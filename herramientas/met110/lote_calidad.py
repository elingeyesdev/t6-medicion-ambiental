"""Comprueba las parejas de una captura, sin producir un análisis de agua."""
import argparse
from datetime import datetime, timezone
import hashlib
import json
from pathlib import Path
from urllib.request import Request, build_opener

from calidad import MAX_ASSET, TOKEN_URL, descargar_scl, leer_recorte, resumen_calidad
from escenas import SinRedirecciones, revisar

MAX_RED = 32 * 1024 * 1024
MAX_ESCENAS = 8


def reusar(recibos, escenas):
    """Los TIFF privados deben coincidir con un recibo anterior y con el catálogo."""
    cache = {}
    for receipt_path, folder in recibos:
        receipt = json.loads(receipt_path.read_text(encoding="utf-8"))
        records = receipt.get("escenas", [])
        batch = "cantidadEscenasUnicas" in receipt
        if (not isinstance(records, list) or (not batch and len(records) != 2)
                or (batch and (type(receipt["cantidadEscenasUnicas"]) is not int
                               or not 0 < len(records) <= MAX_ESCENAS
                               or receipt["cantidadEscenasUnicas"] != len(records)))):
            raise ValueError("Recibo anterior incompleto.")
        for index, record in enumerate(records):
            scene_id = record["id"]
            if scene_id not in escenas or scene_id in cache:
                raise ValueError("Escena de caché ajena o duplicada.")
            filename = hashlib.sha256(scene_id.encode()).hexdigest()[:24] if batch else ("A", "B")[index]
            source = folder / (filename + ".tif")
            if (type(record.get("bytes")) is not int or not 0 < record["bytes"] <= MAX_ASSET
                    or source.stat().st_size != record["bytes"]
                    or record["urlSinFirma"] != escenas[scene_id]["scl"]["href"]):
                raise ValueError("El recibo no coincide con el activo privado.")
            raw = source.read_bytes()
            if hashlib.sha256(raw).hexdigest() != record["sha256"]:
                raise ValueError("El TIFF privado fue alterado.")
            cache[scene_id] = raw
    return cache


def resumir(catalogo, recortes, grids, recibos):
    pairs = catalogo["parejasCandidatas"]
    ids = {p[k] for p in pairs for k in ("referencia", "comparacion")}
    if not pairs or set(recortes) != ids or set(grids) != ids or set(recibos) != ids:
        raise ValueError("Faltan escenas: no se anuncia una revisión completa.")
    results = []
    for pair in pairs:
        a, b = pair["referencia"], pair["comparacion"]
        if any(grids[a][k] != grids[b][k] for k in ("epsg", "ventanaColumnasFilas", "tamanoRecorte")):
            raise ValueError("Las celdas no coinciden.")
        results.append({**pair, "calidad": resumen_calidad(recortes[a], recortes[b])})
    results.sort(key=lambda x: (-x["calidad"]["fraccionComparableAmbas"],
                                x["separacionDias"], x["referencia"], x["comparacion"]))
    return {
        "tipo": "viabilidad_de_las_parejas_de_esta_captura_sin_analisis_de_agua",
        "comprobado_utc": datetime.now(timezone.utc).isoformat(),
        "metodoAprobado": False, "parejaAdoptada": None, "umbralMinimoAprobado": None,
        "bbox": catalogo["bbox"], "haySiguientePagina": catalogo["haySiguientePagina"],
        "cantidadParejasRevisadas": len(results), "cantidadEscenasUnicas": len(ids),
        "escenas": [recibos[k] for k in sorted(ids)], "parejasPorCalidadLocal": results,
        "pixelesAgua": None, "superficieInundadaHa": None,
        "firmasGuardadas": False, "clavesDeCuentaUtilizadas": False,
        "atribucion": "Contains modified Copernicus Sentinel data (2025); distribución Microsoft Planetary Computer.",
        "limite": "Ordena solo la visibilidad bajo la máscara candidata y la malla comprobada. "
                  "No adopta escenas, no demuestra inundación ni que otro período o producto sea inviable.",
    }


