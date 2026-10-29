"""Campos GFS explícitos: unidades, nivel, intervalo, máscara y rejilla verificables."""
import argparse
from datetime import datetime, timedelta
import hashlib
import json
import os
from pathlib import Path
import shutil
import tempfile

import eccodes as ec
import numpy as np
from PIL import Image

from producir import SA, MAX_PIXELES, MAX_ENTRADA, MAX_PNG

# Categoría/número WMO, tipo de nivel, altura, unidad GRIB y tipo de paso.
ESPECIFICACIONES = {
    'temperatura': (0, 0, 'heightAboveGround', 2, 'K', 'instant'),
    'humedad': (1, 1, 'heightAboveGround', 2, '%', 'instant'),
    'u10': (2, 2, 'heightAboveGround', 10, 'm s**-1', 'instant'),
    'v10': (2, 3, 'heightAboveGround', 10, 'm s**-1', 'instant'),
    'presion': (3, 1, 'meanSea', 0, 'Pa', 'instant'),
    'visibilidad': (19, 0, 'surface', 0, 'm', 'instant'),
    'nieve': (1, 13, 'surface', 0, 'kg m**-2', 'instant'),
    'lluvia_intensidad': (1, 7, 'surface', 0, 'kg m**-2 s**-1', 'instant'),
    'lluvia': (1, 8, 'surface', 0, 'kg m**-2', 'accum'),
}
CAPAS = {
    'temperatura': ('Temperatura a 2 m', '°C', 'temp-era5', -50, 50, 1, -273.15),
    'humedad': ('Humedad relativa a 2 m', '%', 'humedad-etl', 0, 100, 1, 0),
    'presion': ('Presión a nivel del mar', 'hPa', 'presion-etl', 900, 1100, .01, 0),
    'visibilidad': ('Visibilidad horizontal', 'km', 'vis-etl', 0, 24.140, .001, 0),
    'nieve': ('Nieve acumulada · equivalente de agua', 'mm', 'nieve-etl', 0, 150, 1, 0),
    'lluvia_intensidad': ('Intensidad de precipitación total', 'mm/h', 'lluvia-escalones', 0, 150, 3600, 0),
    'lluvia': ('Precipitación total acumulada', 'mm', 'lluvia-escalones', 0, 150, 1, 0),
    'viento': ('Viento a 10 m', 'km/h', 'viento-uv127', -100, 100, 1, 0),
}
LLUVIA = np.array([0, .2, .5, 1, 2, 3, 4, 5, 7.5, 10, 15, 20, 25, 30, 35, 40, 50, 60, 70, 85, 100, 150])


