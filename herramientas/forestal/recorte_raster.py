"""Recorte técnico de GFC v1.13 mediante rangos HTTP; no publica un producto."""
import argparse
from datetime import datetime, timezone
import hashlib
import io
import json
import math
from pathlib import Path
import re
import struct
import sys
from urllib.request import Request, urlopen

BASE = "https://storage.googleapis.com/earthenginepartners-hansen/GFC-2025-v1.13/"
BANDAS = ("lossyear", "treecover2000", "datamask")
MAX_BYTES = 8_000_000
MAX_RANGO = 3_000_000
MAX_LADO = 128


def guardar_json(ruta, dato):
    ruta.write_text(json.dumps(dato, ensure_ascii=False, indent=2) + "\n",
                    encoding="utf-8", newline="\n")


class Rangos:
    """Conserva cada respuesta exacta; una reproducción nunca consulta la red."""

    def __init__(self, directorio, reproducir=False, abrir=urlopen):
        self.directorio = Path(directorio)
        self.reproducir, self.abrir = reproducir, abrir
        self.bytes = 0
        self.registros = []
        self.versiones = {}
        self.pendientes = []
        if reproducir:
            with (self.directorio / "rangos.json").open("rb") as archivo:
                contenido = archivo.read(100001)
            if len(contenido) > 100000:
                raise ValueError("Recibo demasiado grande.")
            recibo = json.loads(contenido)
            if recibo.get("version") != "gfc-rangos-1":
                raise ValueError("Versión de captura no admitida.")
            self.pendientes = recibo["rangos"]
            if not isinstance(self.pendientes, list) or len(self.pendientes) > 24:
                raise ValueError("Captura demasiado grande.")
        else:
            self.directorio.mkdir(parents=True, exist_ok=False)

    def leer(self, banda, inicio, cantidad):
        if (banda not in BANDAS or type(inicio) is not int or inicio < 0
                or type(cantidad) is not int or not 0 < cantidad <= MAX_RANGO
                or self.bytes + cantidad > MAX_BYTES or len(self.registros) >= 24):
            raise ValueError("Rango fuera del límite de lectura.")
        url = BASE + "Hansen_GFC-2025-v1.13_" + banda + "_10S_070W.tif"
        fin = inicio + cantidad - 1
        nombre = f"{len(self.registros):02d}-{banda}-{inicio}-{fin}.bin"
        if self.reproducir:
            if len(self.registros) >= len(self.pendientes):
                raise ValueError("Falta un rango en la captura.")
            registro = self.pendientes[len(self.registros)]
            if any(registro.get(k) != v for k, v in {
                    "url": url, "inicio": inicio, "cantidad": cantidad,
                    "archivo": nombre}.items()):
                raise ValueError("La captura no coincide con el rango solicitado.")
            with (self.directorio / nombre).open("rb") as archivo:
                dato = archivo.read(cantidad + 1)
            if len(dato) != cantidad:
                raise ValueError("Longitud de captura incorrecta.")
            if hashlib.sha256(dato).hexdigest() != registro.get("sha256"):
                raise ValueError("La captura cambió.")
            estado, cabeceras = registro["http"], registro["cabeceras"]
        else:
            cab = {"Range": f"bytes={inicio}-{fin}", "Accept-Encoding": "identity"}
            if banda in self.versiones:
                cab["If-Match"] = self.versiones[banda][0]
            with self.abrir(Request(url, headers=cab), timeout=20) as respuesta:
                estado = respuesta.status
                cabeceras = {k.lower(): v for k, v in respuesta.headers.items()}
                # Rechazar un servidor sin rangos antes de descargar su cuerpo.
                if estado != 206:
                    raise ValueError("El proveedor no respetó el rango HTTP.")
                dato = respuesta.read(cantidad + 1)
            registro = {"url": url, "inicio": inicio, "cantidad": cantidad,
                        "archivo": nombre, "http": estado, "cabeceras": cabeceras,
                        "sha256": hashlib.sha256(dato).hexdigest(),
                        "consultado_utc": datetime.now(timezone.utc).isoformat()}
        contenido = cabeceras.get("content-range", "")
        coincidencia = re.fullmatch(r"bytes (\d+)-(\d+)/(\d+)", contenido)
        etag = cabeceras.get("etag", "")
        if (estado != 206 or len(dato) != cantidad or not coincidencia
                or tuple(map(int, coincidencia.groups()[:2])) != (inicio, fin)
                or int(coincidencia[3]) <= fin
                or not re.fullmatch(r'"[0-9a-fA-F]{32}"', etag)
                or cabeceras.get("content-encoding", "identity") != "identity"):
            raise ValueError("Respuesta de rango incompleta o incompatible.")
        version = (etag, int(coincidencia[3]))
        if banda in self.versiones and self.versiones[banda] != version:
            raise ValueError("El archivo cambió durante la lectura.")
        self.versiones[banda] = version
        if not self.reproducir:
            (self.directorio / nombre).write_bytes(dato)
        self.registros.append(registro)
        self.bytes += cantidad
        return dato

    def terminar(self):
        if self.reproducir and len(self.registros) != len(self.pendientes):
            raise ValueError("Sobran rangos en la captura.")
        if not self.reproducir:
            guardar_json(self.directorio / "rangos.json",
                         {"version": "gfc-rangos-1", "rangos": self.registros})