def comprobar(evidencia, privado, reutilizados=(), limite_red=MAX_RED, consultar=False, opener=None):
    if type(limite_red) is not int or not 0 < limite_red <= MAX_RED:
        raise ValueError("Presupuesto de descarga no válido.")
    catalogo = revisar(evidencia)
    ids = {p[k] for p in catalogo["parejasCandidatas"] for k in ("referencia", "comparacion")}
    if not ids or len(ids) > MAX_ESCENAS:
        raise ValueError("Cantidad de escenas fuera del límite.")
    scenes = {s["id"]: s for group in catalogo["escenas"].values() for s in group if s["id"] in ids}
    cache = reusar(reutilizados, scenes)
    missing = ids - set(cache)
    if missing and not consultar:
        raise ValueError("Faltan activos privados; la red necesita autorización expresa.")
    # Cada corrida conserva sus archivos y no sustituye evidencia de una anterior.
    privado.mkdir(parents=True, exist_ok=False)
    opener = opener or build_opener(SinRedirecciones())
    token = None
    if missing:
        with opener.open(Request(TOKEN_URL, headers={"Accept": "application/json"}), timeout=30) as response:
            body = response.read(65_537)
            if response.status != 200 or len(body) > 65_536:
                raise ValueError("Acceso temporal no disponible.")
            access = json.loads(body)
            token = access["token"]
            expires = datetime.fromisoformat(access["msft:expiry"].replace("Z", "+00:00"))
            if not isinstance(token, str) or not token or expires <= datetime.now(timezone.utc):
                raise ValueError("Acceso temporal no válido.")
    crops, grids, receipts = {}, {}, {}
    downloaded = reused = 0
    for scene_id in sorted(ids):
        scene = scenes[scene_id]
        cached = scene_id in cache
        if cached:
            raw = cache[scene_id]
            reused += len(raw)
        else:
            remaining = min(MAX_ASSET, limite_red - downloaded)
            if remaining <= 0:
                raise ValueError("Se agotó el presupuesto: no se anuncia revisión completa.")
            raw = descargar_scl(scene, token, opener, limite=remaining)
            downloaded += len(raw)
        pixels, grid = leer_recorte(raw, scene)
        # El identificador proviene del catálogo validado, pero el nombre local no lo usa.
        filename = hashlib.sha256(scene_id.encode()).hexdigest()[:24] + ".tif"
        (privado / filename).write_bytes(raw)
        crops[scene_id], grids[scene_id] = pixels, grid
        receipts[scene_id] = {"id": scene_id, "urlSinFirma": scene["scl"]["href"],
                              "httpOriginal": 206, "bytes": len(raw),
                              "sha256": hashlib.sha256(raw).hexdigest(),
                              "reutilizadoVerificado": cached, **grid}
    result = resumir(catalogo, crops, grids, receipts)
    result["descarga"] = {"bytesNuevos": downloaded, "bytesReutilizados": reused,
                           "limiteBytesNuevos": limite_red, "limitePorActivo": MAX_ASSET,
                           "limiteEscenasUnicas": MAX_ESCENAS}
    return result


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--evidencia", type=Path, required=True)
    parser.add_argument("--privado", type=Path, required=True)
    parser.add_argument("--reusar", nargs=2, action="append", default=[], metavar=("RECIBO", "TIFF_PRIVADOS"))
    parser.add_argument("--limite-mib", type=int, default=32)
    parser.add_argument("--consultar-mpc", action="store_true")
    args = parser.parse_args()
    try:
        result = comprobar(args.evidencia, args.privado,
                           [(Path(a), Path(b)) for a, b in args.reusar],
                           args.limite_mib * 1024 * 1024, args.consultar_mpc)
        target = args.privado / "revision.json"
        with target.open("x", encoding="utf-8") as output:
            output.write(json.dumps(result, ensure_ascii=False, indent=2) + "\n")
        print(json.dumps({"parejasRevisadas": result["cantidadParejasRevisadas"],
                          "mejorFraccionComparable": result["parejasPorCalidadLocal"][0]["calidad"]["fraccionComparableAmbas"],
                          "descarga": result["descarga"], "metodoAprobado": False}))
    except Exception:
        parser.exit(1, "La revisión no se completó; no se genera un resultado de agua ni se publican enlaces temporales.\n")


if __name__ == "__main__":
    main()
