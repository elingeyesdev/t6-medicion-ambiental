"""Verifica evidencia HTTP congelada contra el contrato; no llama a NASA ni usa claves.

Requisito de verificación: jsonschema==4.25.1. No es dependencia del backend.
"""
import json
from pathlib import Path

from jsonschema import Draft202012Validator, FormatChecker

raiz = Path(__file__).resolve().parents[2]
contrato = json.loads((raiz / 'compartido/api/openapi.json').read_text(encoding='utf-8'))
for nombre, esquema in contrato['components']['schemas'].items():
    Draft202012Validator.check_schema(esquema)

casos = {'consulta_real.json': 'RespuestaFocos', 'catalogo_real.json': 'RespuestaCatalogo', 'limite_real.json': 'RespuestaLimite'}
for archivo, nombre in casos.items():
    contenido = json.loads((raiz / 'docs/evidencias/api01' / archivo).read_text(encoding='utf-8'))
    esquema = {'$ref': '#/components/schemas/' + nombre, 'components': contrato['components']}
    Draft202012Validator(esquema, format_checker=FormatChecker()).validate(contenido)
    print(archivo + ': esquema y formatos válidos')
print('Evidencia externa congelada; no equivale a una nueva consulta ni a una prueba Flutter.')
