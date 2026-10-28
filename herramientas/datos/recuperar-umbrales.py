"""Extrae los 29 umbrales del seed de EnviroSense sin ejecutar SQL."""
from pathlib import Path
import argparse, hashlib, json, re
raiz=Path(__file__).resolve().parents[2]
fuente=raiz/'referencias/envirosense/codigo/database/seeds/02_umbrales.sql'
texto=fuente.read_text(encoding='utf-8')
patron=r"SELECT m\.id,\s*(\d+),\s*'([^']+)',\s*(-?[\d.]+),\s*(-?[\d.]+),\s*'(#[a-fA-F0-9]{6})',\s*'([^']+)'\s+FROM metricas m WHERE m\.clave = '([^']+)'"
metricas={}
for nivel,nombre,minimo,maximo,color,severidad,metrica in re.findall(patron,texto):
    metricas.setdefault(metrica,[]).append({'nivel':int(nivel),'nombre':nombre,'minimo':float(minimo),'maximo':float(maximo),'color':color,'severidad':severidad})
assert {k:len(v) for k,v in metricas.items()}=={'aqi':6,'temperatura':7,'ica':5,'ruido':6,'humedad':5}
resultado={'procedencia':{'archivo':fuente.relative_to(raiz).as_posix(),'sha256':hashlib.sha256(fuente.read_bytes()).hexdigest()},'nota':'Reglas recuperadas del software original; no constituyen validación normativa ni recomendación sanitaria.','metricas':metricas}
salida=raiz/'compartido/alertas/umbrales-envirosense.json'
p=argparse.ArgumentParser(description=__doc__);p.add_argument('--comprobar',action='store_true');op=p.parse_args()
if op.comprobar:
    assert json.loads(salida.read_text(encoding='utf-8'))==resultado, 'El catálogo no reproduce el seed.'
else:
    salida.parent.mkdir(parents=True,exist_ok=True);salida.write_text(json.dumps(resultado,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
print('29 umbrales, cinco variables y procedencia comprobada.')