def leer_mensaje(mensaje):
    def get(k):
        return ec.codes_get(mensaje, k)
    if (get('edition') != 2 or get('centre') != 'kwbc' or get('discipline') != 0 or
            get('gridType') != 'regular_ll' or get('typeOfGeneratingProcess') != 2 or
            get('generatingProcessIdentifier') not in (81, 96)):
        raise ValueError('El mensaje no es un campo determinista GFS NCEP de la rejilla acordada.')
    identidad = tuple(get(k) for k in ('parameterCategory', 'parameterNumber', 'typeOfLevel', 'level', 'units', 'stepType'))
    clave = next((k for k, s in ESPECIFICACIONES.items() if s == identidad), None)
    if clave is None:
        return None  # Otro campo no se usa como sustituto del solicitado.
    plantilla = get('productDefinitionTemplateNumber')
    if plantilla != (8 if clave == 'lluvia' else 0):
        raise ValueError('No se aceptan ensembles, promedios o probabilidades como un campo instantáneo.')
    if clave == 'lluvia' and (get('typeOfStatisticalProcessing') != 1 or get('numberOfTimeRanges') != 1):
        raise ValueError('La lluvia debe ser un acumulado de un único intervalo explícito.')
    if get('stepUnits') != 1:
        raise ValueError('El plazo GFS debe estar expresado en horas.')
    ciclo = datetime.strptime(f"{get('dataDate'):08d}{get('dataTime'):04d}", '%Y%m%d%H%M')
    valido = datetime.strptime(f"{get('validityDate'):08d}{get('validityTime'):04d}", '%Y%m%d%H%M')
    inicio, fin = get('startStep'), get('endStep')
    if get('generatingProcessIdentifier') == 81 and fin != 0:
        raise ValueError('El proceso de análisis 81 solo se acepta para el plazo cero.')
    if (ciclo.hour not in (0, 6, 12, 18) or ciclo.minute or not 0 <= inicio <= fin <= 384 or
            valido != ciclo + timedelta(hours=fin) or
            (clave == 'lluvia' and inicio == fin) or (clave != 'lluvia' and inicio != fin)):
        raise ValueError('Ciclo, plazo e intervalo no concuerdan.')
    ancho, alto = get('Ni'), get('Nj')
    if ancho < 2 or alto < 2 or ancho * alto > MAX_PIXELES or get('alternativeRowScanning') != 0:
        raise ValueError('Rejilla excesiva o barrido no admitido.')
    def matriz(k):
        valores = ec.codes_get_array(mensaje, k)
        if len(valores) != ancho * alto:
            raise ValueError('El tamaño del campo y sus coordenadas no coincide.')
        return valores.reshape((ancho, alto)).T if get('jPointsAreConsecutive') else valores.reshape((alto, ancho))
    valores, latitudes, longitudes = matriz('values'), matriz('latitudes'), matriz('longitudes')
    if get('bitmapPresent'):
        valores = np.where(matriz('bitmap').astype(bool), valores, np.nan)
    lat, lon = latitudes[:, 0], longitudes[0, :]
    if not np.allclose(latitudes, lat[:, None]) or not np.allclose(longitudes, lon[None, :]):
        raise ValueError('No se acepta una rejilla curvilínea como latitud/longitud regular.')
    lon = ((lon + 180) % 360) - 180
    filas, columnas = np.argsort(lat)[::-1], np.argsort(lon)
    lat, lon, valores = lat[filas], lon[columnas], valores[np.ix_(filas, columnas)]
    filas = np.flatnonzero((lat >= SA[1]) & (lat <= SA[3]))
    columnas = np.flatnonzero((lon >= SA[0]) & (lon <= SA[2]))
    lat, lon, valores = lat[filas], lon[columnas], valores[np.ix_(filas, columnas)]
    for eje, paso in ((lat, -.25), (lon, .25)):
        if len(eje) < 2 or not np.isfinite(eje).all() or not np.allclose(np.diff(eje), paso, atol=1e-7):
            raise ValueError('Se requiere la rejilla regular GFS de 0,25 grados sin duplicados.')
    rejilla = {'proyeccion': 'EPSG:4326', 'registro': 'nodos', 'filas': 'norte-sur',
               'oeste': float(lon[0]), 'este': float(lon[-1]), 'sur': float(lat[-1]), 'norte': float(lat[0])}
    tiempo = {'ciclo': ciclo.strftime('%Y-%m-%dT%H:%M:%SZ'), 'instante': valido.strftime('%Y-%m-%dT%H:%M:%SZ'),
              'desde': (ciclo + timedelta(hours=inicio)).strftime('%Y-%m-%dT%H:%M:%SZ'), 'hasta': valido.strftime('%Y-%m-%dT%H:%M:%SZ'),
              'inicio_horas': inicio, 'fin_horas': fin, 'tipo_paso': identidad[-1]}
    return clave, {'valores': valores, 'rejilla': rejilla, 'tiempo': tiempo, 'unidad_entrada': identidad[-2], 'duplicados_iguales': 0}


def leer_campos(entrada):
    ruta = Path(entrada)
    if not ruta.is_file() or ruta.is_symlink() or ruta.stat().st_size > MAX_ENTRADA:
        raise ValueError('Elige un GRIB2 local regular de hasta 128 MiB.')
    campos = {}
    with ruta.open('rb') as archivo:
        numero = 0
        while True:
            mensaje = ec.codes_grib_new_from_file(archivo)
            if mensaje is None:
                break
            try:
                numero += 1
                if numero > 64:
                    raise ValueError('El conjunto contiene demasiados mensajes.')
                resultado = leer_mensaje(mensaje)
                if resultado is None:
                    continue
                clave, campo = resultado
                identidad = clave if clave != 'lluvia' else 'lluvia:'+str(campo['tiempo']['inicio_horas'])+':'+str(campo['tiempo']['fin_horas'])
                if identidad in campos:
                    previo = campos[identidad]
                    if any(previo[k] != campo[k] for k in ('rejilla', 'tiempo', 'unidad_entrada')) or not np.array_equal(previo['valores'], campo['valores'], equal_nan=True):
                        raise ValueError('Hay campos distintos para la misma variable; no se elige uno silenciosamente.')
                    previo['duplicados_iguales'] += 1
                else:
                    campos[identidad] = campo
            finally:
                ec.codes_release(mensaje)
    if not campos:
        raise ValueError('No hay campos compatibles.')
    return campos


