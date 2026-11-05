"""Disponibilidad declarada: no calcula fechas ni estima esfuerzo pendiente."""
import argparse
import json
import math
from pathlib import Path

def capacidad(datos):
    personas, nombres = [], set()
    for p in datos['personas']:
        nombre = p['nombre']
        if not isinstance(nombre, str) or not nombre or nombre in nombres:
            raise ValueError('Nombre ausente o repetido')
        nombres.add(nombre)
        h = p['horasPorDiaDeclaradas']
        dias = p['calendarioConfirmado']['diasSemanaISO']
        if not isinstance(dias, list) or not dias or any(type(d) is not int or not 1 <= d <= 7 for d in dias) or len(set(dias)) != len(dias):
            raise ValueError('Dias invalidos o repetidos')
        a,b = h['minimo'],h['maximo']
        if any(type(v) not in [int,float] or not math.isfinite(v) for v in [a,b]) or not 0 < a <= b <= 24:
            raise ValueError('Horas invalidas')
        personas.append({'nombre':nombre,'diasSemanaISO':sorted(dias),'horasDiarias':{'minimo':a,'maximo':b},'horasSemanaCompleta':{'minimo':a*len(dias),'maximo':b*len(dias)},'desdeConfirmado':p['desde'],'ausenciasConfirmadas':p['ausencias']})
    if not personas: raise ValueError('Faltan personas')
    return {'tipo':'capacidad_declarada_no_pronostico','unidad':'horas-persona','personas':personas,'totalSemanaCompleta':{'minimo':sum(p['horasSemanaCompleta']['minimo'] for p in personas),'maximo':sum(p['horasSemanaCompleta']['maximo'] for p in personas)},'condicion':'Semana completa con todos los dias declarados disponibles; sin descontar ausencias desconocidas. No implica trabajo simultaneo ni recursos intercambiables.','duracionCalendarioHoras':None,'fechaEntrega':None,'esfuerzoRestante':None}

if __name__ == '__main__':
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--datos',type=Path,default=Path('docs/planificacion109/datos-por-confirmar.json'))
    parser.add_argument('--salida',type=Path,required=True)
    args=parser.parse_args()
    informe=capacidad(json.loads(args.datos.read_text(encoding='utf-8')))
    with args.salida.open('x',encoding='utf-8') as f:
        json.dump(informe,f,ensure_ascii=False,indent=2,allow_nan=False); f.write('\n')