def cabecera(dato):
    if dato[:4] != b"II*\x00":
        raise ValueError("Se esperaba TIFF clásico little endian.")
    inicio = struct.unpack_from("<I", dato, 4)[0]
    if inicio + 2 > len(dato):
        raise ValueError("IFD ausente.")
    cantidad = struct.unpack_from("<H", dato, inicio)[0]
    if cantidad > 32 or inicio + 2 + cantidad * 12 + 4 > len(dato):
        raise ValueError("IFD fuera de la cabecera.")
    etiquetas = {}
    for i in range(cantidad):
        tag, tipo, cuenta, valor = struct.unpack_from("<HHII", dato, inicio + 2 + 12*i)
        if tag in etiquetas:
            raise ValueError("Etiqueta TIFF duplicada.")
        etiquetas[tag] = (tipo, cuenta, valor)
    esperadas = {256: 40000, 257: 40000, 258: 8, 259: 5, 262: 1,
                 277: 1, 278: 1, 284: 1, 317: 1, 339: 1}
    if any(etiquetas.get(k) != (3, 1, v) for k, v in esperadas.items()):
        raise ValueError("Cambió la organización raster esperada.")
    if etiquetas.get(273, ())[:2] != (4, 40000) or etiquetas.get(279, ())[:2] != (4, 40000):
        raise ValueError("No hay una tira por fila.")
    return etiquetas


def geometria(fuente, banda, etiquetas):
    valores = {}
    for tag, tipo, cuenta, formato in [(33550, 12, 3, "d"),
                                        (33922, 12, 6, "d"),
                                        (34735, 3, 32, "H")]:
        if etiquetas.get(tag, ())[:2] != (tipo, cuenta):
            raise ValueError("Georreferencia no reconocida.")
        n = struct.calcsize("<" + formato) * cuenta
        valores[tag] = struct.unpack("<" + formato * cuenta,
                                    fuente.leer(banda, etiquetas[tag][2], n))
    if valores[33550] != (0.00025, 0.00025, 0) or valores[33922] != (0, 0, 0, -70, -10, 0):
        raise ValueError("Cambió la cuadrícula de la tesela.")
    claves = valores[34735]
    if claves[:4] != (1, 1, 0, 7):
        raise ValueError("GeoKeys no reconocidas.")
    mapa = {claves[i]: claves[i+1:i+4] for i in range(4, len(claves), 4)}
    if (mapa.get(1024) != (0, 1, 2) or mapa.get(1025) != (0, 1, 1)
            or mapa.get(2048) != (0, 1, 4326)):
        raise ValueError("Se requiere WGS84 geográfico y PixelIsArea.")
    return {"epsg": 4326, "pixel": "area", "oeste": -70, "norte": -10,
            "paso_grados": 0.00025, "ancho": 40000, "alto": 40000}


def limites(columna, fila, ancho, alto):
    if (any(type(v) is not int for v in (columna, fila, ancho, alto))
            or not 1 <= ancho <= MAX_LADO or not 1 <= alto <= MAX_LADO
            or columna < 0 or fila < 0 or columna + ancho > 40000
            or fila + alto > 40000):
        raise ValueError("La ventana debe quedar dentro de la tesela y medir hasta 128 por 128.")


