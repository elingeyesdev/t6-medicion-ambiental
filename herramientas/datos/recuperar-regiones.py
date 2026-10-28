"""Extrae geometrías publicadas por EnviroSense sin ejecutar SQL ni descargar datos."""
import argparse
import hashlib
import json
from pathlib import Path
import re

RAIZ = Path(__file__).resolve().parents[2]
ORIGEN = RAIZ / 'referencias/envirosense/codigo/database/seeds/geojson'
SALIDA = RAIZ / 'compartido/geografia/regiones'
PATRON = re.compile(r"SELECT p\.id, '((?:''|[^'])*)', '([^']+)', '([^']+)'::jsonb\s+FROM paises p\s+WHERE p\.codigo = '([A-Z]{2})'", re.S)


def extraer():
    archivos = {}
    indice = []
    for ruta in sorted(ORIGEN.glob('*_geojson.sql')):
        texto = ruta.read_text(encoding='utf-8-sig')
        filas = list(PATRON.finditer(texto))
        if not filas or len(filas) != texto.count('INSERT INTO regiones'):
            raise ValueError(f'No se pudo leer completo {ruta.name}.')
        fuente = re.search(r'^-- Fuente:\s*(.+)$', texto, re.M).group(1).strip()
        licencia = re.search(r'^-- Licencia:\s*(.+)$', texto, re.M)
        origen = {'archivo': ruta.relative_to(RAIZ).as_posix(), 'sha256': hashlib.sha256(ruta.read_bytes()).hexdigest(),
                  'fuente': fuente, 'licenciaDeclarada': licencia.group(1).strip() if licencia else 'No declarada en el seed; se conserva la procedencia original.'}
        for fila in filas:
            nombre, nivel, raw, pais = fila.groups()
            nombre = nombre.replace("''", "'")
            geometria = json.loads(raw)
            if geometria.get('type') not in ('Polygon', 'MultiPolygon'):
                raise ValueError('La región no contiene un polígono.')
            poligonos = [geometria['coordinates']] if geometria['type'] == 'Polygon' else geometria['coordinates']
            coordenadas = []
            for poligono in poligonos:
                for anillo in poligono:
                    if len(anillo) < 4 or anillo[0] != anillo[-1]:
                        raise ValueError('Anillo inválido.')
                    for x, y, *resto in anillo:
                        if not -180 <= x <= 180 or not -90 <= y <= 90:
                            raise ValueError('Coordenada fuera del mundo.')
                        coordenadas.append((x, y))
            codigo = pais + '-' + hashlib.sha256(nombre.encode()).hexdigest()[:12]
            caja = [min(x for x, y in coordenadas), min(y for x, y in coordenadas), max(x for x, y in coordenadas), max(y for x, y in coordenadas)]
            propiedades = {'codigo': codigo, 'pais': pais, 'nombre': nombre, 'nivel': nivel, 'caja': caja, 'procedencia': origen}
            feature = {'type': 'Feature', 'properties': propiedades, 'geometry': geometria}
            contenido = json.dumps(feature, ensure_ascii=False, separators=(',', ':')) + '\n'
            archivos[codigo + '.geojson'] = contenido
            indice.append(propiedades | {'sha256': hashlib.sha256(contenido.encode()).hexdigest()})
    if len({i['codigo'] for i in indice}) != len(indice) or len({i['pais'] for i in indice}) != 13:
        raise ValueError('El catálogo de regiones está incompleto o repetido.')
    archivos['indice.json'] = json.dumps({'version': 'envirosense-ab4f93a', 'datos': sorted(indice, key=lambda i: (i['pais'], i['nombre']))}, ensure_ascii=False, indent=2) + '\n'
    return archivos, indice


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--comprobar', action='store_true')
    argumentos = parser.parse_args()
    archivos, indice = extraer()
    if argumentos.comprobar:
        for nombre, contenido in archivos.items():
            ruta = SALIDA / nombre
            if not ruta.is_file() or ruta.read_bytes() != contenido.encode():
                raise SystemExit(f'El archivo {nombre} no reproduce el origen.')
        if set(p.name for p in SALIDA.iterdir()) != set(archivos):
            raise SystemExit('Hay archivos no previstos en el catálogo.')
    else:
        SALIDA.mkdir(parents=True, exist_ok=True)
        for nombre, contenido in archivos.items():
            (SALIDA / nombre).write_bytes(contenido.encode())
    print(f'Recuperadas {len(indice)} regiones de 13 países/territorios; geometrías conservadas sin simplificar.')
