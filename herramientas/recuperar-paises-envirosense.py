"""Recupera solo el catálogo de países del origen; no importa mediciones ni bases."""
import hashlib
import json
import re
from pathlib import Path

raiz = Path(__file__).resolve().parents[1]
origen = raiz / 'referencias/envirosense/codigo/database/seeds'
filas = []
fuentes = []
for nombre in ['01_geografia.sql', '01_sudamerica.sql']:
    archivo = origen / nombre
    texto = archivo.read_text(encoding='utf-8')
    bloque = re.search(r'INSERT INTO paises \(([^)]+)\) VALUES(.*?)ON CONFLICT', texto, re.S)
    columnas = [c.strip() for c in bloque[1].split(',')]
    for valores in re.findall(r"\('([^']+)',\s*'([^']+)'\)", bloque[2]):
        fila = dict(zip(columnas, valores))
        filas.append({'codigo': fila['codigo'], 'nombre': fila['nombre'], 'tipo': 'territorio' if fila['codigo'] == 'GF' else 'pais'})
    fuentes.append({'archivo': archivo.relative_to(raiz).as_posix(), 'sha256': hashlib.sha256(archivo.read_bytes()).hexdigest()})
assert len({f['codigo'] for f in filas}) == len(filas) == 13
salida = raiz / 'compartido/geografia/paises-envirosense.json'
salida.parent.mkdir(parents=True, exist_ok=True)
salida.write_text(json.dumps({'version': '1.0', 'fuentes': fuentes, 'datos': sorted(filas, key=lambda f: f['codigo'])}, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
print('Catálogo recuperado: 12 países y Guyana Francesa. No se importaron datos ambientales.')
