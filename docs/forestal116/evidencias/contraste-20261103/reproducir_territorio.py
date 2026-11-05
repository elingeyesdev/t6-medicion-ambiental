"""Contrasta dos contornos publicados; conserva la geometría GADM fuera de Git."""
import argparse
from hashlib import sha256
import json
import math
from pathlib import Path
from pyproj import Geod

ROOT = Path(__file__).resolve().parents[4]
GEOD = Geod(ellps="WGS84")


def area_ring(ring):
    if len(ring) < 4 or ring[0] != ring[-1]:
        raise ValueError("Anillo incompleto.")
    if any(len(point) < 2 or not all(math.isfinite(v) for v in point[:2])
           or not -180 <= point[0] <= 180 or not -90 <= point[1] <= 90 for point in ring):
        raise ValueError("Coordenadas incompatibles.")
    return abs(GEOD.polygon_area_perimeter([p[0] for p in ring], [p[1] for p in ring])[0])


def describe(geometry):
    if geometry["type"] not in ("Polygon", "MultiPolygon"):
        raise ValueError("Se necesita geometría poligonal.")
    polygons = [geometry["coordinates"]] if geometry["type"] == "Polygon" else geometry["coordinates"]
    points = [p for polygon in polygons for ring in polygon for p in ring]
    areas = [area_ring(polygon[0]) - sum(area_ring(r) for r in polygon[1:]) for polygon in polygons]
    if any(area <= 0 for area in areas):
        raise ValueError("Área incompatible con los anillos.")
    return {
        "tipo": geometry["type"], "componentes": len(polygons), "verticesConCierre": len(points),
        "bbox": [min(p[0] for p in points), min(p[1] for p in points),
                 max(p[0] for p in points), max(p[1] for p in points)],
        "areaGeodesicaKm2": sum(areas) / 1000000,
    }


def compare(gadm_path):
    local_path = ROOT / "compartido/territorios/datos/limites/departamentos.geojson"
    local_raw, gadm_raw = local_path.read_bytes(), gadm_path.read_bytes()
    receipt_path = Path(__file__).resolve().parent / "gadm-recibo.json"
    receipt = json.loads(receipt_path.read_text(encoding="utf-8"))
    if sha256(gadm_raw).hexdigest() != receipt["sha256"]:
        raise ValueError("La geometría GADM no coincide con el recibo de esta captura.")
    local = next(f for f in json.loads(local_raw)["features"] if f["properties"]["identificador"] == "BO07")
    provider = next(f for f in json.loads(gadm_raw)["features"] if f["properties"]["GID_1"] == "BOL.8_1")
    a, b = describe(local["geometry"]), describe(provider["geometry"])
    return {
        "metodo": "Geod WGS84; área exterior menos huecos, segmentos geodésicos entre vértices publicados",
        "pyprojVersion": __import__("pyproj").__version__,
        "catalogo": {"id": "BO07", "nombre": local["properties"]["nombre"], "archivoSha256": sha256(local_raw).hexdigest(), **a},
        "gadm": {"id": "BOL.8_1", "nombre": provider["properties"]["NAME_1"], "archivoSha256": sha256(gadm_raw).hexdigest(), **b},
        "diferenciaAreaTotalKm2": abs(a["areaGeodesicaKm2"] - b["areaGeodesicaKm2"]),
        "geometriaIdentica": local["geometry"] == provider["geometry"],
        "equivalenciaAdoptada": False,
        "alcance": "Contraste de nombre, extensión y áreas de los archivos. Falta intersección, examen topológico y revisión de la equivalencia territorial.",
    }


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--gadm", type=Path, required=True)
    print(json.dumps(compare(parser.parse_args().gadm), ensure_ascii=False, indent=2))
