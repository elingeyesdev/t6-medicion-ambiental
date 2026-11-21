import argparse
import hashlib
import json
import math
from pathlib import Path
import sys

parser = argparse.ArgumentParser(description='Contraste exploratorio OPERA del recorte de Santa Rosa del Sara.')
parser.add_argument('base', type=Path)
parser.add_argument('entradas', type=Path)
parser.add_argument('--salida', type=Path, required=True, help='Directorio de resultados nuevo')
args = parser.parse_args()

import numpy as np
import rasterio
from pyproj import Transformer
from rasterio.features import geometry_mask
from rasterio.vrt import WarpedVRT
from rasterio.warp import Resampling
from rasterio.windows import Window, from_bounds
from shapely.geometry import mapping, shape
from shapely.ops import transform, unary_union


def read_json(path):
    return json.loads(path.read_bytes())


def digest(path, algorithm='sha256'):
    with path.open('rb') as source:
        return hashlib.file_digest(source, algorithm).hexdigest()


def checked_raster(path, entry, crs, grid, width, height, allowed):
    if path.stat().st_size != entry['bytes'] or digest(path, 'md5') != entry['md5']:
        raise ValueError(f'El tamaño o MD5 no coincide: {path.name}')
    with rasterio.open(path) as source:
        if source.driver != 'GTiff' or source.count != 1 or source.dtypes != ('uint8',):
            raise ValueError(f'Se esperaba un GeoTIFF categórico de una banda: {path.name}')
        if not source.crs or source.crs.to_epsg() != 32720 or source.res != (30, 30):
            raise ValueError(f'CRS o resolución inesperados: {path.name}')
        with WarpedVRT(source, crs=crs, transform=grid, width=width, height=height,
                       resampling=Resampling.nearest, src_nodata=255, nodata=255) as vrt:
            values = vrt.read(1, masked=True).filled(255)
    extras = np.setdiff1d(np.unique(values), allowed)
    if extras.size:
        raise ValueError(f'Códigos desconocidos en {path.name}: {extras.tolist()}')
    return values


def count_coverage(zone, valid):
    denominator = int(zone.sum())
    comparable = int((zone & valid).sum())
    return {'celdas': denominator, 'comparables': comparable,
            'coberturaPct': round(100 * comparable / denominator, 4) if denominator else None}


def measure_changes(zone, comparable, before, after):
    valid = zone & comparable
    result = count_coverage(zone, comparable)
    metrics = {
        'aguaNueva': (before == 0) & np.isin(after, (1, 3)),
        'aguaPersistente': np.isin(before, (1, 3)) & np.isin(after, (1, 3)),
        'aguaPerdida': np.isin(before, (1, 3)) & (after == 0),
        'sinAguaEnAmbas': (before == 0) & (after == 0),
    }
    result['clases'] = {}
    for name, selector in metrics.items():
        cells = int((valid & selector).sum())
        result['clases'][name] = {'celdas': cells, 'ha': round(cells * 0.09, 2)}
    if sum(v['celdas'] for v in result['clases'].values()) != result['comparables']:
        raise ValueError('Las transiciones no cubren el conjunto comparable.')
    return result


def make_mask(geom, dimensions, grid):
    return geometry_mask([mapping(geom)], out_shape=dimensions, transform=grid, invert=True)


def rounded_window(geom, grid):
    w = from_bounds(*geom.bounds, transform=grid)
    x, y = math.floor(w.col_off), math.floor(w.row_off)
    return Window(x, y, math.ceil(w.col_off + w.width) - x,
                  math.ceil(w.row_off + w.height) - y)


reference_path = args.base / 'observacion-copernicus.geojson'
references = [f for f in read_json(reference_path)['features'] if f['properties']['dmg_src_id'] == 3]
references.sort(key=lambda f: f['properties']['area'], reverse=True)
candidate = references[17]
if len(references) != 101 or abs(candidate['properties']['area'] - 36.175681) > 1e-6:
    raise ValueError('La referencia no coincide con el candidato documentado.')
project = Transformer.from_crs(4326, 32720, always_xy=True).transform
polygon = transform(project, shape(candidate['geometry']))
area = polygon.buffer(500)
all_reference = unary_union([transform(project, shape(f['geometry'])) for f in references])

rtc_dir = args.base
rtc_path = rtc_dir / 'radar-recorte-30m.tif'
rtc_sha = digest(rtc_path)
if rtc_sha != (rtc_dir / 'radar-sha256.txt').read_text(encoding='ascii').strip():
    raise ValueError('La huella del recorte SAR original no coincide.')
with rasterio.open(rtc_path) as ds:
    if ds.crs.to_epsg() != 32720 or ds.res != (30, 30) or ds.count != 4:
        raise ValueError('La malla del recorte original no coincide.')
    w = rounded_window(area, ds.transform)
    grid = ds.window_transform(w)
    crs = ds.crs
    vals = ds.read(window=w, boundless=True, fill_value=np.nan)
    valid_radiometry = (np.isfinite(vals) & (vals > 0)).all(axis=0)

