"""Descarga y prepara los insumos públicos del piloto de Santa Rosa del Sara."""
import argparse
import hashlib
from io import BytesIO
import json
import math
from pathlib import Path
from urllib.parse import urlencode
from urllib.request import urlopen
from zipfile import ZipFile

import numpy as np
import rasterio
from rasterio.features import geometry_mask
from rasterio.transform import from_origin
from rasterio.warp import Resampling, reproject, transform_bounds, transform_geom
from rasterio.windows import Window, from_bounds

CRS = 'EPSG:32720'
BBOX = (-63.641, -16.81, -63.52, -16.675)
STEP = 30
SIGN = 'https://planetarycomputer.microsoft.com/api/sas/v1/sign?'


def sha(data):
    return hashlib.sha256(data).hexdigest()


def descargar_referencia(fuente, salida):
    with urlopen(fuente['url'], timeout=30) as response:
        contenido = response.read(fuente['bytes'] + 1)
    if len(contenido) != fuente['bytes'] or sha(contenido) != fuente['sha256']:
        raise ValueError('El archivo DEL v2 de Copernicus cambió.')
    with ZipFile(BytesIO(contenido)) as archivo:
        capa = archivo.read(fuente['capa'])
    if sha(capa) != fuente['sha256Capa']:
        raise ValueError('La capa de referencia de Copernicus cambió.')
    (salida / 'observacion-copernicus.geojson').write_bytes(capa)


def leer_banda(fuente, grid, inside):
    with urlopen(SIGN + urlencode({'href': fuente['url']}), timeout=20) as response:
        url = json.load(response)['href']
    try:
        with rasterio.open('/vsicurl/' + url) as ds:
            if ds.count != 1 or ds.crs.to_epsg() != 32720 or ds.res != (10, 10):
                raise ValueError('La malla de origen cambió.')
            bounds = transform_bounds('EPSG:4326', ds.crs, *BBOX)
            window = from_bounds(*bounds, transform=ds.transform)
            window = window.round_offsets().round_lengths().intersection(Window(0, 0, ds.width, ds.height))
            values = ds.read(1, window=window, masked=True).astype('float32').filled(np.nan)
            values[~np.isfinite(values) | (values <= 0)] = np.nan
            result = np.full(inside.shape, np.nan, dtype='float32')
            reproject(values, result, src_transform=ds.window_transform(window),
                      src_crs=ds.crs, src_nodata=np.nan, dst_transform=grid,
                      dst_crs=CRS, dst_nodata=np.nan, resampling=Resampling.nearest)
    except Exception as error:
        # Los enlaces firmados solo se mantienen en memoria.
        raise ValueError(f"No se pudo leer {fuente['escena']} {fuente['banda']} ({type(error).__name__}).") from None
    result[~inside] = np.nan
    if np.isfinite(result).sum() != fuente['pixelesValidos']:
        raise ValueError(f"Cambió la cobertura de {fuente['escena']} {fuente['banda']}.")
    if sha(result.astype('<f4', copy=False).tobytes()) != fuente['sha256PixelesFloat32LE']:
        raise ValueError(f"Cambiaron los píxeles de {fuente['escena']} {fuente['banda']}.")
    return result


def preparar_radar(fuentes, salida):
    left, bottom, right, top = transform_bounds('EPSG:4326', CRS, *BBOX)
    left, top = math.floor(left / STEP) * STEP, math.ceil(top / STEP) * STEP
    width, height = math.ceil((right - left) / STEP), math.ceil((top - bottom) / STEP)
    grid = from_origin(left, top, STEP, STEP)
    west, south, east, north = BBOX
    area = {'type': 'Polygon', 'coordinates': [[(west, south), (east, south), (east, north),
                                              (west, north), (west, south)]]}
    inside = geometry_mask([transform_geom('EPSG:4326', CRS, area)], out_shape=(height, width),
                           transform=grid, invert=True)
    with rasterio.Env(GDAL_DISABLE_READDIR_ON_OPEN='EMPTY_DIR', GDAL_HTTP_TIMEOUT='18', GDAL_HTTP_MAX_RETRY='2'):
        bands = [leer_banda(f, grid, inside) for f in fuentes]
    if np.logical_and.reduce([np.isfinite(b) for b in bands]).sum() != 145885:
        raise ValueError('Cambió la cobertura común del recorte completo.')
    path = salida / 'radar-recorte-30m.tif'
    with rasterio.open(path, 'w', driver='GTiff', count=len(bands), width=width, height=height,
                       dtype='float32', crs=CRS, transform=grid, nodata=np.nan, compress='deflate') as ds:
        for i, (data, fuente) in enumerate(zip(bands, fuentes, strict=True), 1):
            ds.write(data, i)
            ds.set_band_description(i, fuente['escena'] + ' ' + fuente['banda'])
    (salida / 'radar-sha256.txt').write_text(sha(path.read_bytes()) + '\n', encoding='ascii')


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('salida', type=Path, help='Directorio de trabajo nuevo')
    args = parser.parse_args()
    sources = json.loads(Path(__file__).with_name('fuentes.json').read_bytes())
    args.salida.mkdir(parents=True, exist_ok=False)
    descargar_referencia(sources['copernicus'], args.salida)
    preparar_radar(sources['sentinel1'], args.salida)
