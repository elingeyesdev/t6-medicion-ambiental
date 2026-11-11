from copy import deepcopy
from decimal import Decimal
import json
from pathlib import Path
import shutil
import tempfile
import unittest
from unittest.mock import patch

from verificar_fuente import YEAR, leer_json, normalizar_serie, revisar, validar_metadatos

ROOT = Path(__file__).resolve().parents[2]
EVIDENCE = ROOT / 'docs/forestal116/evidencias'


class FuenteTest(unittest.TestCase):
    def test_captura_real_y_ejemplo_reproducible(self):
        actual = revisar(EVIDENCE)
        expected = json.loads((ROOT / 'compartido/cobertura-forestal/ejemplo-propuesto.json').read_text(encoding='utf-8'))
        self.assertEqual(actual, expected)
        self.assertEqual(actual['resumen']['anios_con_dato'], 25)
        self.assertEqual(actual['serie'][23]['perdida_ha'], '1323408.83757')
        self.assertFalse(actual['integrado_en_api'])
        self.assertFalse(actual['criterio']['adoptado'])
        self.assertIsNone(actual['cobertura']['fraccion_valida'])

    def test_revision_no_usa_red(self):
        with patch('urllib.request.urlopen', side_effect=AssertionError('red')):
            revisar(EVIDENCE)

    def test_ausencia_y_nulo_no_son_cero(self):
        data = normalizar_serie([{YEAR: 2001, 'perdida_ha': None}])
        self.assertEqual(len(data['serie']), 25)
        self.assertTrue(all(r['estado'] == 'no_informado' and r['perdida_ha'] is None for r in data['serie']))
        self.assertIsNone(data['resumen']['subtotal_informado_ha'])
        self.assertIsNone(data['resumen']['total_periodo_ha'])

    def test_cero_valido_y_cobertura_parcial(self):
        data = normalizar_serie([{YEAR: 2001, 'perdida_ha': 0}, {YEAR: 2002, 'perdida_ha': Decimal('1.234567890123456789')}])
        self.assertEqual(data['serie'][0]['estado'], 'dato')
        self.assertEqual(data['serie'][0]['perdida_ha'], '0')
        self.assertIsNone(data['resumen']['total_periodo_ha'])
        self.assertEqual(data['resumen']['subtotal_informado_ha'], '1.234567890123456789')

    def test_orden_y_total_solo_si_todos_los_anios_tienen_dato(self):
        data = normalizar_serie([{YEAR: y, 'perdida_ha': 1} for y in range(2025, 2000, -1)])
        self.assertEqual([r['anio'] for r in data['serie']], list(range(2001, 2026)))
        self.assertEqual(data['resumen']['total_periodo_ha'], '25')

    def test_rechaza_respuesta_error_y_demasiadas_filas(self):
        for bad in ({'status': 'error'}, None, 'error', [{}] * 26):
            with self.subTest(bad=bad), self.assertRaises(ValueError):
                normalizar_serie(bad)

    def test_rechaza_anios_codificados_futuros_duplicados_o_booleanos(self):
        for year in (1, 2026, True, 2001.0, '2001'):
            with self.subTest(year=year), self.assertRaises(ValueError):
                normalizar_serie([{YEAR: year, 'perdida_ha': 1}])
        with self.assertRaises(ValueError):
            normalizar_serie([{YEAR: 2001, 'perdida_ha': 1}] * 2)

    def test_rechaza_areas_negativas_texto_booleano_o_no_finitas(self):
        for area in (-1, '1', True, float('nan'), float('inf'), Decimal('-Infinity')):
            with self.subTest(area=area), self.assertRaises(ValueError):
                normalizar_serie([{YEAR: 2001, 'perdida_ha': area}])

    def test_rechaza_filas_con_otra_unidad_o_campos_inesperados(self):
        for row in ({YEAR: 2001, 'area_km2': 1}, {YEAR: 2001, 'perdida_ha': 1, 'extra': 1}, None):
            with self.subTest(row=row), self.assertRaises(ValueError):
                normalizar_serie([row])

    def test_json_estricto_no_finito_y_claves_repetidas(self):
        for raw in ('NaN', 'Infinity', '{"a":1,"a":2}'):
            with self.subTest(raw=raw), self.assertRaises(ValueError):
                leer_json(raw)

    def test_version_distinta_mutable_o_nueva_codificacion_requieren_revision(self):
        docs = [leer_json((EVIDENCE / f'{name}.json').read_bytes()) for name in ('producto', 'version', 'campos')]
        for field, value in (('version', 'latest'), ('is_mutable', True), ('dataset', 'otro')):
            changed = deepcopy(docs)
            changed[1]['data'][field] = value
            with self.subTest(field=field), self.assertRaises(ValueError):
                validar_metadatos(*changed)
        changed = deepcopy(docs)
        year = next(x for x in changed[2]['data'] if x['pixel_meaning'] == YEAR)
        year['values_table']['rows'].append({'value': 26, 'meaning': 2026})
        with self.assertRaises(ValueError):
            validar_metadatos(*changed)

    def test_captura_manipulada_no_se_valida(self):
        with tempfile.TemporaryDirectory() as tmp:
            target = Path(tmp) / 'captura'
            shutil.copytree(EVIDENCE, target)
            (target / 'serie.json').write_text('[]', encoding='utf-8')
            with self.assertRaises(ValueError):
                revisar(target)

    def test_recibo_de_otro_territorio_criterio_o_acceso_no_se_adopta(self):
        for change in ('aoi%5Bregion%5D=8', 'percent+%3E%3D+30', 'credenciales', 'http'):
            with tempfile.TemporaryDirectory() as tmp:
                target = Path(tmp) / 'captura'
                shutil.copytree(EVIDENCE, target)
                manifest = json.loads((target / 'consultas.json').read_text(encoding='utf-8'))
                if change in ('credenciales', 'http'):
                    manifest[-1][change] = True if change == 'credenciales' else 500
                else:
                    self.assertIn(change, manifest[-1]['url'])
                    manifest[-1]['url'] = manifest[-1]['url'].replace(change, change + 'INVALIDO')
                (target / 'consultas.json').write_text(json.dumps(manifest), encoding='utf-8')
                with self.subTest(change=change), self.assertRaises(ValueError):
                    revisar(target)


if __name__ == '__main__':
    unittest.main()
