"""Contrato del productor multivariable con GRIB2 controlados, nunca datos reales."""
import json
import os
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile
import unittest

import eccodes as ec
import numpy as np
from PIL import Image

BASE = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(BASE))
from campos_gfs import CAPAS, codificar_campo, leer_campos, producir_campos
from adquirir_gfs import descargar, parametros
from test_adquisicion import Respuesta, Conexion


def mensaje(clave, valores=None, cambios=None):
    m = ec.codes_grib_new_from_samples('regular_ll_sfc_grib2')
    try:
        cat, num, nivel, altura = {
            'temperatura': (0, 0, 'heightAboveGround', 2), 'humedad': (1, 1, 'heightAboveGround', 2),
            'u10': (2, 2, 'heightAboveGround', 10), 'v10': (2, 3, 'heightAboveGround', 10),
            'presion': (3, 1, 'meanSea', 0), 'visibilidad': (19, 0, 'surface', 0),
            'nieve': (1, 13, 'surface', 0), 'lluvia_intensidad': (1, 7, 'surface', 0),
            'lluvia': (1, 8, 'surface', 0),
            'radiacion_solar': (4, 192, 'surface', 0), 'evaporacion_potencial': (1, 200, 'surface', 0),
        }[clave]
        ajustes = {'centre': 'kwbc', 'Ni': 3, 'Nj': 3, 'latitudeOfFirstGridPointInDegrees': -17,
                   'latitudeOfLastGridPointInDegrees': -17.5, 'longitudeOfFirstGridPointInDegrees': 298,
                   'longitudeOfLastGridPointInDegrees': 298.5, 'iDirectionIncrementInDegrees': .25,
                   'jDirectionIncrementInDegrees': .25, 'jScansPositively': 0, 'dataDate': 20261029,
                   'dataTime': 600, 'typeOfGeneratingProcess': 2, 'generatingProcessIdentifier': 96,
                   'typeOfLevel': nivel, 'level': altura, 'parameterCategory': cat, 'parameterNumber': num,
                   'stepType': 'accum' if clave == 'lluvia' else 'avg' if clave == 'radiacion_solar' else 'instant',
                   'stepRange': '0-3' if clave in ('lluvia', 'radiacion_solar') else '3',
                   'bitmapPresent': 1, 'missingValue': 9999, 'bitsPerValue': 24}
        if clave in ('radiacion_solar', 'evaporacion_potencial'):
            ajustes.update(tablesVersion=2, localTablesVersion=1)
        for k, v in (ajustes | (cambios or {})).items():
            ec.codes_set(m, k, v)
        defecto = {'temperatura': 293.15, 'humedad': 60, 'u10': 0, 'v10': 0, 'presion': 101325,
                   'visibilidad': 10000, 'nieve': 0, 'lluvia_intensidad': .001, 'lluvia': 5,
                   'radiacion_solar': 400, 'evaporacion_potencial': -20}[clave]
        ec.codes_set_values(m, valores if valores is not None else [defecto]*9)
        return ec.codes_get_message(m)
    finally:
        ec.codes_release(m)


