"""Genera puntos candidatos interiores del catálogo territorial fijado; sin red ni datos climáticos."""
import argparse
import hashlib
import json
from pathlib import Path

import numpy
import shapely
from shapely.geometry import Point, shape

RAIZ = Path(__file__).resolve().parents[2]
TERRITORIOS = RAIZ / "compartido/territorios"
VERSION = "scz-muestras-sequias-0.1-propuesta"
PILOTO = [-63.1821, -17.7833]


def leer(ruta):
    return json.loads(ruta.read_text(encoding="utf-8"))


def seleccionar(geometria, piloto=None):
    if geometria.geom_type not in ("Polygon", "MultiPolygon") or geometria.is_empty or not geometria.is_valid:
        raise ValueError("Se requiere geometría poligonal válida, no vacía.")
    punto = Point(piloto) if piloto is not None else geometria.representative_point()
    coordenada = [round(punto.x, 6), round(punto.y, 6)]
    redondeado = Point(coordenada)
    # No aceptar borde, hueco o punto desplazado fuera al redondear para el cliente.
    if not geometria.contains(redondeado) or geometria.boundary.distance(redondeado) <= 1e-9:
        raise ValueError("El punto redondeado no es interior con separación numérica del borde.")
    return coordenada


def generar():
    if shapely.__version__ != "2.1.2" or numpy.__version__ != "2.3.4":
        raise ValueError("Reproducir con shapely==2.1.2 y numpy==2.3.4.")
    catalogo = leer(TERRITORIOS / "datos/catalogo.json")
    capa = leer(TERRITORIOS / "datos/limites/municipios.geojson")
    manifiesto = leer(TERRITORIOS / "manifiesto.json")
    fuentes = {}
    for relativo in ("datos/catalogo.json", "datos/limites/municipios.geojson"):
        digest = hashlib.sha256((TERRITORIOS / relativo).read_bytes()).hexdigest()
        if digest != manifiesto["archivos"][relativo]:
            raise ValueError("El recurso territorial no coincide con su manifiesto: " + relativo)
        fuentes[relativo] = digest
    if capa["versionCatalogo"] != catalogo["version"] or manifiesto["versionCatalogo"] != catalogo["version"]:
        raise ValueError("Versiones territoriales incompatibles.")
    municipios = {t["identificador"]: t for t in catalogo["territorios"] if t["tipo"] == "municipio"}
    geometrias = {f["id"]: shape(f["geometry"]) for f in capa["features"]}
    if len(municipios) != 56 or len(capa["features"]) != 56 or set(geometrias) != set(municipios):
        raise ValueError("El conjunto debe coincidir exactamente con las 56 unidades municipales del catálogo.")
    muestras = []
    for codigo in sorted(municipios):
        piloto = codigo == "BO070101"
        coordenada = seleccionar(geometrias[codigo], PILOTO if piloto else None)
        coincidencias = [otro for otro, g in geometrias.items() if g.covers(Point(coordenada))]
        if coincidencias != [codigo]:
            raise ValueError("El punto no pertenece exclusivamente al municipio: " + codigo)
        muestras.append({"identificador": "scz-piloto-01" if piloto else "scz-" + codigo.lower() + "-01",
                         "municipio": codigo, "provincia": municipios[codigo]["padre"],
                         "coordenadaSolicitada": coordenada,
                         "seleccion": "piloto_met35_conservado" if piloto else "point_on_surface_redondeado_6_decimales"})
    return {"version": VERSION, "estado": "propuesta_pendiente_revision",
            "versionCatalogoTerritorial": catalogo["version"], "crs": "OGC:CRS84",
            "ordenCoordenadas": ["longitud", "latitud"], "fuentesTerritorialesSha256": fuentes,
            "generacion": {"algoritmo": "punto_interior_y_piloto_conservado_v1", "shapely": shapely.__version__,
                           "geos": shapely.geos_version_string, "numpy": numpy.__version__},
            "alcance": "Un punto candidato por unidad municipal del catálogo; no es estación, promedio municipal ni cobertura climática.",
            "muestras": muestras}


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--salida", type=Path, required=True)
    args = parser.parse_args()
    documento = generar()
    args.salida.parent.mkdir(parents=True, exist_ok=True)
    args.salida.write_text(json.dumps(documento, ensure_ascii=False, indent=2, allow_nan=False) + "\n", encoding="utf-8", newline="\n")
    print(f"56 puntos interiores exclusivos; piloto conservado. {args.salida}")
