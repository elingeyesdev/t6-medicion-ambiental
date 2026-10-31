"""Acceso SCL acotado y calidad del recorte. No calcula cambios de agua."""
import argparse
from datetime import datetime, timezone
import hashlib
from io import BytesIO
import json
import math
from pathlib import Path
import re
from urllib.parse import urlsplit
from urllib.request import Request, build_opener
import numpy as np
from PIL import Image
from pyproj import Transformer

from escenas import SinRedirecciones, config, revisar

MAX_ASSET = 8 * 1024 * 1024
TOKEN_URL = "https://planetarycomputer.microsoft.com/api/sas/v1/token/sentinel2l2a01/sentinel2-l2"


def clases_validas(pixels):
    if pixels.ndim != 2 or pixels.dtype != np.uint8 or np.any(pixels > 11):
        raise ValueError("SCL no válida.")
    return np.isin(pixels, config()["politicaSclPropuesta"]["comparables"])


def resumen_calidad(a, b):
    if a.shape != b.shape or a.size == 0:
        raise ValueError("Los recortes no comparten malla.")
    va, vb = clases_validas(a), clases_validas(b)
    return {
        "pixelesRecorte": int(a.size),
        "fraccionComparableA": float(va.mean()), "fraccionComparableB": float(vb.mean()),
        "fraccionComparableAmbas": float((va & vb).mean()),
        "politicaComparables": [4, 5, 6], "politicaAdoptada": False,
        "umbralMinimoAprobado": None, "pixelesAgua": None, "superficieInundadaHa": None,
    }


def leer_recorte(raw, scene):
    with Image.open(BytesIO(raw)) as image:
        asset = scene["scl"]
        if image.format != "TIFF" or image.mode != "L" or list(reversed(image.size)) != asset["proj:shape"]:
            raise ValueError("No coincide el raster SCL.")
        transform = asset["proj:transform"]
        if len(transform) != 6 or transform[0] != 20 or transform[1] != 0 or transform[3] != 0 or transform[4] != -20:
            raise ValueError("Malla no admitida.")
        tags = image.tag_v2
        if tuple(tags.get(33550, ()))[:2] != (20.0, 20.0):
            raise ValueError("Resolución distinta.")
        tie = tuple(tags.get(33922, ()))
        if len(tie) != 6 or tie[:2] != (0.0, 0.0) or tie[3:5] != (transform[2], transform[5]):
            raise ValueError("Origen distinto.")
        geokeys = tuple(tags.get(34735, ()))
        if len(geokeys) < 4 or len(geokeys) != 4 + geokeys[3] * 4:
            raise ValueError("Georreferencia ausente.")
        keys = {geokeys[i]: geokeys[i + 1:i + 4] for i in range(4, len(geokeys), 4)}
        if keys.get(3072) != (0, 1, scene["epsg"]):
            raise ValueError("CRS distinto del catálogo.")
        projector = Transformer.from_crs("OGC:CRS84", scene["epsg"], always_xy=True)
        west, south, east, north = projector.transform_bounds(*config()["bbox"], densify_pts=21)
        if not all(math.isfinite(v) for v in (west, south, east, north)):
            raise ValueError("Límites no finitos.")
        x0, y0 = transform[2], transform[5]
        box = (math.floor((west - x0) / 20), math.floor((y0 - north) / 20),
               math.ceil((east - x0) / 20), math.ceil((y0 - south) / 20))
        if not (0 <= box[0] < box[2] <= image.width and 0 <= box[1] < box[3] <= image.height):
            raise ValueError("El recorte sale del raster.")
        if (box[2] - box[0]) * (box[3] - box[1]) > 500_000:
            raise ValueError("Recorte superior al límite.")
        subset = np.array(image.crop(box), dtype=np.uint8)
        clases_validas(subset)
        return subset, {
            "epsg": scene["epsg"], "tamanoRaster": list(image.size),
            "ventanaColumnasFilas": list(box), "resolucionMetros": [20, 20],
            "tamanoRecorte": [subset.shape[1], subset.shape[0]],
            "sha256PixelesRecorte": hashlib.sha256(subset.tobytes()).hexdigest(),
            "muestreo": "celdas originales, ventana exterior; sin remuestreo",
            "georreferenciaComprobada": True,
        }


