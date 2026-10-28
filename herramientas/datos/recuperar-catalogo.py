"""Recupera constantes públicas del referente sin ejecutar JavaScript ni SQL."""
import argparse
import hashlib
import json
from pathlib import Path
import re

RAIZ = Path(__file__).resolve().parents[2]
BASE = RAIZ / "referencias/envirosense/codigo"
LOCALIDADES = BASE / "Backend/Src/modules/simulacion/localidades.data.js"
LIMITES = BASE / "Backend/Src/constants/metricas.js"
UNIDADES = BASE / "database/esquema_envirosense.sql"
texto = LOCALIDADES.read_text(encoding="utf-8")
paises = {"BOLIVIA": "BO", "ARGENTINA": "AR", "BRASIL": "BR", "CHILE": "CL", "COLOMBIA": "CO", "ECUADOR": "EC", "PERÚ": "PE", "PARAGUAY": "PY", "URUGUAY": "UY", "VENEZUELA": "VE"}
patron = re.compile(r"\{\s*id: '([^']+)',\s*name: '([^']+)',\s*latitude: ([-\d.]+), longitude: ([-\d.]+),\s*ranges: \{([^{}]+)\}\s*\}")
puntos = []
for m in patron.finditer(texto):
    codigo, nombre, lat, lon, rangos = m.groups()
    prefijo = texto[:m.start()]
    pais = max(((prefijo.rfind(nombre_pais), clave) for nombre_pais, clave in paises.items()))[1]
    pais = {"georgetown": "GY", "paramaribo": "SR", "cayenne": "GF"}.get(codigo, pais)
    puntos.append({"codigo": codigo, "nombre": nombre, "pais": pais, "latitud": float(lat), "longitud": float(lon),
                   "rangosSimulacion": {clave: [float(a), float(b)] for clave, a, b in re.findall(r"(\w+): \[([-\d.]+), ([-\d.]+)\]", rangos)}})
if not puntos or len(puntos) != len(re.findall(r"\bid: '", texto)) or len({p['codigo'] for p in puntos}) != len(puntos):
    raise ValueError("No se pudo recuperar el catálogo completo sin ambigüedad.")
etiquetas = {"temperatura": ("Temperatura", "°C"), "humedad": ("Humedad", "%"), "aqi": ("Calidad del aire", "AQI"), "ica": ("Índice de agua", "ICA"), "ruido": ("Ruido", "dB")}
metricas = {clave: {"nombre": etiquetas[clave][0], "unidad": etiquetas[clave][1], "minimo": float(a), "maximo": float(b)}
            for clave, a, b in re.findall(r"(\w+):\s*\{\s*min:\s*([-\d.]+),\s*max:\s*([-\d.]+)\s*\}", LIMITES.read_text(encoding="utf-8"))}
if set(metricas) != set(etiquetas):
    raise ValueError("Faltan límites de las cinco variables del referente.")
resultado = {"version": "envirosense-2026-10-28", "fuentes": [{"archivo": str(p.relative_to(RAIZ)).replace('\\', '/'), "sha256": hashlib.sha256(p.read_bytes()).hexdigest()} for p in [LOCALIDADES, LIMITES, UNIDADES]],
             "nota": "Son puntos de referencia del sistema original, no estaciones físicas ni lecturas observadas. Los rangos solo se usan en simulaciones.", "metricas": metricas, "puntos": puntos}
salida = RAIZ / "compartido/sensores/catalogo-envirosense.json"
argumentos = argparse.ArgumentParser(description=__doc__)
argumentos.add_argument("--comprobar", action="store_true", help="Verifica la reproducción sin cambiar archivos.")
opciones = argumentos.parse_args()
if opciones.comprobar:
    if not salida.is_file() or json.loads(salida.read_text(encoding="utf-8")) != resultado:
        raise SystemExit("El catálogo guardado no coincide con las constantes originales.")
else:
    salida.parent.mkdir(parents=True, exist_ok=True)
    salida.write_text(json.dumps(resultado, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
print(f"Recuperados {len(puntos)} puntos y {len(metricas)} variables; no se importaron lecturas ni cuentas.")
