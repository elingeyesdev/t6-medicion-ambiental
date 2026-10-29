"""Exploración STAC acotada. Solo metadatos; nunca descarga bandas o analiza agua."""
from __future__ import annotations

import argparse
from datetime import datetime, timezone
import hashlib
import json
import math
from pathlib import Path
import re
import ssl
import sys
from urllib.error import HTTPError, URLError
from urllib.parse import parse_qs, urlencode, urlsplit
from urllib.request import HTTPRedirectHandler, HTTPSHandler, Request, build_opener

RAIZ = Path(__file__).resolve().parents[2]
MAX_BYTES = 2 * 1024 * 1024
PROVEEDORES = {
    "cdse": "https://stac.dataspace.copernicus.eu/v1/search",
    "mpc": "https://planetarycomputer.microsoft.com/api/stac/v1/search",
}


def configuracion():
    return json.loads((RAIZ / "compartido/inundaciones/exploracion.json").read_text(encoding="utf-8"))


def url_consulta(proveedor, ventana):
    c = configuracion()
    return PROVEEDORES[proveedor] + "?" + urlencode({
        "collections": c["coleccion"], "bbox": ",".join(map(str, c["bbox"])),
        "datetime": c["ventanas"][ventana], "limit": c["limitePrimeraPagina"],
    })


def interpretar(cuerpo, tipo, estado, ventana):
    """Un HTTP 200 HTML o JSON incompatible es fallo, nunca cero escenas."""
    if not 200 <= estado < 300:
        raise ValueError("ERROR_HTTP")
    if len(cuerpo) > MAX_BYTES:
        raise ValueError("RESPUESTA_EXCESIVA")
    if tipo.split(";", 1)[0].strip().lower() not in ("application/json", "application/geo+json"):
        raise ValueError("RESPUESTA_NO_JSON")
    try:
        documento = json.loads(cuerpo.decode("utf-8"))
    except (ValueError, UnicodeError) as error:
        raise ValueError("JSON_INVALIDO") from error
    c = configuracion()
    if not isinstance(documento, dict) or documento.get("type") != "FeatureCollection" or not isinstance(documento.get("features"), list) or not isinstance(documento.get("links"), list):
        raise ValueError("CATALOGO_INCOMPATIBLE")
    if len(documento["features"]) > c["limitePrimeraPagina"]:
        raise ValueError("PAGINA_EXCESIVA")
    desde, hasta = c["ventanas"][ventana].split("/")
    inicio, fin = [datetime.fromisoformat(s.replace("Z", "+00:00")) for s in (desde, hasta)]
    ids, escenas = set(), []
    for item in documento["features"]:
        if not isinstance(item, dict) or item.get("type") != "Feature" or item.get("collection") != c["coleccion"]:
            raise ValueError("ESCENA_INCOMPATIBLE")
        identificador, propiedades, activos = item.get("id"), item.get("properties"), item.get("assets")
        if not isinstance(identificador, str) or not re.fullmatch(r"[A-Za-z0-9_-]{1,160}", identificador) or identificador in ids or not isinstance(propiedades, dict) or not isinstance(activos, dict):
            raise ValueError("ESCENA_INCOMPATIBLE")
        try:
            fecha = datetime.fromisoformat(propiedades["datetime"].replace("Z", "+00:00"))
            if fecha.tzinfo is None or not inicio <= fecha <= fin:
                raise ValueError("Fecha fuera de ventana")
        except (KeyError, AttributeError, TypeError, ValueError) as error:
            raise ValueError("FECHA_INCOMPATIBLE") from error
        bbox = item.get("bbox")
        if not isinstance(bbox, list) or len(bbox) != 4 or any(type(v) not in (float, int) or not math.isfinite(v) for v in bbox) or not -180 <= bbox[0] < bbox[2] <= 180 or not -90 <= bbox[1] < bbox[3] <= 90:
            raise ValueError("GEOMETRIA_INCOMPATIBLE")
        a = c["bbox"]
        if bbox[2] <= a[0] or bbox[0] >= a[2] or bbox[3] <= a[1] or bbox[1] >= a[3]:
            raise ValueError("ESCENA_FUERA_DE_AREA")
        nubosidad = propiedades.get("eo:cloud_cover")
        if nubosidad is not None and (type(nubosidad) not in (float, int) or not 0 <= nubosidad <= 100):
            raise ValueError("NUBOSIDAD_INVALIDA")
        scl = activos.get("SCL") or activos.get("SCL_20m")
        for activo in activos.values():
            if isinstance(activo, dict) and isinstance(activo.get("href"), str):
                enlace = urlsplit(activo["href"])
                campos = {k.lower() for k in parse_qs(enlace.query, keep_blank_values=True)}
                if enlace.username or enlace.password or campos.intersection({"sig", "access_token", "api_key", "token", "credential", "sas"}):
                    raise ValueError("ACTIVO_NO_PUBLICABLE")
        enlace_scl = None
        if scl:
            if not isinstance(scl, dict) or not isinstance(scl.get("href"), str):
                raise ValueError("ACTIVO_INVALIDO")
            enlace_scl = scl["href"]
            u = urlsplit(enlace_scl)
            if u.scheme != "https" or not u.hostname or u.username or u.password or u.query or u.fragment:
                raise ValueError("ACTIVO_NO_PUBLICABLE")
        ids.add(identificador)
        tile = re.search(r"_T([0-9]{2}[A-Z]{3})_", identificador)
        escenas.append({
            "identificador": identificador, "fecha": propiedades["datetime"],
            "tile": propiedades.get("s2:mgrs_tile") or (tile.group(1) if tile else None),
            "plataforma": propiedades.get("platform"), "nubesEscenaPorcentaje": nubosidad,
            "nubesEnAreaPorcentaje": None, "sclDisponibleEnMetadatos": enlace_scl is not None,
            "sclUrlSinFirma": enlace_scl, "pixelesAgua": None,
        })
    return {
        "escenas": escenas,
        "tieneSiguientePagina": any(isinstance(l, dict) and l.get("rel") == "next" for l in documento["links"]),
    }


