import json
from pathlib import Path
from shapely.geometry import shape, Point

root = Path(__file__).resolve().parents[2]
response = json.loads((root/'docs/evidencias/api01/consulta_real.json').read_text(encoding='utf-8'))
data = root/'compartido/territorios/datos'
department = shape(json.loads((data/'limites/departamentos.geojson').read_text(encoding='utf-8'))['features'][0]['geometry'])
municipalities = [(f['id'],shape(f['geometry'])) for f in json.loads((data/'limites/municipios.geojson').read_text(encoding='utf-8'))['features']]
for foco in response['datos']:
    point = Point(foco['geometria']['coordinates'])
    assert department.covers(point)
    matches = [identifier for identifier,geometry in municipalities if geometry.covers(point)]
    expected = matches[0] if len(matches)==1 else None
    assert foco['territorio']['municipio'] == expected
ids = [f['identificador'] for f in response['datos']]
assert len(ids)==len(set(ids))==response['resumen']['totalRegistros']
assert response['resumen']['indicadores'][2]['valor']==max(f['atributos']['potenciaRadiativa'] for f in response['datos'])
print(f'{len(ids)} detecciones reales: pertenencia departamental y municipal contrastada con Shapely, sin duplicados, máximo FRP coherente.')
