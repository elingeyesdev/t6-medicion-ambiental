"""SVG de referencia del inicio, a partir del mismo catálogo que usan los módulos."""
import hashlib
import html
import json
import math
from pathlib import Path

raiz = Path(__file__).resolve().parents[1]
fuente = raiz / 'compartido/territorios/datos/limites/provincias.geojson'
destino = raiz / 'web/public/img/envirosense'
destino.mkdir(parents=True, exist_ok=True)
datos = json.loads(fuente.read_text(encoding='utf-8'))
factor = math.cos(math.radians(-17.5))

def anillos(geometria):
    poligonos = geometria['coordinates'] if geometria['type'] == 'MultiPolygon' else [geometria['coordinates']]
    return [anillo for poligono in poligonos for anillo in poligono]

puntos = [p for f in datos['features'] for a in anillos(f['geometry']) for p in a]
xs, ys = [p[0] * factor for p in puntos], [-p[1] for p in puntos]
x0, y0 = min(xs), min(ys)
escala = min(480 / (max(xs) - min(xs)), 530 / (max(ys) - min(ys)))
dx = (560 - (max(xs) - min(xs)) * escala) / 2
dy = (610 - (max(ys) - min(ys)) * escala) / 2

def proyectar(p):
    return ((p[0] * factor - x0) * escala + dx, (-p[1] - y0) * escala + dy)

def para_portada(anillo):
    """Reducir trazos subpíxel solo en la ilustración; no toca el catálogo."""
    elegidos = []
    for p in anillo:
        xy = proyectar(p)
        if not elegidos or math.dist(elegidos[-1], xy) >= .65:
            elegidos.append(xy)
    return elegidos if len(elegidos) >= 3 else [proyectar(p) for p in anillo]

salida = ['<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 560 610" role="img" aria-labelledby="titulo descripcion">', '<title id="titulo">Provincias de Santa Cruz, Bolivia</title>', '<desc id="descripcion">Límites de referencia OCHA/HDX. No presenta resultados ambientales.</desc>', '<g fill="#5bc0be" fill-opacity=".10" stroke="#438c89" stroke-width="1.2" stroke-linejoin="round" fill-rule="evenodd">']
for feature in datos['features']:
    camino = []
    for anillo in anillos(feature['geometry']):
        puntos_svg = para_portada(anillo)
        camino.append('M' + 'L'.join(f'{x:.2f},{y:.2f}' for x, y in puntos_svg) + 'Z')
    salida.append(f'<path d="{"".join(camino)}"><title>{html.escape(feature["properties"]["nombre"])}</title></path>')
salida.append('</g>')
x, y = proyectar([-63.1821, -17.7833])
salida.append(f'<circle cx="{x:.2f}" cy="{y:.2f}" r="4" fill="#287b79"/><text x="{x+10:.2f}" y="{y+4:.2f}" font-family="system-ui,sans-serif" font-size="12" fill="#438c89">Santa Cruz de la Sierra</text>')
salida.append('</svg>')
(destino / 'santa-cruz.svg').write_text('\n'.join(salida) + '\n', encoding='utf-8')
print('Mapa de inicio generado desde las 15 provincias del catálogo.')
