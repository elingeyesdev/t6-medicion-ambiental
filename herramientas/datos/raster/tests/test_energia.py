"""Energía NCEP: no es índice UV ni evaporación acumulada en mm."""
import json
import os
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile
import unittest

import numpy as np

BASE = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(BASE))
from campos_gfs import CAPAS_ENERGIA, codificar_campo, producir_campos
from adquirir_gfs import parametros
from test_campos import mensaje


class EnergiaTest(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.raiz = Path(self.tmp.name)

    def tearDown(self):
        self.tmp.cleanup()

    def entrada(self, *datos):
        ruta = self.raiz/'energia.grib2'
        ruta.write_bytes(b''.join(datos))
        return ruta

    def test_campos_con_unidades_periodo_y_publicador_php_reales(self):
        entrada = self.entrada(mensaje('radiacion_solar'), mensaje('evaporacion_potencial'))
        salida = self.raiz/'lote'
        resultado = producir_campos(entrada, salida, list(CAPAS_ENERGIA), controlado=True, promedio_desde=0)
        self.assertEqual([c['unidad_salida'] for c in resultado['campos']], ['W/m²', 'W/m²'])
        self.assertTrue(all(c['unidad_tabla_local_ncep'] for c in resultado['campos']))
        cuadros = json.loads((salida/'receta.json').read_text())['cuadros']
        solar, evapo = cuadros
        self.assertEqual(solar['intervalo']['tipo_paso'], 'avg')
        self.assertEqual(solar['intervalo']['desde'], '2026-10-29T06:00:00Z')
        self.assertEqual(solar['intervalo']['hasta'], '2026-10-29T09:00:00Z')
        self.assertEqual(evapo['intervalo']['inicio_horas'], 3)
        self.assertEqual(evapo['intervalo']['tipo_paso'], 'instant')
        self.assertNotIn('uv', [c['capa'] for c in cuadros])
        php = os.environ.get('RASTER_PHP') or shutil.which('php')
        self.assertIsNotNone(php)
        p = subprocess.run([php, str(BASE/'tests/publicador_real.php'), str(salida), '2'], capture_output=True, text=True, timeout=15)
        self.assertEqual(p.returncode, 0, p.stdout+p.stderr)

    def test_cero_exacto_signo_mascara_y_extremos_sin_saturar(self):
        valores = np.array([[-500, 0, 1500, np.nan, 1501, -501]])
        p, meta = codificar_campo('evaporacion_potencial', valores)
        np.testing.assert_array_equal(p[0, :, 3], [255, 255, 255, 0, 0, 0])
        np.testing.assert_array_equal(p[0, :3, 0], [0, 64, 255])
        self.assertLess(meta['error_maximo_cuantizacion_unidad_salida'], 4)
        solar, _ = codificar_campo('radiacion_solar', [[0, 1600, np.nan, -1, 1601]])
        np.testing.assert_array_equal(solar[0, :, 3], [255, 255, 0, 0, 0])
        np.testing.assert_array_equal(solar[0, :2, 0], [0, 255])

    def test_no_confunde_promedio_con_instante_nivel_o_tabla_local(self):
        for clave, cambio in [
            ('radiacion_solar', {'stepType': 'instant', 'stepRange': '3'}),
            ('evaporacion_potencial', {'localTablesVersion': 0}),
            ('evaporacion_potencial', {'typeOfLevel': 'heightAboveGround', 'level': 2}),
            ('radiacion_solar', {'centre': 'ecmf'}),
        ]:
            with self.subTest(clave=clave, cambio=cambio):
                entrada = self.entrada(mensaje(clave, cambios=cambio))
                with self.assertRaises(ValueError):
                    producir_campos(entrada, self.raiz/'no', [clave], controlado=True)
                self.assertFalse((self.raiz/'no').exists())

    def test_periodos_distintos_exigen_seleccion_explicita(self):
        entrada = self.entrada(mensaje('radiacion_solar'), mensaje('radiacion_solar', cambios={'stepRange': '1-3'}))
        with self.assertRaisesRegex(ValueError, 'distintos promedios'):
            producir_campos(entrada, self.raiz/'no', ['radiacion_solar'], controlado=True)
        r = producir_campos(entrada, self.raiz/'si', ['radiacion_solar'], controlado=True, promedio_desde=1)
        self.assertEqual(r['campos'][0]['tiempo']['inicio_horas'], 1)

    def test_peticion_solo_energia_no_pide_temperatura_ni_se_mezcla(self):
        ruta = parametros('2026103000', 3, energia=True)
        self.assertIn('var_DSWRF=on', ruta)
        self.assertIn('var_PEVPR=on', ruta)
        self.assertNotIn('var_TMP', ruta)
        with self.assertRaises(ValueError):
            parametros('2026103000', 3, conjunto=True, energia=True)


if __name__ == '__main__':
    unittest.main()
