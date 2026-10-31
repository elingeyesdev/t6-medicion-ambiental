"""Compara disponibilidad de escenas, sin calcular agua ni inundaciones."""
import argparse
from datetime import datetime, timezone
import hashlib
import json
from pathlib import Path
import sys
from urllib.parse import urlencode
from urllib.request import Request, build_opener

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT))
from herramientas.inundaciones.catalogo import interpretar, SinRedirecciones, MAX_BYTES

ENDPOINT = "https://planetarycomputer.microsoft.com/api/stac/v1/search"


def config():
    return json.loads((ROOT / "compartido/inundaciones/metodo-m4.json").read_text(encoding="utf-8"))


def url(ventana):
    c = config()
    return ENDPOINT + "?" + urlencode({
        "collections": c["coleccion"], "bbox": ",".join(map(str, c["bbox"])),
        "datetime": c["ventanas"][ventana], "limit": c["limitePrimeraPagina"],
    })


def escenas(raw, tipo, status, ventana):
    c = config()
    result = interpretar(raw, tipo, status, ventana, config=c)
    entries = json.loads(raw)["features"]
    extra = []
    for item in entries:
        p, asset = item["properties"], item["assets"].get("SCL")
        if not isinstance(asset, dict):
            continue
        required = ["s2:mgrs_tile", "platform", "sat:relative_orbit", "s2:processing_baseline", "proj:epsg"]
        if any(p.get(k) is None for k in required):
            continue
        bbox = item["bbox"]
        complete = bbox[0] <= c["bbox"][0] and bbox[1] <= c["bbox"][1] and bbox[2] >= c["bbox"][2] and bbox[3] >= c["bbox"][3]
        extra.append({
            "id": item["id"], "fecha": p["datetime"], "tile": p["s2:mgrs_tile"],
            "plataforma": p["platform"], "orbita": p["sat:relative_orbit"],
            "baseline": p["s2:processing_baseline"], "epsg": p["proj:epsg"],
            "nubesEscenaPorcentaje": p.get("eo:cloud_cover"),
            "bboxContieneArea": complete,
            "scl": {k: asset.get(k) for k in ("href", "proj:bbox", "proj:shape", "proj:transform", "gsd", "type")},
        })
    return extra, result["tieneSiguientePagina"]


def parejas(a, b):
    result = []
    for first in a:
        for second in b:
            same = all(first[k] == second[k] for k in ("tile", "plataforma", "orbita", "baseline", "epsg"))
            grid = all(first["scl"][k] == second["scl"][k] for k in ("proj:transform", "proj:shape", "proj:bbox", "gsd"))
            if not same or not grid or not first["bboxContieneArea"] or not second["bboxContieneArea"]:
                continue
            if first["scl"]["gsd"] != 20 or first["scl"]["proj:transform"] is None:
                continue
            days = (datetime.fromisoformat(second["fecha"].replace("Z", "+00:00")) - datetime.fromisoformat(first["fecha"].replace("Z", "+00:00"))).total_seconds() / 86400
            if days <= 0:
                continue
            clouds = [x["nubesEscenaPorcentaje"] for x in (first, second)]
            score = max(101 if x is None else x for x in clouds)
            result.append({"referencia": first["id"], "comparacion": second["id"],
                           "separacionDias": days, "peorNubeEscena": score,
                           "aprobada": False})
    return sorted(result, key=lambda x: (x["peorNubeEscena"], x["separacionDias"], x["referencia"], x["comparacion"]))


def revisar(folder):
    receipt = json.loads((folder / "consultas.json").read_text(encoding="utf-8"))
    if not isinstance(receipt, list) or len(receipt) != 2:
        raise ValueError("Se necesitan ambos recibos.")
    records, pages, seen = {}, {}, set()
    for item in receipt:
        filename = item["archivo"]
        if filename not in ("A.json", "B.json") or filename in seen:
            raise ValueError("Archivo inesperado o duplicado.")
        seen.add(filename)
        name = filename[0]
        if item["url"] != url(name) or item["http"] != 200:
            raise ValueError("Otra consulta o respuesta fallida.")
        if datetime.fromisoformat(item["consultado_utc"]).utcoffset() is None:
            raise ValueError("Fecha sin zona.")
        raw = (folder / filename).read_bytes()
        if len(raw) != item["bytes"] or hashlib.sha256(raw).hexdigest() != item["sha256"]:
            raise ValueError("No coincide la integridad.")
        records[name], pages[name] = escenas(raw, item["tipo"], item["http"], name)
    return {
        "estado": "propuesta_pendiente_revision", "bbox": config()["bbox"],
        "escenas": records, "haySiguientePagina": pages,
        "parejasCandidatas": parejas(records["A"], records["B"]),
        "calidadLocalComprobadaPorCatalogo": False,
        "nota": "El orden solo prioriza consultas de acceso; no adopta un par ni prueba agua.",
        "metodoAprobado": False, "superficieInundadaHa": None,
    }


def capturar(folder):
    folder.mkdir(parents=True, exist_ok=False)
    receipt = []
    for name in ("A", "B"):
        request = Request(url(name), headers={"Accept": "application/geo+json, application/json"})
        with build_opener(SinRedirecciones()).open(request, timeout=30) as response:
            raw = response.read(MAX_BYTES + 1)
            if len(raw) > MAX_BYTES:
                raise ValueError("Catálogo superior al límite.")
            escenas(raw, response.headers.get("Content-Type", ""), response.status, name)
            item = {
                "archivo": name + ".json", "url": url(name),
                "consultado_utc": datetime.now(timezone.utc).isoformat(),
                "http": response.status, "tipo": response.headers.get("Content-Type"),
                "bytes": len(raw), "sha256": hashlib.sha256(raw).hexdigest(),
            }
        (folder / (name + ".json")).write_bytes(raw)
        receipt.append(item)
        (folder / "consultas.json").write_text(json.dumps(receipt, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    return revisar(folder)


def main():
    p = argparse.ArgumentParser(description=__doc__)
    p.add_argument("accion", choices=["revisar", "capturar"])
    p.add_argument("--directorio", type=Path, required=True)
    p.add_argument("--salida", type=Path)
    p.add_argument("--consultar-mpc", action="store_true")
    args = p.parse_args()
    if args.accion == "capturar" and not args.consultar_mpc:
        p.error("La consulta externa necesita --consultar-mpc.")
    try:
        result = capturar(args.directorio) if args.accion == "capturar" else revisar(args.directorio)
        if args.salida:
            args.salida.parent.mkdir(parents=True, exist_ok=True)
            args.salida.write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
        print(json.dumps({"escenas": {k: len(v) for k, v in result["escenas"].items()},
                          "siguiente": result["haySiguientePagina"],
                          "parejas": result["parejasCandidatas"][:2]}))
    except (ValueError, KeyError, TypeError, OSError):
        p.exit(1, "No se valida la captura; revisar recibos, formato y condiciones de acceso.\n")


if __name__ == "__main__":
    main()
