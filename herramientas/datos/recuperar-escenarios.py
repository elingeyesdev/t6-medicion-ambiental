"""Recupera escenarios numéricos de EnviroSense sin ejecutar el servicio original."""
from pathlib import Path
import argparse
import hashlib
import json
import re

raiz = Path(__file__).resolve().parents[2]
fuente = raiz / 'referencias/envirosense/codigo/Backend/Src/modules/simulacion-zona/escenarios.data.js'
texto = fuente.read_text(encoding='utf-8')
catalogo = {}
for seccion in re.split(r"metricaClave:\s*'", texto)[1:]:
    metrica = seccion.split("'", 1)[0]
    escenarios = []
    patron = r"\bid:\s*'([^']+)'\s*,\s*nombre:\s*'([^']+)'(.*?)(?=\n\s*\},?\s*\n\s*(?:\{|\]))"
    for identificador, nombre, cuerpo in re.findall(patron, seccion, re.S):
        curva = re.search(r"curva:\s*'([^']+)'", cuerpo).group(1)
        niveles = {nivel:{'inicio':float(inicio),'fin':float(fin)} for nivel,inicio,fin in re.findall(r'(bajo|medio|alto):\s*\{\s*inicio:\s*(-?[\d.]+),\s*fin:\s*(-?[\d.]+)', cuerpo)}
        if len(niveles)!=3 or curva not in ('lineal','pico','exponencial'):
            raise ValueError('Escenario incompleto.')
        escenarios.append({'id':identificador,'nombre':nombre,'curva':curva,'niveles':niveles})
    if len(escenarios)!=2:
        raise ValueError(f'Faltan escenarios de {metrica}.')
    catalogo[metrica]=escenarios
if set(catalogo)!= {'temperatura','humedad','aqi','ica','ruido'}:
    raise ValueError('Faltan variables.')
resultado={'procedencia':{'archivo':fuente.relative_to(raiz).as_posix(),'sha256':hashlib.sha256(fuente.read_bytes()).hexdigest()}, 'metricas':catalogo}
salida=raiz/'compartido/simulaciones/escenarios-envirosense.json'
parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('--comprobar',action='store_true');opciones=parser.parse_args()
if opciones.comprobar:
    if json.loads(salida.read_text(encoding='utf-8'))!=resultado:
        raise SystemExit('Los escenarios no reproducen el original.')
else:
    salida.parent.mkdir(parents=True,exist_ok=True);salida.write_text(json.dumps(resultado,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
print('Recuperados diez escenarios, tres intensidades y cinco variables.')