def codificar_campo(clave, valores, v=None):
    _, _, _, minimo, maximo, factor, desplazamiento = CAPAS[clave]
    a = np.asarray(valores, dtype=float) * factor + desplazamiento
    # GRIB de 24 bits puede reconstruir 100 % como 100.0000015 %. No es sobresaturación.
    if clave == 'humedad':
        a = np.where((a >= -1e-5) & (a <= 100+1e-5), np.clip(a, 0, 100), a)
    finitos = np.isfinite(a)
    validos = finitos & (a >= minimo) & (a <= maximo)
    rgba = np.zeros((*a.shape, 4), dtype=np.uint8)
    if clave == 'viento':
        if v is None or np.asarray(v).shape != a.shape:
            raise ValueError('El viento requiere ambas componentes de la misma rejilla.')
        b = np.asarray(v, dtype=float)
        finitos &= np.isfinite(b)
        validos &= np.isfinite(b) & (b >= minimo) & (b <= maximo)
        rgba[validos, 0] = np.rint(a[validos] * 1.27 + 127).astype(np.uint8)
        rgba[validos, 1] = np.rint(b[validos] * 1.27 + 127).astype(np.uint8)
        error = 3.6 * np.sqrt(2) / 2.54
    else:
        codigos = np.interp(a[validos], LLUVIA, np.arange(22)) / 21 * 255 if clave.startswith('lluvia') else (a[validos] - minimo) / (maximo - minimo) * 255
        rgba[validos, :3] = np.rint(codigos).astype(np.uint8)[:, None]
        error = float(np.diff(LLUVIA).max() * 21 / 510) if clave.startswith('lluvia') else (maximo - minimo) / 510
    if not validos.any():
        raise ValueError('El campo no contiene valores representables; no se publica una imagen vacía.')
    rgba[validos, 3] = 255
    return rgba, {'validos': int(validos.sum()), 'ausentes': int((~finitos).sum()), 'fuera_de_escala': int((finitos & ~validos).sum()),
                  'error_maximo_cuantizacion_unidad_salida': float(error), 'politica_fuera_de_escala': 'null, sin saturar valores'}