def mini_tiff(tiras):
    """TIFF de ancho completo y pocas filas; Pillow nunca abre la imagen de 1.600 Mpx."""
    n = len(tiras)
    tags = [(256, 4, 1, 40000), (257, 4, 1, n), (258, 3, 1, 8),
            (259, 3, 1, 5), (262, 3, 1, 1), (273, 4, n, None),
            (277, 3, 1, 1), (278, 4, 1, 1), (279, 4, n, None),
            (284, 3, 1, 1), (317, 3, 1, 1), (339, 3, 1, 1)]
    inicio_tablas = 8 + 2 + 12 * len(tags) + 4
    inicio_datos = inicio_tablas + (8 * n if n > 1 else 0)
    offsets, cursor = [], inicio_datos
    for tira in tiras:
        offsets.append(cursor)
        cursor += len(tira)
    cuentas = [len(t) for t in tiras]
    tabla = b""
    entradas = []
    for tag, tipo, cuenta, valor in tags:
        if tag == 273:
            valor = offsets[0] if n == 1 else inicio_tablas
        elif tag == 279:
            valor = cuentas[0] if n == 1 else inicio_tablas + 4*n
        entradas.append(struct.pack("<HHII", tag, tipo, cuenta, valor))
    if n > 1:
        tabla = struct.pack("<" + "I"*n, *offsets) + struct.pack("<" + "I"*n, *cuentas)
    return (b"II*\x00" + struct.pack("<I", 8) + struct.pack("<H", len(tags))
            + b"".join(entradas) + b"\x00"*4 + tabla + b"".join(tiras))


def leer_banda(fuente, banda, columna, fila, ancho, alto):
    from PIL import Image
    tags = cabecera(fuente.leer(banda, 0, 16384))
    geo = geometria(fuente, banda, tags)
    offsets = struct.unpack("<" + "I"*alto,
                            fuente.leer(banda, tags[273][2] + 4*fila, 4*alto))
    cuentas = struct.unpack("<" + "I"*alto,
                            fuente.leer(banda, tags[279][2] + 4*fila, 4*alto))
    if (any(not 0 < n <= 100000 for n in cuentas)
            or any(o < 320378 for o in offsets)
            or any(offsets[i+1] != offsets[i] + cuentas[i] for i in range(alto-1))):
        raise ValueError("Tiras no contiguas o tamaños inesperados.")
    bloque = fuente.leer(banda, offsets[0], sum(cuentas))
    tiras, cursor = [], 0
    for cuenta in cuentas:
        tiras.append(bloque[cursor:cursor+cuenta])
        cursor += cuenta
    with Image.open(io.BytesIO(mini_tiff(tiras))) as imagen:
        if imagen.mode != "L" or imagen.size != (40000, alto):
            raise ValueError("Píxeles decodificados incompatibles.")
        recorte = imagen.crop((columna, 0, columna+ancho, alto))
        dato = recorte.tobytes()
    permitidos = {"lossyear": range(26), "treecover2000": range(101), "datamask": range(3)}
    if any(v not in permitidos[banda] for v in dato):
        raise ValueError("Valor raster fuera de la versión.")
    return geo, dato


