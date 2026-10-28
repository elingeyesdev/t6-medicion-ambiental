"""Valida todas las geometrías y la cobertura jerárquica; no emplea muestras de puntos."""

import argparse
import hashlib
import json
import sys
import zipfile
from datetime import datetime, timezone
from pathlib import Path

import numpy
import pyproj
import shapely
from pyproj import Transformer
from shapely.geometry import shape
from shapely.ops import transform, unary_union
from shapely.validation import explain_validity


RAIZ = Path(__file__).resolve().parents[1]
ARCHIVOS = {1: "departamentos.geojson", 2: "provincias.geojson", 3: "municipios.geojson"}
TOLERANCIA_M2 = 1.0  # Solo residuo numérico: NO representa precisión de los límites.


def leer(archivo):
    return json.loads(archivo.read_text(encoding="utf-8"))


def validar(zip_origen=None):
    catalogo = leer(RAIZ / "datos/catalogo.json")
    manifiesto = leer(RAIZ / "manifiesto.json")
    for relativo, esperado in manifiesto["archivos"].items():
        archivo = (RAIZ / relativo).resolve()
        if not archivo.is_relative_to(RAIZ) or hashlib.sha256(archivo.read_bytes()).hexdigest() != esperado:
            raise ValueError("Recurso alterado o ruta inválida: " + relativo)
    registros = {r["identificador"]: r for r in catalogo["territorios"]}
    if len(registros) != 72 or len(catalogo["territorios"]) != 72:
        raise ValueError("Registros duplicados o catálogo incompleto.")

    proyecto = Transformer.from_crs("EPSG:4326", "EPSG:6933", always_xy=True).transform
    geometrias = {}
    proyecciones = {}
    capas = {}
    for nivel in (1, 2, 3):
        capa = leer(RAIZ / "datos/limites" / ARCHIVOS[nivel])
        if capa["versionCatalogo"] != catalogo["version"]:
            raise ValueError("Se mezclaron versiones del catálogo y sus geometrías.")
        capas[nivel] = capa["features"]
        if len(capas[nivel]) != {1: 1, 2: 15, 3: 56}[nivel]:
            raise ValueError("La cantidad de geometrías por nivel no corresponde.")
        for feature in capas[nivel]:
            identificador = feature["id"]
            if identificador in geometrias or identificador not in registros:
                raise ValueError("Geometría repetida o sin registro: " + identificador)
            if feature["properties"]["padre"] != registros[identificador]["padre"]:
                raise ValueError("El padre del límite y del catálogo difieren.")
            geometria = shape(feature["geometry"])
            if geometria.is_empty or geometria.geom_type not in ("Polygon", "MultiPolygon") or not geometria.is_valid:
                raise ValueError(identificador + ": " + explain_validity(geometria))
            geometria_proyectada = transform(proyecto, geometria)
            if geometria_proyectada.area <= 0:
                raise ValueError("Área inválida: " + identificador)
            geometrias[identificador] = feature
            proyecciones[identificador] = geometria_proyectada
    if set(geometrias) != set(registros):
        raise ValueError("Faltan geometrías del catálogo.")

    coberturas = []
    for padre, registro in registros.items():
        if registro["tipo"] == "municipio":
            continue
        hijos = [r["identificador"] for r in registros.values() if r["padre"] == padre]
        if not hijos:
            raise ValueError("Territorio sin hijos: " + padre)
        union = unary_union([proyecciones[h] for h in hijos])
        diferencia = proyecciones[padre].symmetric_difference(union).area
        solape = max(0.0, sum(proyecciones[h].area for h in hijos) - union.area)
        fuera = union.difference(proyecciones[padre]).area
        if max(diferencia, solape, fuera) > TOLERANCIA_M2:
            raise ValueError(f"División territorial no consistente en {padre}: diferencia={diferencia}, solape={solape}, fuera={fuera} m²")
        coberturas.append({"padre": padre, "hijos": len(hijos),
            "diferenciaSimetricaM2": diferencia, "solapeHijosM2": solape, "fueraDelPadreM2": fuera})

    comparadas = 0
    if zip_origen:
        fuente = leer(RAIZ / "fuentes/fuentes.json")["geometrias"]
        if hashlib.sha256(zip_origen.read_bytes()).hexdigest() != fuente["sha256Zip"]:
            raise ValueError("El ZIP no es la fuente fijada.")
        with zipfile.ZipFile(zip_origen) as archivo_zip:
            for nivel in (1, 2, 3):
                capa = json.loads(archivo_zip.read(f"bol_admin{nivel}.geojson"))
                for feature in capa["features"]:
                    p = feature["properties"]
                    if p["adm1_pcode"] == "BO07":
                        identificador = p[f"adm{nivel}_pcode"]
                        if feature["geometry"] != geometrias[identificador]["geometry"]:
                            raise ValueError("Se modificaron coordenadas de origen: " + identificador)
                        comparadas += 1
        if comparadas != 72:
            raise ValueError("La comparación con el origen no cubre todos los registros.")

    return {
        "estado": "correcto", "versionCatalogo": catalogo["version"],
        "fechaEjecucionUTC": datetime.now(timezone.utc).isoformat(),
        "herramientas": {"python": sys.version.split()[0], "shapely": shapely.__version__,
            "geos": shapely.geos_version_string, "pyproj": pyproj.__version__, "numpy": numpy.__version__},
        "crsCoordenadas": "OGC:CRS84", "orden": ["longitud", "latitud"],
        "crsSoloParaCompararAreas": "EPSG:6933", "toleranciaNumericaM2": TOLERANCIA_M2,
        "geometriasValidas": len(geometrias), "comparadasCoordenadaACoordenadaConOrigen": comparadas,
        "conteos": {"departamento": 1, "provincia": 15, "municipio": 56},
        "bboxDepartamento": list(shape(geometrias["BO07"]["geometry"]).bounds),
        "coberturas": coberturas,
        "alcance": "Validación técnica exhaustiva del conjunto publicado; no es una certificación de deslinde legal o actualización temporal a 2026. Acuerdo del equipo pendiente.",
    }


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--zip", type=Path, help="Comparar además las 72 geometrías con el ZIP original fijado")
    parser.add_argument("--salida", type=Path, help="Guardar un informe JSON de la ejecución")
    args = parser.parse_args()
    try:
        informe = validar(args.zip)
        if args.salida:
            args.salida.parent.mkdir(parents=True, exist_ok=True)
            args.salida.write_text(json.dumps(informe, ensure_ascii=False, indent=2, allow_nan=False) + "\n", encoding="utf-8")
        print("CORRECTO: 72 geometrías válidas y 16 divisiones jerárquicas completas, sin solapes de área superiores a la tolerancia numérica.")
        if args.zip:
            print("CORRECTO: coordenadas de las 72 geometrías idénticas a las de la fuente fijada.")
    except (ValueError, KeyError, OSError) as error:
        print("ERROR:", error, file=sys.stderr)
        sys.exit(1)
