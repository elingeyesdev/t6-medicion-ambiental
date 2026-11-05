"""Regresiones del contraste de contornos, sin red ni datos privados."""
import math
import unittest
from pathlib import Path
from tempfile import TemporaryDirectory
from reproducir_territorio import area_ring, describe, compare


class TerritorioTest(unittest.TestCase):
    def setUp(self):
        self.outer = [[-63, -18], [-62, -18], [-62, -17], [-63, -17], [-63, -18]]
        self.hole = [[-62.8, -17.8], [-62.2, -17.8], [-62.2, -17.2], [-62.8, -17.2], [-62.8, -17.8]]

    def test_hueco_se_resta_con_ambas_orientaciones(self):
        esperado = (area_ring(self.outer) - area_ring(self.hole)) / 1000000
        for hole in [self.hole, self.hole[::-1]]:
            actual = describe({"type": "Polygon", "coordinates": [self.outer, hole]})
            self.assertAlmostEqual(actual["areaGeodesicaKm2"], esperado, places=6)

    def test_polygon_y_multipolygon_representan_la_misma_area(self):
        a = describe({"type": "Polygon", "coordinates": [self.outer]})
        b = describe({"type": "MultiPolygon", "coordinates": [[self.outer]]})
        self.assertEqual(a["areaGeodesicaKm2"], b["areaGeodesicaKm2"])
        self.assertEqual(a["bbox"], [-63, -18, -62, -17])

    def test_anillo_abierto_no_finito_o_fuera_del_globo_falla(self):
        for ring in [self.outer[:-1], [[math.nan, 0]] * 4, [[181, 0]] * 4]:
            with self.subTest(ring=ring):
                with self.assertRaises(ValueError):
                    area_ring(ring)

    def test_rechaza_lineas_y_area_no_positiva(self):
        for geometry in [{"type": "LineString", "coordinates": self.outer},
                         {"type": "Polygon", "coordinates": [self.hole, self.outer]}]:
            with self.assertRaises(ValueError):
                describe(geometry)

    def test_otro_archivo_gadm_requiere_una_captura_nueva(self):
        with TemporaryDirectory() as directory:
            path = Path(directory) / "gadm.json"
            path.write_text("{}", encoding="utf-8")
            with self.assertRaisesRegex(ValueError, "no coincide con el recibo"):
                compare(path)


if __name__ == "__main__":
    unittest.main()