def calcular(bandas, columna, fila, ancho, alto, umbral, desde, hasta):
    from pyproj import Geod
    limites(columna, fila, ancho, alto)
    if (type(umbral) is not int or not 1 <= umbral <= 100
            or type(desde) is not int or type(hasta) is not int
            or not 2001 <= desde <= hasta <= 2025
            or set(bandas) != set(BANDAS)
            or any(len(v) != ancho*alto for v in bandas.values())):
        raise ValueError("Criterio, período o bandas incompatibles.")
    for banda, maximo in [("lossyear", 25), ("treecover2000", 100), ("datamask", 2)]:
        if any(type(v) is not int or not 0 <= v <= maximo for v in bandas[banda]):
            raise ValueError("Valor de banda inválido.")
    geod = Geod(ellps="WGS84")
    serie = {anio: 0.0 for anio in range(2001, 2026)}
    conteos = {"sin_dato_base": 0, "agua_base": 0, "tierra_base": 0,
               "arbolado_base": 0, "perdida_periodo": 0}
    features = []
    for y in range(alto):
        norte = -10 - (fila+y)*0.00025
        sur = norte - 0.00025
        area = abs(geod.polygon_area_perimeter(
            [-70, -69.99975, -69.99975, -70], [norte, norte, sur, sur])[0]) / 10000
        for x in range(ancho):
            i = y*ancho+x
            mask, cobertura, perdida = (bandas[b][i] for b in ("datamask", "treecover2000", "lossyear"))
            conteos[{0: "sin_dato_base", 1: "tierra_base", 2: "agua_base"}[mask]] += 1
            if mask != 1 or cobertura < umbral:
                continue
            conteos["arbolado_base"] += 1
            if perdida:
                anio = 2000+perdida
                serie[anio] += area
                if desde <= anio <= hasta:
                    oeste = -70 + (columna+x)*0.00025
                    este = oeste + 0.00025
                    conteos["perdida_periodo"] += 1
                    features.append({
                        "type": "Feature", "id": f"{fila+y}-{columna+x}",
                        "properties": {"anio": anio, "area_ha": round(area, 8)},
                        "geometry": {"type": "Polygon", "coordinates": [[
                            [oeste, norte], [oeste, sur], [este, sur],
                            [este, norte], [oeste, norte]]]}})
    return {
        "estado": "recorte_tecnico_no_validado", "integrado_en_api": False,
        "criterio": {"base": 2000, "operador": ">=", "porcentaje": umbral, "adoptado": False},
        "consulta": {"desde": desde, "hasta": hasta, "extremos_incluidos": True},
        "ventana_pixeles": {"columna": columna, "fila": fila, "ancho": ancho, "alto": alto},
        "bbox": [-70+columna*0.00025, -10-(fila+alto)*0.00025,
                 -70+(columna+ancho)*0.00025, -10-fila*0.00025],
        "conteos_pixeles": conteos,
        "serie": [{"anio": a, "perdida_ha": round(v, 8)} for a, v in serie.items()],
        "total_periodo_ha": round(math.fsum(serie[a] for a in range(desde, hasta+1)), 8),
        "cobertura_observaciones_fraccion": None,
        "limites": [
            "Ventana rectangular técnica sin equivalencia municipal ni piloto aprobado.",
            "La pérdida de cobertura arbórea no identifica por sí sola deforestación ni su causa.",
            "datamask describe la base; no acredita observación anual completa.",
            "El área usa WGS84 por fila. Los decimales no representan exactitud de medición.",
            "Los años posteriores a 2010 tienen cambios metodológicos en la fuente.",
            "Área cartografiada sin ajuste estadístico ni intervalo de confianza; no es una estimación definitiva.",
        ],
    }, {"type": "FeatureCollection", "features": features}


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    for nombre in ("columna", "fila", "ancho", "alto", "umbral", "desde", "hasta"):
        parser.add_argument("--" + nombre, required=True, type=int)
    parser.add_argument("--captura", required=True, type=Path)
    parser.add_argument("--salida", required=True, type=Path)
    parser.add_argument("--reproducir", action="store_true")
    args = parser.parse_args(argv)
    try:
        from PIL import Image
        limites(args.columna, args.fila, args.ancho, args.alto)
        # Validar parámetros antes de acceder a la red o crear archivos.
        if not 1 <= args.umbral <= 100 or not 2001 <= args.desde <= args.hasta <= 2025:
            raise ValueError("Umbral o período fuera de la versión.")
        if args.salida.exists():
            raise FileExistsError("La carpeta de salida debe ser nueva.")
        fuente = Rangos(args.captura, args.reproducir)
        bandas, geometrias = {}, []
        for banda in BANDAS:
            geo, bandas[banda] = leer_banda(fuente, banda, args.columna, args.fila,
                                            args.ancho, args.alto)
            geometrias.append(geo)
        if any(g != geometrias[0] for g in geometrias):
            raise ValueError("Las bandas no están alineadas.")
        fuente.terminar()
        resumen, capa = calcular(bandas, args.columna, args.fila, args.ancho, args.alto,
                                  args.umbral, args.desde, args.hasta)
        resumen["fuente"] = {
            "producto": "Hansen GFC", "version": "GFC-2025-v1.13", "tesela": "10S_070W",
            "licencia": "CC BY 4.0",
            "licencia_url": "https://creativecommons.org/licenses/by/4.0/",
            "transformaciones": "Recorte, filtro base y año, vectorización por celda y área geodésica.",
            "atribucion": "Hansen/UMD/Google/USGS/NASA",
            "documentacion": BASE + "download.html",
            "cuadricula": geometrias[0],
            "bytes_transferidos_captura": fuente.bytes,
            "rangos": len(fuente.registros),
            "etag": {k: v[0] for k, v in fuente.versiones.items()},
            "captura_sha256": hashlib.sha256((args.captura / "rangos.json").read_bytes()).hexdigest(),
        }
        args.salida.mkdir(parents=True, exist_ok=False)
        guardar_json(args.salida / "resumen.json", resumen)
        guardar_json(args.salida / "perdida.geojson", capa)
        for banda, pixeles in bandas.items():
            Image.frombytes("L", (args.ancho, args.alto), pixeles).save(args.salida / (banda + ".png"))
        print(json.dumps({"bytes_leidos": fuente.bytes, "pixeles": args.ancho*args.alto,
                          "perdida_periodo_ha": resumen["total_periodo_ha"],
                          "estado": resumen["estado"]}, ensure_ascii=False))
        return 0
    except (OSError, ValueError, KeyError, TypeError, struct.error, ImportError) as error:
        print("No se pudo preparar el recorte: " + str(error), file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
