import copy
import json
from pathlib import Path
import unittest
from capacidad import capacidad
ROOT=Path(__file__).resolve().parents[2]
class CapacidadTest(unittest.TestCase):
    def datos(self):return json.loads((ROOT/'docs/planificacion109/datos-por-confirmar.json').read_text(encoding='utf-8'))
    def test_semana_confirmada_sin_convertir_esfuerzo_en_duracion(self):
        r=capacidad(self.datos())
        self.assertEqual(r['totalSemanaCompleta'],{'minimo':28,'maximo':42})
        self.assertEqual(r['personas'][0]['horasSemanaCompleta'],{'minimo':14,'maximo':21})
        self.assertIsNone(r['fechaEntrega']);self.assertIsNone(r['duracionCalendarioHoras']);self.assertIsNone(r['esfuerzoRestante'])
    def test_otro_calendario_cambia_solo_la_capacidad(self):
        d=self.datos();d['personas'][0]['calendarioConfirmado']['diasSemanaISO']=[1,3,5]
        self.assertEqual(capacidad(d)['totalSemanaCompleta'],{'minimo':20,'maximo':30})
    def test_dias_duplicados_no_duplican_horas(self):
        for dias in [[1,1],[0],[8],[True],[]]:
            d=self.datos();d['personas'][0]['calendarioConfirmado']['diasSemanaISO']=dias
            with self.assertRaises(ValueError):capacidad(d)
    def test_rangos_invalidos_no_generan_pronostico(self):
        for a,b in [(4,2),(-1,3),(True,3),(2,float('nan')),(2,25)]:
            d=self.datos();d['personas'][0]['horasPorDiaDeclaradas'].update(minimo=a,maximo=b)
            with self.assertRaises(ValueError):capacidad(d)
    def test_personas_duplicadas_no_se_suman(self):
        d=self.datos();d['personas'].append(copy.deepcopy(d['personas'][0]))
        with self.assertRaises(ValueError):capacidad(d)
    def test_no_modifica_la_plantilla_ni_rellena_estimaciones(self):
        d=self.datos();antes=copy.deepcopy(d);capacidad(d);self.assertEqual(d,antes)
        for a in d['trabajoRestante']:
            for e in a['estimacionesPorIntegrante']:
                self.assertIsNone(e['optimistaHoras']);self.assertIsNone(e['probableHoras']);self.assertIsNone(e['pesimistaHoras'])
if __name__=='__main__':unittest.main()