class CamposTest(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.ruta = Path(self.tmp.name)

    def tearDown(self):
        self.tmp.cleanup()

    def entrada(self, *mensajes):
        p = self.ruta/'campos.grib2'
        p.write_bytes(b''.join(mensajes))
        return p

    def test_ocho_capas_niveles_unidades_y_publicador_real(self):
        p = self.entrada(*(mensaje(k) for k in ('temperatura', 'humedad', 'u10', 'v10', 'presion', 'visibilidad', 'nieve', 'lluvia', 'lluvia_intensidad')))
        salida = self.ruta/'lote'
        meta = producir_campos(p, salida, list(CAPAS), controlado=True)
        self.assertTrue(meta['datos_controlados'])
        self.assertEqual(len(meta['campos']), 8)
        receta = json.loads((salida/'receta.json').read_text(encoding='utf-8'))
        lluvia = next(c for c in receta['cuadros'] if c['capa'] == 'lluvia')
        self.assertEqual(lluvia['intervalo']['desde'], '2026-10-29T06:00:00Z')
        self.assertEqual(lluvia['intervalo']['hasta'], '2026-10-29T09:00:00Z')
        self.assertEqual(lluvia['unidad'], 'mm')
        viento = next(c for c in receta['cuadros'] if c['capa'] == 'viento')
        self.assertEqual(viento['codificacion'], 'viento-uv127')
        pixel = np.asarray(Image.open(salida/viento['archivo']))[0, 0]
        np.testing.assert_array_equal(pixel, [127, 127, 0, 255])
        php = os.environ.get('RASTER_PHP') or shutil.which('php')
        self.assertIsNotNone(php)
        proceso = subprocess.run([php, str(BASE/'tests/publicador_real.php'), str(salida), '8'], capture_output=True, text=True, timeout=15)
        self.assertEqual(proceso.returncode, 0, proceso.stdout+proceso.stderr)
        self.assertEqual(json.loads(proceso.stdout)['cuadros'], 8)

    def test_conversiones_sin_confundir_nieve_ni_tasa_y_acumulado(self):
        pruebas = [('temperatura', 273.15, 0), ('humedad', 50, 50), ('presion', 101325, 1013.25),
                   ('visibilidad', 10000, 10), ('nieve', 20, 20), ('lluvia_intensidad', .001, 3.6), ('lluvia', 5, 5)]
        escalones = np.array([0, .2, .5, 1, 2, 3, 4, 5, 7.5, 10, 15, 20, 25, 30, 35, 40, 50, 60, 70, 85, 100, 150])
        for clave, original, esperado in pruebas:
            with self.subTest(clave=clave):
                rgba, meta = codificar_campo(clave, np.array([[original]]))
                r = float(rgba[0, 0, 0])
                minimo, maximo = CAPAS[clave][3:5]
                real = np.interp(r/255*21, np.arange(22), escalones) if clave.startswith('lluvia') else r/255*(maximo-minimo)+minimo
                self.assertLessEqual(abs(real-esperado), meta['error_maximo_cuantizacion_unidad_salida']+1e-10)

    def test_mascara_cero_y_valor_fuera_de_escala(self):
        rgba, meta = codificar_campo('humedad', np.array([[0, np.nan, -1, 101, 100]]))
        np.testing.assert_array_equal(rgba[0, :, 3], [255, 0, 0, 0, 255])
        self.assertEqual(meta['ausentes'], 1)
        self.assertEqual(meta['fuera_de_escala'], 2)
        viento, _ = codificar_campo('viento', [[0, 5, 101]], [[0, np.nan, 0]])
        np.testing.assert_array_equal(viento[0, :, 3], [255, 0, 0])

    def test_no_sustituye_2m_por_superficie_ni_instantaneo_por_promedio(self):
        p = self.entrada(mensaje('temperatura', cambios={'typeOfLevel': 'surface', 'level': 0}),
                         mensaje('lluvia_intensidad', cambios={'stepType': 'avg', 'stepRange': '0-3'}))
        with self.assertRaises(ValueError):
            producir_campos(p, self.ruta/'no', ['temperatura'])
        self.assertFalse((self.ruta/'no').exists())

    def test_duplicados_iguales_se_registran_y_distintos_se_rechazan(self):
        p = self.entrada(mensaje('lluvia'), mensaje('lluvia'))
        self.assertEqual(leer_campos(p)['lluvia:0:3']['duplicados_iguales'], 1)
        p = self.entrada(mensaje('lluvia'), mensaje('lluvia', [7]*9))
        with self.assertRaisesRegex(ValueError, 'distintos'):
            leer_campos(p)

    def test_componentes_viento_fecha_distinta_no_se_mezclan(self):
        p = self.entrada(mensaje('u10'), mensaje('v10', cambios={'stepRange': '6'}))
        with self.assertRaisesRegex(ValueError, 'fechas o rejillas'):
            producir_campos(p, self.ruta/'no', ['viento'])
        self.assertFalse((self.ruta/'no').exists())
        self.assertEqual(list(self.ruta.glob('.campos-*')), [])

    def test_acumulados_distintos_exigen_intervalo_sin_bloquear_otras_capas(self):
        p = self.entrada(mensaje('temperatura', cambios={'stepRange': '9'}),
                         mensaje('lluvia', cambios={'stepRange': '0-9'}),
                         mensaje('lluvia', [7]*9, {'stepRange': '6-9'}))
        producir_campos(p, self.ruta/'solo-temperatura', ['temperatura'], True)
        with self.assertRaisesRegex(ValueError, 'distintos intervalos'):
            producir_campos(p, self.ruta/'ambiguo', ['lluvia'], True)
        meta = producir_campos(p, self.ruta/'seleccion', ['lluvia'], True, acumulado_desde=6)
        self.assertEqual(meta['campos'][0]['tiempo']['inicio_horas'], 6)
        self.assertEqual(meta['campos'][0]['tiempo']['fin_horas'], 9)

    def test_analisis_cero_y_tolerancia_humedad_de_empaquetado(self):
        p = self.entrada(mensaje('temperatura', cambios={'generatingProcessIdentifier': 81, 'stepRange': '0'}))
        self.assertEqual(leer_campos(p)['temperatura']['tiempo']['fin_horas'], 0)
        p = self.entrada(mensaje('temperatura', cambios={'generatingProcessIdentifier': 81}))
        with self.assertRaises(ValueError):
            leer_campos(p)
        rgba, meta = codificar_campo('humedad', [[100.000002, -.000002, 100.001]])
        np.testing.assert_array_equal(rgba[0, :, 3], [255, 255, 0])
        np.testing.assert_array_equal(rgba[0, :2, 0], [255, 0])
        self.assertEqual(meta['fuera_de_escala'], 1)

    def test_mascara_grib_orientacion_y_nivel_proceso(self):
        p = self.entrada(mensaje('humedad', [0, 1, 2, 9999, 50, 60, 70, 80, 90]))
        c = leer_campos(p)['humedad']
        self.assertTrue(np.isnan(c['valores'][1, 0]))
        self.assertEqual(c['valores'][0, 0], 0)
        self.assertEqual(c['rejilla']['oeste'], -62)
        self.assertEqual(c['rejilla']['norte'], -17)
        p = self.entrada(mensaje('humedad', cambios={'generatingProcessIdentifier': 1}))
        with self.assertRaises(ValueError):
            leer_campos(p)

    def test_no_sobrescribe_y_no_omite_campo_faltante(self):
        p = self.entrada(mensaje('temperatura'))
        destino = self.ruta/'lote'
        producir_campos(p, destino, ['temperatura'], True)
        previo = (destino/'receta.json').read_bytes()
        with self.assertRaises(ValueError):
            producir_campos(p, destino, ['temperatura'], True)
        self.assertEqual(previo, (destino/'receta.json').read_bytes())
        with self.assertRaisesRegex(ValueError, 'Falta'):
            producir_campos(p, self.ruta/'falta', ['temperatura', 'viento'])
        self.assertFalse((self.ruta/'falta').exists())

    def test_adquisicion_conjunto_es_explicita_y_limita_mensajes(self):
        self.assertIn('var_UGRD=on', parametros('2026102906', 3, True))
        self.assertNotIn('var_UGRD=on', parametros('2026102906', 3))
        m = b'GRIB\0\0\0\2'+(20).to_bytes(8, 'big')+b'7777'
        for contenido, conjunto, aceptado in ((m*12, True, True), (m*2, False, False), (m*65, True, False), (m+m[:-1], True, False)):
            conexion = Conexion(Respuesta(contenido))
            datos, recibo = descargar('2026102906', 3, lambda *a, **k: conexion, conjunto)
            self.assertEqual(datos is not None, aceptado)
            if aceptado:
                self.assertEqual(recibo['mensajes'], 12)
            self.assertEqual(len(conexion.solicitudes), 1)


if __name__ == '__main__':
    unittest.main()