def producir_campos(entrada, salida, capas, controlado=False, acumulado_desde=None):
    destino, origen = Path(salida), Path(entrada)
    if destino.exists() or destino.is_symlink() or not destino.parent.is_dir():
        raise ValueError('La salida debe ser un directorio nuevo dentro de una carpeta existente.')
    if not capas or len(set(capas)) != len(capas) or any(c not in CAPAS for c in capas):
        raise ValueError('Elige capas distintas del catálogo.')
    huella = hashlib.sha256(origen.read_bytes()).hexdigest() if origen.is_file() and origen.stat().st_size <= MAX_ENTRADA else None
    campos = leer_campos(origen)
    if 'lluvia' in capas:
        acumulados = [c for k, c in campos.items() if k.startswith('lluvia:') and
                      (acumulado_desde is None or c['tiempo']['inicio_horas'] == acumulado_desde)]
        if len(acumulados) > 1:
            raise ValueError('Hay distintos intervalos de precipitación; elige --acumulado-desde en horas del ciclo.')
        if acumulados:
            campos['lluvia'] = acumulados[0]
    temporal = Path(tempfile.mkdtemp(prefix='.campos-', dir=destino.parent))
    cuadros, procedencias = [], []
    try:
        for clave in capas:
            campo = campos.get('u10' if clave == 'viento' else clave)
            v = campos.get('v10') if clave == 'viento' else None
            if campo is None or (clave == 'viento' and v is None):
                raise ValueError('Falta el insumo de '+clave+'; no se omite la capa pedida.')
            if v and any(v[k] != campo[k] for k in ('rejilla', 'tiempo')):
                raise ValueError('Las componentes del viento tienen fechas o rejillas distintas.')
            rgba, estadisticas = codificar_campo(clave, campo['valores'], v['valores'] if v else None)
            nombre, unidad, codigo, *_ = CAPAS[clave]
            tiempo = campo['tiempo']
            instante = tiempo['instante'].translate(str.maketrans('', '', '-:TZ'))
            identidad = f'gfs-{clave}-{instante}-{huella[:12]}' + ('-prueba' if controlado else '')
            archivo = clave+'/'+identidad+'.png'
            (temporal/clave).mkdir(exist_ok=True)
            Image.fromarray(rgba).save(temporal/archivo, format='PNG', compress_level=9, optimize=False)
            png = (temporal/archivo).read_bytes()
            if len(png) > MAX_PNG:
                raise ValueError('El PNG supera el límite del visor.')
            producto = 'Modelo GFS · '+nombre
            if clave == 'lluvia':
                producto += ' · '+tiempo['desde'][11:16]+' a '+tiempo['hasta'][11:16]+' UTC'
            if controlado:
                producto = 'Fixture técnico · '+nombre
            cuadros.append({'id': identidad, 'capa': clave, 'instante': tiempo['instante'], 'archivo': archivo, 'codificacion': codigo,
                            'fuente': 'Datos controlados de prueba' if controlado else 'NOAA/NCEP · GFS 0.25°', 'producto': producto,
                            'rejilla': campo['rejilla'], 'unidad': unidad, 'intervalo': tiempo,
                            'atribucion': 'Fixture sintético.' if controlado else 'Datos NOAA/NWS/NCEP, transformados por GeoSur; sin aval de NOAA/NWS.'})
            procedencias.append({'capa': clave, 'tiempo': tiempo, 'unidad_entrada': campo['unidad_entrada'], 'unidad_salida': unidad,
                                 'estadisticas': estadisticas, 'duplicados_iguales': campo['duplicados_iguales'],
                                 'salida_sha256': hashlib.sha256(png).hexdigest(), 'ancho': rgba.shape[1], 'alto': rgba.shape[0]})
        if hashlib.sha256(origen.read_bytes()).hexdigest() != huella:
            raise ValueError('El original cambió durante la lectura.')
        receta = {'version': 1, 'cuadros': cuadros}
        metadata = {'version': 1, 'datos_controlados': controlado, 'entrada_sha256': huella, 'entrada_bytes': origen.stat().st_size,
                    'tipo': 'modelo_estimado' if not controlado else 'fixture', 'campos': procedencias,
                    'limites': 'Escala finita de cada PNG; valores fuera de escala transparentes. No son estaciones ni profundidad de nieve.'}
        for nombre, documento in (('receta.json', receta), ('procedencia.json', metadata)):
            (temporal/nombre).write_text(json.dumps(documento, ensure_ascii=False, allow_nan=False, indent=2)+'\n', encoding='utf-8')
        if destino.exists() or destino.is_symlink():
            raise ValueError('La salida apareció durante el proceso; se conserva sin sobrescribir.')
        os.rename(temporal, destino)
        return metadata
    finally:
        if temporal.is_dir():
            if temporal.is_symlink() or temporal.resolve().parent != destino.parent.resolve():
                raise ValueError('La carpeta temporal cambió; se conserva para revisión.')
            shutil.rmtree(temporal)


if __name__ == '__main__':
    p = argparse.ArgumentParser(description=__doc__)
    p.add_argument('--entrada', required=True)
    p.add_argument('--salida', required=True)
    p.add_argument('--capas', nargs='+', choices=CAPAS, default=list(CAPAS))
    p.add_argument('--datos-controlados', action='store_true')
    p.add_argument('--acumulado-desde', type=int, help='Hora inicial del intervalo APCP, contada desde el ciclo GFS; no elige otro período en silencio.')
    a = p.parse_args()
    try:
        r = producir_campos(a.entrada, a.salida, a.capas, a.datos_controlados, a.acumulado_desde)
        print(json.dumps({'capas': [c['capa'] for c in r['campos']], 'datos_controlados': r['datos_controlados'], 'entrada_sha256': r['entrada_sha256']}))
    except (ValueError, OSError, RuntimeError) as e:
        p.exit(2, 'No se publicó el conjunto: '+str(e)+'\n')
