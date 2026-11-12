"""Contrato candidato: no calcula ni certifica resultados ambientales."""
import copy
import json
from pathlib import Path
import unittest
from jsonschema import Draft202012Validator, FormatChecker
BASE = Path(__file__).resolve().parent
SCHEMA = json.loads((BASE / 'respuesta.schema.json').read_text(encoding='utf-8'))
VALIDADOR = Draft202012Validator(SCHEMA, format_checker=FormatChecker())
def validar(d):
    VALIDADOR.validate(d)
    if d['comparacion']['referencia'] >= d['comparacion']['comparacion']:
        raise ValueError('Par invertido')
    c, r = d['cobertura'], d['resumen']
    if c['areaEstudiadaKm2'] <= 0 or c['areaComparableKm2'] > c['areaEstudiadaKm2']:
        raise ValueError('Cobertura incompatible')
    if abs(c['fraccionComparable'] - c['areaComparableKm2'] / c['areaEstudiadaKm2']) > 1e-8:
        raise ValueError('Fraccion incompatible')
    v = [r[k] for k in ['aguaPersistenteKm2', 'aguaNuevaKm2', 'aguaRetiradaKm2']]
    if d['estado'] == 'sin_datos':
        if v != [None, None, None] or c['areaComparableKm2'] != 0:
            raise ValueError('Faltantes no son ceros')
    else:
        if None in v or c['areaComparableKm2'] <= 0 or sum(v) > c['areaComparableKm2'] + 1e-8:
            raise ValueError('Resumen fuera de cobertura')
        if (d['estado'] == 'sin_cambios') != (v[1] == 0 and v[2] == 0):
            raise ValueError('Estado contradice cambios')
    ids = set()
    for f in d['capa']['features']:
        if f['id'] in ids: raise ValueError('ID duplicado')
        ids.add(f['id'])
        for a in f['geometry']['coordinates']:
            if a[0] != a[-1]: raise ValueError('Anillo abierto')
        clase = f['properties']['clase']
        if d['estado'] == 'sin_datos' and clase != 'no_comparable':
            raise ValueError('Agua sin datos')
        if d['estado'] == 'sin_cambios' and clase in ['agua_nueva', 'agua_retirada']:
            raise ValueError('Capa contradice estado')
    return d
class ContratoTest(unittest.TestCase):
    def ejemplo(self, n='cambio'):
        return json.loads((BASE / 'ejemplos' / (n+'.json')).read_text(encoding='utf-8'))
    def test_tres_estados(self):
        for n in ['cambio', 'sin-cambios', 'sin-datos']:
            self.assertEqual('prueba_controlada', validar(self.ejemplo(n))['tipoEvidencia'])
    def test_fechas(self):
        for f in ['2025-02-30', '2024-08-01']:
            d = self.ejemplo(); d['comparacion']['comparacion'] = f
            with self.assertRaises(Exception): validar(d)
    def test_faltante_no_es_cero(self):
        d = self.ejemplo('sin-datos'); d['resumen']['aguaNuevaKm2'] = 0
        with self.assertRaises(ValueError): validar(d)
    def test_superficie_fuera_de_cobertura(self):
        d = self.ejemplo(); d['resumen']['aguaNuevaKm2'] = 10
        with self.assertRaises(ValueError): validar(d)
    def test_fraccion(self):
        d = self.ejemplo(); d['cobertura']['fraccionComparable'] = 1
        with self.assertRaises(ValueError): validar(d)
    def test_geometria(self):
        for c in [[181, 0], [-63.2, -17.7]]:
            d = self.ejemplo(); d['capa']['features'][0]['geometry']['coordinates'][0][0] = c
            with self.assertRaises(Exception): validar(d)
    def test_no_confirma_inundacion_ni_expone_rutas(self):
        for k,v in [('inundacionConfirmada', True), ('archivoPrivado', '/tmp/escena.tif')]:
            d = self.ejemplo(); d['resumen'][k] = v
            with self.assertRaises(Exception): validar(d)
    def test_ids_y_clases(self):
        d = self.ejemplo(); d['capa']['features'].append(copy.deepcopy(d['capa']['features'][0]))
        with self.assertRaises(ValueError): validar(d)
        d = self.ejemplo('sin-cambios'); d['capa']['features'][0]['properties']['clase'] = 'agua_nueva'
        with self.assertRaises(ValueError): validar(d)
if __name__ == '__main__': unittest.main()
