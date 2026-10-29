"""Una petición explícita a NOMADS: TMP a 2 m, GFS 0.25°, Sudamérica."""
import argparse
from datetime import datetime, timezone
import hashlib
import http.client
import json
from pathlib import Path
import ssl
import time
from urllib.parse import urlencode

MAX_BYTES = 2_000_000
BBOX = (-82, -57, -34, 13)  # oeste, sur, este, norte; grados geográficos
HOST = "nomads.ncep.noaa.gov"


def parametros(ciclo, plazo, conjunto=False):
    fecha = datetime.strptime(ciclo, "%Y%m%d%H")
    if fecha.strftime("%Y%m%d%H") != ciclo or fecha.hour not in (0, 6, 12, 18):
        raise ValueError("El ciclo requiere YYYYMMDDHH, con HH=00,06,12,18.")
    if not 0 <= plazo <= 120:
        raise ValueError("El plazo debe estar entre 0 y 120 horas.")
    campos = {"var_TMP": "on", "lev_2_m_above_ground": "on"}
    if conjunto:
        campos.update({"var_" + k: "on" for k in ("RH", "UGRD", "VGRD", "VIS", "WEASD", "PRMSL", "APCP", "PRATE")})
        campos.update({"lev_" + k: "on" for k in ("10_m_above_ground", "surface", "mean_sea_level")})
    consulta = urlencode({"file": f"gfs.t{fecha:%H}z.pgrb2.0p25.f{plazo:03d}",
                         **campos, "subregion": "",
                         "leftlon": 278, "rightlon": 326, "toplat": 13, "bottomlat": -57,
                         "dir": f"/gfs.{fecha:%Y%m%d}/{fecha:%H}/atmos"})
    return "/cgi-bin/filter_gfs_0p25.pl?" + consulta


def descargar(ciclo, plazo, transporte=None, conjunto=False):
    ruta = parametros(ciclo, plazo, conjunto)
    recibo = {"version": 1, "url": "https://" + HOST + ruta, "ciclo": ciclo,
              "plazo_horas": plazo, "bbox_solicitado": list(BBOX), "max_bytes": MAX_BYTES,
              "timeout_segundos": 30, "peticiones": 1, "capturado_utc": datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")}
    inicio = time.monotonic()
    conexion = (transporte or http.client.HTTPSConnection)(HOST, timeout=30, context=ssl.create_default_context())

    def restante():
        segundos = 30 - (time.monotonic() - inicio)
        if segundos <= 0:
            raise TimeoutError("Se agotó el límite de 30 segundos.")
        return segundos

    try:
        conexion.connect()
        socket = conexion.sock
        socket.settimeout(restante())
        conexion.request("GET", ruta, headers={"User-Agent": "GeoSur-RasterETL/1.0", "Accept-Encoding": "identity"})
        socket.settimeout(restante())
        respuesta = conexion.getresponse()
        recibo["http"] = respuesta.status
        if respuesta.status != 200:
            raise ValueError(f"NOMADS respondió HTTP {respuesta.status}; no se siguen redirecciones ni se reintenta.")
        largo = respuesta.getheader("Content-Length")
        if largo is not None and (not largo.isdigit() or int(largo) > MAX_BYTES):
            raise ValueError("Respuesta mayor al límite o tamaño inválido.")
        if respuesta.getheader("Content-Encoding", "identity") != "identity":
            raise ValueError("No se admite contenido comprimido HTTP.")
        contenido = bytearray()
        while True:
            socket.settimeout(restante())
            bloque = respuesta.read1(min(65536, MAX_BYTES + 1 - len(contenido)))
            if not bloque:
                break
            contenido.extend(bloque)
            if len(contenido) > MAX_BYTES:
                raise ValueError("Respuesta mayor a 2 MB; descarga interrumpida.")
        restante()
        if largo is not None and len(contenido) != int(largo):
            raise ValueError("La respuesta está truncada.")
        cursor = mensajes = 0
        while cursor < len(contenido):
            cabecera = contenido[cursor:cursor+16]
            if len(cabecera) != 16 or cabecera[:4] != b"GRIB" or cabecera[7] != 2:
                raise ValueError("Se esperaban mensajes GRIB2 completos, no HTML u otra respuesta.")
            largo_mensaje = int.from_bytes(cabecera[8:16], "big")
            fin = cursor + largo_mensaje
            if largo_mensaje < 20 or fin > len(contenido) or contenido[fin-4:fin] != b"7777":
                raise ValueError("El mensaje GRIB2 está truncado.")
            mensajes += 1
            if mensajes > (64 if conjunto else 1):
                raise ValueError("Hay más mensajes que los admitidos en esta consulta.")
            cursor = fin
        if not mensajes:
            raise ValueError("La respuesta no contiene datos GRIB2.")
        recibo.update(estado="descargado", bytes=len(contenido), mensajes=mensajes, sha256=hashlib.sha256(contenido).hexdigest())
        return bytes(contenido), recibo
    except (OSError, ValueError, http.client.HTTPException) as error:
        recibo.update(estado="fallido", error=str(error), duracion_segundos=round(time.monotonic() - inicio, 3))
        return None, recibo
    finally:
        conexion.close()


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--ciclo", required=True)
    parser.add_argument("--plazo", type=int, default=0)
    parser.add_argument("--destino", required=True)
    parser.add_argument("--recibo", required=True)
    parser.add_argument("--consultar-noaa", action="store_true")
    parser.add_argument("--campos-ambientales", action="store_true", help="Temperatura, humedad, viento, lluvia, presión, visibilidad y nieve.")
    args = parser.parse_args()
    try:
        parametros(args.ciclo, args.plazo, args.campos_ambientales)
        destino, recibo = Path(args.destino), Path(args.recibo)
        if not args.consultar_noaa:
            raise ValueError("La descarga requiere --consultar-noaa.")
        if destino.exists() or destino.is_symlink() or recibo.exists() or recibo.is_symlink() or destino.resolve() == recibo.resolve():
            raise ValueError("Destino y recibo deben ser archivos nuevos distintos.")
        if not destino.parent.is_dir() or not recibo.parent.is_dir():
            raise ValueError("Crea primero las carpetas de trabajo.")
        contenido, resultado = descargar(args.ciclo, args.plazo, conjunto=args.campos_ambientales)
        if contenido is not None:
            with destino.open("xb") as archivo:
                archivo.write(contenido)
        with recibo.open("x", encoding="utf-8") as archivo:
            json.dump(resultado, archivo, ensure_ascii=False, indent=2)
            archivo.write("\n")
        print(json.dumps(resultado, ensure_ascii=False))
        return 0 if contenido is not None else 2
    except (ValueError, OSError) as error:
        parser.exit(2, str(error) + "\n")


if __name__ == "__main__":
    raise SystemExit(main())
