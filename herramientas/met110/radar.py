"""Revisa únicamente metadatos de la alternativa Sentinel-1 RTC, sin descargar bandas."""
import argparse
from datetime import datetime
import hashlib
import json
import math
from pathlib import Path
from urllib.parse import urlencode, urlsplit, parse_qs

from escenas import config

COLLECTION = "sentinel-1-rtc"
BASE = "https://planetarycomputer.microsoft.com/api/stac/v1"
MAX_BYTES = 1024 * 1024


def urls():
    c = config()
    return {**{k + ".json": BASE + "/search?" + urlencode({
        "collections": COLLECTION, "bbox": ",".join(map(str, c["bbox"])),
        "datetime": v, "limit": 20}) for k, v in c["ventanas"].items()},
        "coleccion.json": BASE + "/collections/" + COLLECTION}


def sin_secretos(value):
    if isinstance(value, dict):
        return all(sin_secretos(v) for v in value.values())
    if isinstance(value, list):
        return all(sin_secretos(v) for v in value)
    if isinstance(value, str) and value.startswith(("https://", "http://")):
        return not {k.lower() for k in parse_qs(urlsplit(value).query)} & {
            "sig", "token", "api_key", "api-key", "subscription-key", "access_token"}
    return True


def revisar(folder):
    expected = urls()
    receipts = json.loads((folder / "consultas.json").read_text(encoding="utf-8"))
    if not isinstance(receipts, list) or len(receipts) != 3:
        raise ValueError("Se requieren la colección y las dos consultas.")
    documents, seen = {}, set()
    for receipt in receipts:
        name = receipt["archivo"]
        if (name not in expected or name in seen or receipt["url"] != expected[name]
                or receipt["http"] != 200 or "json" not in receipt["tipo"]):
            raise ValueError("Recibo inesperado.")
        seen.add(name)
        if datetime.fromisoformat(receipt["consultado_utc"]).utcoffset() is None:
            raise ValueError("Consulta sin zona horaria.")
        path = folder / name
        if not 0 < path.stat().st_size <= MAX_BYTES:
            raise ValueError("Metadatos fuera del límite.")
        raw = path.read_bytes()
        if len(raw) != receipt["bytes"] or hashlib.sha256(raw).hexdigest() != receipt["sha256"]:
            raise ValueError("Integridad distinta.")
        documents[name] = json.loads(raw)
        if not sin_secretos(documents[name]):
            raise ValueError("La captura contiene un enlace de acceso temporal.")
    collection = documents["coleccion.json"]
    if collection.get("type") != "Collection" or collection.get("id") != COLLECTION:
        raise ValueError("Otra colección.")
    groups, next_pages = {}, {}
    for name in ("A", "B"):
        doc = documents[name + ".json"]
        if doc.get("type") != "FeatureCollection" or not isinstance(doc.get("features"), list):
            raise ValueError("No es un catálogo STAC.")
        next_pages[name] = any(link.get("rel") == "next" for link in doc.get("links", []))
        begin, end = [datetime.fromisoformat(s.replace("Z", "+00:00")) for s in config()["ventanas"][name].split("/")]
        records, ids = [], set()
        for scene in doc["features"]:
            p = scene["properties"]
            when = datetime.fromisoformat(p["datetime"].replace("Z", "+00:00"))
            if (scene.get("collection") != COLLECTION or scene["id"] in ids
                    or when.utcoffset() is None or not begin <= when <= end):
                raise ValueError("Escena duplicada, ajena o fuera de fecha.")
            ids.add(scene["id"])
            bbox = scene["bbox"]
            area = config()["bbox"]
            if (len(bbox) != 4 or not all(type(v) in (int, float) and math.isfinite(v) for v in bbox)
                    or bbox[0] >= bbox[2] or bbox[1] >= bbox[3]
                    or bbox[0] >= area[2] or bbox[2] <= area[0] or bbox[1] >= area[3] or bbox[3] <= area[1]):
                raise ValueError("Caja sin intersección.")
            records.append({"id": scene["id"], "fecha": p["datetime"],
                            "plataforma": p["platform"], "orbitaRelativa": p["sat:relative_orbit"],
                            "sentido": p["sat:orbit_state"], "modo": p["sar:instrument_mode"],
                            "polarizaciones": p["sar:polarizations"], "epsg": p["proj:epsg"],
                            "transform": p["proj:transform"], "shape": p["proj:shape"],
                            "bandas": {k: {"bytesAnunciados": scene["assets"][k].get("file:size"),
                                           "raster": scene["assets"][k].get("raster:bands")}
                                       for k in ("vv", "vh")}, "pixelesLeidos": False})
        groups[name] = records
    return {"tipo": "alternativa_radar_solo_metadatos", "coleccion": COLLECTION,
            "licenciaDeclaradaColeccion": collection.get("license"), "escenas": groups,
            "haySiguientePagina": next_pages, "bandasDescargadas": False,
            "accesoRasterVerificado": False, "metodoAprobado": False,
            "parejaAdoptada": None, "superficieInundadaHa": None,
            "nota": "El catálogo es público; la colección declara cuenta Planetary Computer para sus bandas RTC. "
                    "No se intentó autenticar ni descargar imágenes. Las cajas no certifican la huella ni la visibilidad."}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--directorio", required=True, type=Path)
    args = parser.parse_args()
    try:
        result = revisar(args.directorio)
        print(json.dumps(result, ensure_ascii=False, indent=2))
    except (ValueError, KeyError, TypeError, OSError):
        parser.exit(1, "No se valida la captura radar. No se interpreta como ausencia de inundación.\n")


if __name__ == "__main__":
    main()
