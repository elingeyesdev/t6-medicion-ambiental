"""Contrasta Hansen GFC en Santa Rosa con clases anuales de Sentinel-2 IO."""

import argparse
import hashlib
import json
import math
from collections import Counter
from concurrent.futures import ThreadPoolExecutor
from pathlib import Path
from urllib.request import Request, urlopen

import numpy as np
import rasterio
from PIL import Image
from rasterio.warp import Resampling, reproject, transform_bounds
from rasterio.windows import Window, from_bounds

from contrastar_recorte import contrastar

FUENTE = "https://io-10m-annual-lulc.s3.us-west-2.amazonaws.com/"
ANIOS = range(2017, 2025)
CLASES_VALIDAS = (1, 2, 4, 5, 7, 8, 9, 11)
ARBOLADO = 2


def leer_anio(anio, bbox, malla):
    url = f"{FUENTE}20K_{anio}.tif"
    with urlopen(Request(url, method="HEAD"), timeout=15) as resp:
        etag = resp.headers["ETag"]
    with rasterio.Env(GDAL_DISABLE_READDIR_ON_OPEN="EMPTY_DIR", GDAL_HTTP_TIMEOUT="15"):
        with rasterio.open("/vsicurl/" + url) as ds:
            if ds.crs.to_epsg() != 32720 or ds.res != (10, 10) or ds.count != 1:
                raise ValueError(f"Malla de referencia incompatible en {anio}.")
            bounds = transform_bounds("EPSG:4326", ds.crs, *bbox)
            w = from_bounds(*bounds, transform=ds.transform)
            x, y = math.floor(w.col_off), math.floor(w.row_off)
            exacto = Window(x, y, math.ceil(w.col_off + w.width) - x,
                            math.ceil(w.row_off + w.height) - y)
            ventana = ds.read(1, window=exacto)
            salida = np.zeros((64, 64), dtype=np.uint8)
            reproject(ventana, salida, src_transform=ds.window_transform(exacto), src_crs=ds.crs,
                      src_nodata=ds.nodata, dst_transform=malla, dst_crs="EPSG:4326",
                      dst_nodata=0, resampling=Resampling.nearest)
    with urlopen(Request(url, method="HEAD"), timeout=15) as resp:
        if etag is None or resp.headers["ETag"] != etag:
            raise ValueError(f"La fuente cambió durante la lectura de {anio}.")
    if not np.isin(salida, (*CLASES_VALIDAS, 0, 10, 255)).all():
        raise ValueError(f"Clases inesperadas en {anio}.")
    return anio, salida, {"fuente": url, "etag": etag, "sha256_clases_64x64": hashlib.sha256(salida.tobytes()).hexdigest()}


def banda(base, nombre):
    with Image.open(base / f"{nombre}.png") as img:
        if img.size != (64, 64) or img.mode != "L":
            raise ValueError("El recorte fuente debe ser de 64 × 64.")
        return np.asarray(img)


def categoria(anterior, posterior, siguiente):
    if any(v not in CLASES_VALIDAS for v in (anterior, posterior, siguiente)):
        return "referencia_incompleta"
    if anterior != ARBOLADO:
        return "clase_anterior_no_arbolado"
    if posterior != ARBOLADO and siguiente != ARBOLADO:
        return "transicion_persistente"
    if posterior == ARBOLADO and siguiente == ARBOLADO:
        return "arbolado_en_ambos_posteriores"
    return "posteriores_mixtos"


def verificar(caso):
    integridad = contrastar(caso)
    base = caso / "resultado"
    resumen = json.loads((base / "resumen.json").read_bytes())
    bbox = resumen["bbox"]
    if (bbox != [-63.7, -17.016, -63.684, -17.0] or
            resumen["criterio"]["porcentaje"] != 30 or
            resumen["consulta"] != {"desde": 2001, "hasta": 2025, "extremos_incluidos": True}):
        raise ValueError("El caso no es el recorte acordado de Santa Rosa.")
    malla = rasterio.transform.from_bounds(*bbox, 64, 64)
    with ThreadPoolExecutor(max_workers=4) as ejecucion:
        rasters = list(ejecucion.map(lambda anio: leer_anio(anio, bbox, malla), ANIOS))
    clases = {anio: capa for anio, capa, _ in rasters}
    gfc = banda(base, "lossyear")
    elegibles = (banda(base, "datamask") == 1) & (banda(base, "treecover2000") >= 30)
    por_anio = {}
    for anio in range(2018, 2023):
        celdas = np.argwhere(elegibles & (gfc == anio - 2000))
        conteos = Counter(categoria(*(clases[a][y, x] for a in (anio-1, anio+1, anio+2))) for y, x in celdas)
        por_anio[anio] = {"celdas_gfc": len(celdas), "conteo": dict(sorted(conteos.items()))}
    sensibilidad = []
    for fila, columna in np.argwhere(elegibles & (banda(base, "treecover2000") == 30) & (gfc != 0)):
        sensibilidad.append({"celda": int(fila*64 + columna), "anio_gfc": 2000 + int(gfc[fila, columna]),
                             "clases_por_anio": {a: int(capa[fila, columna]) for a, capa in clases.items()}})
    return {
        "estado": "contraste_clasificaciones",
        "fuente": "Impact Observatory, Microsoft y Esri. Sentinel-2, 10m Annual Land Use Land Cover (9-class), CC BY 4.0.",
        "malla_comun": "Centros de las 4096 celdas Hansen en EPSG:4326, vecino más cercano desde 10 m UTM 20S.",
        "definicion": "Para cada pérdida GFC en t, clase Trees en t-1 y clase sin Trees tanto en t+1 como t+2.",
        "bbox": bbox,
        "huella_gfc": integridad["captura_sha256"],
        "total_celdas_perdida_gfc": int((elegibles & (gfc != 0)).sum()),
        "celdas_periodo_con_contraste": sum(f["celdas_gfc"] for f in por_anio.values()),
        "cobertura_clases_por_anio": {a: int(np.isin(m, CLASES_VALIDAS).sum()) for a, m in clases.items()},
        "rasters": {a: meta for a, _, meta in rasters},
        "resultados_por_anio": por_anio,
        "celdas_sensibles": sensibilidad,
    }


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("caso", type=Path)
    parser.add_argument("--salida", required=True, type=Path)
    args = parser.parse_args()
    resultado = verificar(args.caso)
    with args.salida.open("x", encoding="utf-8", newline="\n") as archivo:
        json.dump(resultado, archivo, ensure_ascii=False, indent=2, allow_nan=False)
        archivo.write("\n")