def informe(cuerpo, tipo, estado, proveedor, ventana, recuperado):
    base = {
        "version": configuracion()["version"], "proveedorCatalogo": proveedor,
        "url": url_consulta(proveedor, ventana), "ventana": ventana, "recuperadoEn": recuperado,
        "http": {"estado": estado, "tipoContenido": tipo, "bytes": len(cuerpo), "sha256": hashlib.sha256(cuerpo).hexdigest()},
        "rasterDescargado": False, "pixelesAgua": None, "superficieInundadaKm2": None,
        "metodoAprobado": False,
    }
    try:
        resultado = interpretar(cuerpo, tipo, estado, ventana)
        base.update(resultado)
        base["estado"] = "metadatos_disponibles" if resultado["escenas"] else "consulta_valida_sin_coincidencias"
    except ValueError as error:
        base.update({"estado": "catalogo_no_verificado", "motivo": str(error), "escenas": None, "tieneSiguientePagina": None})
    return base


class SinRedirecciones(HTTPRedirectHandler):
    def redirect_request(self, request, fp, code, msg, headers, newurl):
        return None


def consultar(proveedor, ventana, destino, ca=None):
    destino.mkdir(parents=True, exist_ok=False)
    contexto = ssl.create_default_context(cafile=str(ca) if ca else None)
    cliente = build_opener(SinRedirecciones(), HTTPSHandler(context=contexto))
    solicitud = Request(url_consulta(proveedor, ventana), headers={"Accept": "application/geo+json, application/json", "User-Agent": "GeoCruz-ANA01-metadata/0.1"})
    fecha = datetime.now(timezone.utc).isoformat()
    try:
        try:
            respuesta = cliente.open(solicitud, timeout=30)
        except HTTPError as error:
            respuesta = error
        with respuesta:
            estado, tipo = respuesta.status, respuesta.headers.get("Content-Type", "")
            cuerpo = respuesta.read(MAX_BYTES + 1)
        if len(cuerpo) > MAX_BYTES:
            raise ValueError("RESPUESTA_EXCESIVA")
        resumen = informe(cuerpo, tipo, estado, proveedor, ventana, fecha)
        if resumen["estado"] != "catalogo_no_verificado":
            (destino / "respuesta.json").write_bytes(cuerpo)
    except (URLError, OSError, ValueError) as error:
        resumen = {"estado": "catalogo_no_verificado", "motivo": type(error).__name__,
                   "proveedorCatalogo": proveedor, "ventana": ventana, "recuperadoEn": fecha,
                   "escenas": None, "rasterDescargado": False, "pixelesAgua": None,
                   "superficieInundadaKm2": None, "metodoAprobado": False}
    (destino / "informe.json").write_text(json.dumps(resumen, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    print(json.dumps({"directorio": str(destino), "estado": resumen["estado"], "escenasEnEstaPagina": len(resumen["escenas"]) if resumen["escenas"] is not None else None, "motivo": resumen.get("motivo"), "rasterDescargado": False}, ensure_ascii=False))
    return 2 if resumen["estado"] == "catalogo_no_verificado" else 0


def revisar():
    directorio = RAIZ / "docs/evidencias/ana01"
    for proveedor, ventana in (("cdse", "A"), ("mpc", "A"), ("mpc", "B")):
        carpeta = directorio / f"{proveedor}-{ventana}"
        r = json.loads((carpeta / "informe.json").read_text(encoding="utf-8"))
        if r["estado"] == "catalogo_no_verificado":
            if r["escenas"] is not None:
                raise ValueError("Un fallo no acredita cero escenas")
            print(f"{proveedor} {ventana}: no verificado ({r['motivo']}); número de escenas desconocido")
            continue
        cuerpo = (carpeta / "respuesta.json").read_bytes()
        nuevo = informe(cuerpo, r["http"]["tipoContenido"], r["http"]["estado"], proveedor, ventana, r["recuperadoEn"])
        if r != nuevo:
            raise ValueError("La evidencia no coincide con los originales o parámetros actuales")
        print(f"{proveedor} {ventana}: {len(r['escenas'])} metadatos de escenas; siguiente página={r['tieneSiguientePagina']}")
        for s in r["escenas"]:
            print(f"  {s['fecha']} | {s['tile']} | nubes escena={s['nubesEscenaPorcentaje']}% | {s['identificador']}")
    print("Sin lectura de píxeles: agua e inundación no calculadas. Método pendiente de revisión.")
    return 0


def main():
    if hasattr(sys.stdout, "reconfigure"):
        sys.stdout.reconfigure(encoding="utf-8")
    p = argparse.ArgumentParser(description=__doc__)
    sub = p.add_subparsers(dest="operacion", required=True)
    sub.add_parser("revisar", help="Verificar evidencia congelada sin Internet")
    red = sub.add_parser("consultar", help="Una página de metadatos STAC; no descarga bandas")
    red.add_argument("--proveedor", choices=PROVEEDORES, required=True)
    red.add_argument("--ventana", choices=("A", "B"), required=True)
    red.add_argument("--salida", type=Path, required=True, help="Directorio nuevo, sin sobrescribir")
    red.add_argument("--ca", type=Path, help="Certificados CA locales; TLS se verifica siempre")
    args = p.parse_args()
    if args.operacion == "revisar":
        return revisar()
    return consultar(args.proveedor, args.ventana, args.salida, args.ca)


if __name__ == "__main__":
    sys.exit(main())
