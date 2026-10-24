import importlib.util
import json
from pathlib import Path
import unittest

from shapely.geometry import MultiPolygon, Point, Polygon, box

SCRIPT = Path(__file__).with_name("preparar-muestras.py")
spec = importlib.util.spec_from_file_location("preparar_muestras", SCRIPT)
modulo = importlib.util.module_from_spec(spec)
spec.loader.exec_module(modulo)


class MuestrasTest(unittest.TestCase):
    def test_concavo_no_usa_centroide_que_cae_fuera(self):
        g = Polygon([(0, 0), (6, 0), (6, 6), (4, 6), (4, 2), (2, 2), (2, 6), (0, 6)])
        self.assertFalse(g.contains(g.centroid))
        self.assertTrue(g.contains(Point(modulo.seleccionar(g))))

    def test_hueco_y_multipoligono_preservan_interior(self):
        g = Polygon([(0, 0), (10, 0), (10, 10), (0, 10)], holes=[[(3, 3), (7, 3), (7, 7), (3, 7)]])
        self.assertTrue(g.contains(Point(modulo.seleccionar(g))))
        m = MultiPolygon([g, box(20, 20, 30, 30)])
        self.assertTrue(m.contains(Point(modulo.seleccionar(m))))
        with self.assertRaises(ValueError):
            modulo.seleccionar(g, [5, 5])

    def test_no_acepta_geometria_invalida_vacia_o_punto_redondeado_al_borde(self):
        for g in (Polygon(), Point(1, 1), Polygon([(0, 0), (2, 2), (0, 2), (2, 0)]), box(0, 0, 1, 1e-8)):
            with self.subTest(geometria=g), self.assertRaises(ValueError):
                modulo.seleccionar(g)

    def test_regeneracion_reproduce_exactamente_el_catalogo_congelado(self):
        documento = json.loads((modulo.RAIZ / "compartido/sequias/muestras.json").read_text(encoding="utf-8"))
        actual = modulo.generar()
        self.assertEqual(documento, actual)
        self.assertEqual(len(actual["muestras"]), 56)
        self.assertEqual(actual["muestras"][0]["identificador"], "scz-piloto-01")
        self.assertEqual(actual["muestras"][0]["coordenadaSolicitada"], [-63.1821, -17.7833])


if __name__ == "__main__":
    unittest.main(verbosity=2)