dims = valid_radiometry.shape
around = make_mask(area, dims, grid)
zones = {
    'poligono': make_mask(polygon, dims, grid),
    'entorno500m': around,
    'fueraDeReferenciaEnEntorno': around & ~make_mask(all_reference, dims, grid),
}
baseline = {name: count_coverage(zone, valid_radiometry) for name, zone in zones.items()}
if (baseline['entorno500m']['celdas'], baseline['entorno500m']['comparables']) != (2974, 2490):
    raise ValueError('El recorte no reproduce la línea de base de #111.')

sources = read_json(Path(__file__).with_name('fuentes.json'))['opera']
paths = [args.entradas / entry['archivo'] for entry in sources]
missing = [path.name for path in paths if not path.is_file()]
if missing:
    print(json.dumps({'estado': 'faltan_archivos', 'cobertura_radiometrica': baseline,
                      'archivos_faltantes': missing}, ensure_ascii=True, indent=2))
    sys.exit(2)

spec = {'rtc': (0, 1, 2, 3, 255), 'wtr': (0, 1, 3, 250, 251, 255)}
layers, receipts = {}, []
for entry, path in zip(sources, paths, strict=True):
    product, date = entry['tipo'], entry['fecha']
    layers[product, date] = checked_raster(path, entry, crs, grid, dims[1], dims[0], spec[product])
    receipts.append({**entry, 'sha256': digest(path)})

dates = ('2025-12-06', '2025-12-18')
ma, mb = (layers['rtc', d] for d in dates)
before, after = (layers['wtr', d] for d in dates)
valid_masks = (ma == 0) & (mb == 0)
valid_water = np.isin(before, (0, 1, 3)) & np.isin(after, (0, 1, 3))
conservative = valid_radiometry & valid_masks & valid_water
result = {
    'estado': 'revision_tecnica',
    'fechas': dates,
    'candidato': 18,
    'radio_metros': 500,
    'crs': str(crs),
    'resolucion_metros': 30,
    'sha256_referencia': digest(reference_path),
    'sha256_recorte_radar': rtc_sha,
    'fuentes': receipts,
    'cobertura_radiometrica': baseline,
    'rtc_con_mascaras': {name: count_coverage(zone, valid_radiometry & valid_masks)
                         for name, zone in zones.items()},
    'dswx': {name: measure_changes(zone, valid_water, before, after) for name, zone in zones.items()},
    'dswx_en_interseccion_con_rtc': {name: measure_changes(zone, conservative, before, after)
                                   for name, zone in zones.items()},
}

from rasterio.features import rasterize, shapes
from shapely import make_valid
from shapely.geometry import Polygon

def polygon_parts(geom):
    if isinstance(geom, Polygon):
        yield geom
    elif hasattr(geom, 'geoms'):
        for part in geom.geoms:
            yield from polygon_parts(part)

classes = (
    ('agua_nueva', conservative & (before == 0) & np.isin(after, (1, 3))),
    ('agua_persistente', conservative & np.isin(before, (1, 3)) & np.isin(after, (1, 3))),
    ('agua_retirada', conservative & np.isin(before, (1, 3)) & (after == 0)),
    ('no_comparable', ~conservative),
)
unproject = Transformer.from_crs(32720, 4326, always_xy=True).transform
features = []
summary = {}
covered = np.zeros(dims, dtype=bool)
for label, cells in classes:
    zone = cells & around
    if (zone & covered).any():
        raise ValueError('Solapamiento en las clases')
    covered |= zone
    parts = []
    for geom, label_value in shapes(zone.astype('uint8'), mask=zone, transform=grid):
        if label_value != 1:
            raise ValueError('La geometría tiene un valor inesperado')
        parts.extend(polygon_parts(make_valid(shape(geom))))
    count = int(zone.sum())
    if abs(sum(g.area for g in parts) - count * 900) > 0.001:
        raise ValueError('La superficie no coincide con el conteo raster')
    back = rasterize([(mapping(g), 1) for g in parts], out_shape=dims, transform=grid)
    if not np.array_equal(back.astype(bool), zone):
        raise ValueError('La geometría no reproduce las celdas')
    for index, geom in enumerate(parts, 1):
        features.append({'type': 'Feature', 'id': f'{label}-{index}',
                         'properties': {'clase': label, 'area_ha': round(geom.area / 10000, 2)},
                         'geometry': mapping(transform(unproject, geom))})
    summary[label] = {'celdas': count, 'poligonos': len(parts)}
payload = {'type': 'FeatureCollection', 'estado': 'revision_tecnica', 'fechas': dates,
           'alcance': 'entorno de 500 m del polígono candidato de Santa Rosa del Sara',
           'fuente': 'OPERA DSWx-S1 y RTC-S1, NASA JPL',
           'features': features}
args.salida.mkdir(parents=True, exist_ok=False)
with (args.salida / 'capa-revision.geojson').open('x', encoding='utf-8', newline='\n') as dst:
    json.dump(payload, dst, ensure_ascii=False, separators=(',',':'))
    dst.write('\n')
result['vectorizacion'] = summary
(args.salida / 'contraste.json').write_text(json.dumps(result, indent=2) + '\n', encoding='utf-8')
print(json.dumps({'cobertura': result['dswx_en_interseccion_con_rtc']['entorno500m'], 'capa': summary}, indent=2))
