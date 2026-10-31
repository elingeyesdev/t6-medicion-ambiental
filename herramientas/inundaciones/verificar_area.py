"""Contrasta solo el área técnica contra las geometrías del catálogo, sin datos satelitales."""
import hashlib
import json
from pathlib import Path

from shapely.geometry import box, shape

raiz = Path(__file__).resolve().parents[2]
config = json.loads((raiz / "compartido/inundaciones/exploracion.json").read_text(encoding="utf-8"))
archivo = raiz / "compartido/territorios/datos/limites/municipios.geojson"
original = archivo.read_bytes()
area = box(*config["bbox"])
intersecciones = sorted(f["properties"]["identificador"] for f in json.loads(original)["features"] if shape(f["geometry"]).intersection(area).area > 0)
if intersecciones != sorted(config["municipiosQueIntersecanSegunCatalogo"]):
    raise ValueError("La declaración territorial del área cambió")
resultado = {"bbox": config["bbox"], "crs": config["crsBbox"], "interseccionesCatalogo": intersecciones, "sha256Geometrias": hashlib.sha256(original).hexdigest(), "alcance": "Intersección geométrica, no certificación legal ni superficie de inundación"}
evidencia = raiz / "docs/evidencias/ana01/area.json"
if evidencia.is_file() and json.loads(evidencia.read_text(encoding="utf-8")) != resultado:
    raise ValueError("La evidencia territorial no coincide con la geometría y parámetros actuales")
print(json.dumps(resultado, indent=2))
