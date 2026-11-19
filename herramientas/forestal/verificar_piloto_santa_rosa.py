"""Comprueba la ubicación y el efecto del umbral para el piloto de #147."""

import argparse
import hashlib
import json
from pathlib import Path

from PIL import Image
from pyproj import Geod
from shapely.geometry import box, shape

from contrastar_recorte import contrastar

ROOT = Path(__file__).resolve().parents[2]
LIMITES = ROOT / "compartido/territorios/datos/limites/municipios.geojson"


def verificar(caso):
    integridad = contrastar(caso)
    resultado = caso / "resultado"
    resumen = json.loads((resultado / "resumen.json").read_bytes())
    if (
        resumen["consulta"] != {"desde": 2001, "hasta": 2025, "extremos_incluidos": True}
        or resumen["criterio"] != {"base": 2000, "operador": ">=", "porcentaje": 30, "adoptado": False}
        or resumen["ventana_pixeles"] != {"columna": 25200, "fila": 28000, "ancho": 64, "alto": 64}
    ):
        raise ValueError("El recorte no coincide con el candidato a revisión.")

    limites = LIMITES.read_bytes()
    municipio = next(
        f for f in json.loads(limites)["features"]
        if f["properties"]["identificador"] == "BO070602"
    )
    recinto = shape(municipio["geometry"])
    if not recinto.is_valid or not recinto.covers(box(*resumen["bbox"])):
        raise ValueError("El límite local no contiene por completo la ventana.")

    bandas = {}
    for nombre in ("datamask", "treecover2000", "lossyear"):
        with Image.open(resultado / (nombre + ".png")) as banda:
            bandas[nombre] = banda.tobytes()

    oeste, _, _, norte = resumen["bbox"]
    paso = resumen["fuente"]["cuadricula"]["paso_grados"]
    geod = Geod(ellps="WGS84")
    conteo = 0
    diferencias = {}
    for indice, (mascara, copa, perdida) in enumerate(zip(*bandas.values(), strict=True)):
        if mascara != 1 or copa != 30:
            continue
        conteo += 1
        if perdida:
            fila, columna = divmod(indice, 64)
            x, y = oeste + columna * paso, norte - fila * paso
            area, _ = geod.polygon_area_perimeter([x, x + paso, x + paso, x], [y, y, y - paso, y - paso])
            diferencias[2000 + perdida] = diferencias.get(2000 + perdida, 0) + abs(area) / 10000

    total = resumen["total_periodo_ha"]
    diferencia = sum(diferencias.values())
    return {
        "estado": "candidato_para_revision",
        "version": resumen["fuente"]["version"],
        "tesela": resumen["fuente"]["tesela"],
        "licencia": resumen["fuente"]["licencia"],
        "municipio_referencia": "BO070602",
        "nombre_municipio": municipio["properties"]["nombre"],
        "limites_sha256": hashlib.sha256(limites).hexdigest(),
        "bbox": resumen["bbox"],
        "consulta": resumen["consulta"],
        "criterio": resumen["criterio"],
        "conteos_pixeles": resumen["conteos_pixeles"],
        "celdas_con_copa_exactamente_30": conteo,
        "diferencia_por_anio_ha": {anio: round(area, 8) for anio, area in diferencias.items()},
        "total_mayor_o_igual_a_30_ha": total,
        "total_mayor_a_30_ha": round(total - diferencia, 8),
        "diferencia_total_ha": round(diferencia, 8),
        "verificacion_raster_y_capa": integridad,
    }


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("caso", type=Path)
    args = parser.parse_args()
    print(json.dumps(verificar(args.caso), ensure_ascii=False, indent=2))