def descargar_scl(scene, token, opener):
    unsigned = scene["scl"]["href"]
    parsed = urlsplit(unsigned)
    if (parsed.scheme != "https" or parsed.netloc != "sentinel2l2a01.blob.core.windows.net"
            or not parsed.path.startswith("/sentinel2-l2/") or not parsed.path.endswith("_SCL_20m.tif")
            or parsed.query or parsed.fragment):
        raise ValueError("Activo fuera del acceso permitido.")
    request = Request(unsigned + "?" + token, headers={"Range": f"bytes=0-{MAX_ASSET - 1}"})
    with opener.open(request, timeout=40) as response:
        content_range = response.headers.get("Content-Range", "")
        match = re.fullmatch(r"bytes 0-(\d+)/(\d+)", content_range)
        if response.status != 206 or not match:
            raise ValueError("Se requiere lectura HTTP acotada por rango.")
        total, end = int(match[2]), int(match[1])
        if not 0 < total <= MAX_ASSET or end != total - 1:
            raise ValueError("El activo supera el máximo permitido.")
        raw = response.read(MAX_ASSET + 1)
        if len(raw) != total or raw[:4] not in (b"II*\x00", b"MM\x00*"):
            raise ValueError("TIFF incompleto o incompatible.")
    return raw


def comprobar(folder, private, pareja=1):
    report = revisar(folder)
    if type(pareja) is not int or pareja not in (1, 2) or len(report["parejasCandidatas"]) < pareja:
        raise ValueError("No hay pareja técnica candidata.")
    private.mkdir(parents=True, exist_ok=False)
    pair = report["parejasCandidatas"][pareja - 1]
    first = next(x for x in report["escenas"]["A"] if x["id"] == pair["referencia"])
    second = next(x for x in report["escenas"]["B"] if x["id"] == pair["comparacion"])
    opener = build_opener(SinRedirecciones())
    with opener.open(Request(TOKEN_URL, headers={"Accept": "application/json"}), timeout=30) as response:
        body = response.read(65_537)
        if response.status != 200 or len(body) > 65_536:
            raise ValueError("No se obtuvo un acceso temporal válido.")
        access = json.loads(body)
        token = access["token"]
        expiry = datetime.fromisoformat(access["msft:expiry"].replace("Z", "+00:00"))
        if not isinstance(token, str) or not token or expiry <= datetime.now(timezone.utc):
            raise ValueError("Acceso temporal vencido.")
    output, crops, geometry = [], [], []
    for label, scene in zip(("A", "B"), (first, second)):
        raw = descargar_scl(scene, token, opener)
        # Solo almacenamiento privado de la comprobación; nunca guardar la URL firmada.
        (private / (label + ".tif")).write_bytes(raw)
        pixels, grid = leer_recorte(raw, scene)
        crops.append(pixels)
        geometry.append({k: grid[k] for k in ("epsg", "ventanaColumnasFilas", "tamanoRecorte")})
        output.append({
            "id": scene["id"], "urlSinFirma": scene["scl"]["href"],
            "http": 206, "bytes": len(raw), "sha256": hashlib.sha256(raw).hexdigest(), **grid,
        })
    if geometry[0] != geometry[1]:
        raise ValueError("Las ventanas de calidad no coinciden.")
    return {
        "comprobado_utc": datetime.now(timezone.utc).isoformat(),
        "tipo": "viabilidad_tecnica_sin_analisis_de_agua", "metodoAprobado": False,
        "escenas": output, "calidad": resumen_calidad(*crops),
        "firmasGuardadas": False, "clavesDeCuentaUtilizadas": False,
        "atribucion": "Contains modified Copernicus Sentinel data (2025); distribución Microsoft Planetary Computer.",
        "limite": "La calidad evalúa la política candidata de clases; no valida exactitud de SCL ni una inundación.",
    }


def main():
    p = argparse.ArgumentParser(description=__doc__)
    p.add_argument("--evidencia", type=Path, required=True)
    p.add_argument("--privado", type=Path, required=True)
    p.add_argument("--salida", type=Path, required=True)
    p.add_argument("--consultar-mpc", action="store_true")
    p.add_argument("--pareja", type=int, choices=[1, 2], default=1)
    args = p.parse_args()
    if not args.consultar_mpc:
        p.error("El acceso requiere --consultar-mpc.")
    try:
        result = comprobar(args.evidencia, args.privado, args.pareja)
        args.salida.parent.mkdir(parents=True, exist_ok=True)
        args.salida.write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
        print(json.dumps(result["calidad"]))
    except Exception:
        # Un error HTTP puede contener una firma: no imprimir URL, excepción ni traceback.
        p.exit(1, "Acceso/recorte no verificado. No se guardan firmas ni se infiere ausencia de agua.\n")


if __name__ == "__main__":
    main()
