import hashlib
import json
import os
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile
import unittest

import numpy as np
from PIL import Image

BASE = Path(__file__).resolve().parents[1]
RAIZ = BASE.parents[2]
sys.path.insert(0, str(BASE))
from producir import codificar, producir, seleccionar
from fixtures import dataset_era5, grib_controlado, netcdf_controlado


class ProductorTest(unittest.TestCase):
    def setUp(self):
        carpeta = RAIZ / "tmp/pruebas-productor"
        carpeta.mkdir(parents=True, exist_ok=True)
        self.temporal = tempfile.TemporaryDirectory(dir=carpeta)
        self.ruta = Path(self.temporal.name)

    def tearDown(self):
        self.temporal.cleanup()

    def test_grib_real_controlado_mascara_escala_y_georreferenciacion(self):
        origen = self.ruta / "fixture.grib2"
        grib_controlado(origen)
        salida = self.ruta / "lote"
        meta = producir(origen, salida, "gfs", controlado=True)
        self.assertEqual(meta["tiempo"]["instante"], "2026-10-27T06:00:00Z")
        self.assertEqual(meta["rejilla"], {"proyeccion": "EPSG:4326", "registro": "nodos", "filas": "norte-sur",
                                           "oeste": -82., "este": -81.5, "sur": -17.5, "norte": -17.})
        self.assertEqual(meta["estadisticas"]["validos"], 7)
        self.assertEqual(meta["estadisticas"]["ausentes_no_finitos"], 1)
        self.assertEqual(meta["estadisticas"]["fuera_de_escala"], 1)
        pixeles = np.asarray(Image.open(salida / meta["salida"]["archivo"]))
        np.testing.assert_array_equal(pixeles[:, :, 3], [[255, 255, 255], [0, 0, 255], [255, 255, 255]])
        self.assertTrue(np.all(pixeles[pixeles[:, :, 3] == 0] == 0))
        self.assertEqual(meta["origen"], "controlado")
        self.assertIn("Fixture", meta["producto"])

    def test_productor_es_reproducible_y_no_sobrescribe(self):
        origen = self.ruta / "fixture.grib2"
        grib_controlado(origen)
        a, b = self.ruta / "uno", self.ruta / "dos"
        producir(origen, a, "gfs", controlado=True)
        producir(origen, b, "gfs", controlado=True)
        self.assertEqual(sorted(p.relative_to(a) for p in a.rglob("*") if p.is_file()), sorted(p.relative_to(b) for p in b.rglob("*") if p.is_file()))
        for archivo in (p for p in a.rglob("*") if p.is_file()):
            self.assertEqual(archivo.read_bytes(), (b / archivo.relative_to(a)).read_bytes())
        anterior = (a / "receta.json").read_bytes()
        with self.assertRaises(ValueError):
            producir(origen, a, "gfs", controlado=True)
        self.assertEqual(anterior, (a / "receta.json").read_bytes())

    def test_netcdf_local_exige_fecha_exacta_respeta_fill_y_orienta_coordenadas(self):
        origen = self.ruta / "fixture.nc"
        netcdf_controlado(origen)
        with self.assertRaisesRegex(ValueError, "fixture"):
            producir(origen, self.ruta / "sin-marca", "era5", "2026-10-27T12:00:00Z")
        with self.assertRaisesRegex(ValueError, "varias fechas"):
            producir(origen, self.ruta / "ambiguo", "era5", controlado=True)
        with self.assertRaisesRegex(ValueError, "no existe"):
            producir(origen, self.ruta / "ausente", "era5", "2026-10-27T12:30:00Z", controlado=True)
        meta = producir(origen, self.ruta / "lote", "era5", "2026-10-27T12:00:00Z", controlado=True)
        self.assertEqual(meta["rejilla"]["oeste"], -62)
        self.assertEqual(meta["rejilla"]["norte"], -17)
        pixeles = np.asarray(Image.open(self.ruta / "lote" / meta["salida"]["archivo"]))
        self.assertEqual(int(pixeles[1, 1, 3]), 0)  # Faltante al sur, después de invertir el eje real.
        self.assertEqual(int(pixeles[1, 0, 3]), 255)  # 0 °C no equivale a faltante.
        self.assertEqual(meta["estadisticas"]["validos"], 5)

    def test_escala_limites_cero_nulos_y_error_maximo(self):
        celsius = np.array([[-50, -10, 0, 10, 50, np.nan, np.inf, -60, 60]], dtype=float)
        rgba, estadisticas = codificar(celsius + 273.15)
        np.testing.assert_array_equal(rgba[0, :5, 3], [255] * 5)
        np.testing.assert_array_equal(rgba[0, 5:, 3], [0] * 4)
        self.assertEqual(int(rgba[0, 0, 0]), 0)
        self.assertEqual(int(rgba[0, 4, 0]), 255)
        np.testing.assert_array_less(np.abs(rgba[0, :5, 0].astype(float) / 255 * 100 - 50 - celsius[0, :5]), .196079)
        self.assertEqual(estadisticas["fuera_de_escala"], 2)
        with self.assertRaises(ValueError):
            codificar(np.array([[np.nan, 500]]))

    def test_faltantes_irregularidad_unidades_nivel_y_dimensiones_se_rechazan(self):
        base = dataset_era5().isel(valid_time=[0])
        invalidos = []
        otro = base.copy(deep=True)
        otro.t2m.attrs["units"] = "degC"
        invalidos.append(otro)
        invalidos.append(base.assign_coords(longitude=[298, 299, 301]))
        invalidos.append(base.assign_coords(longitude=[298, 298, 300]))
        invalidos.append(base.assign_coords(latitude=[np.nan, -17]))
        invalidos.append(base.expand_dims(member=[0]))
        invalidos.append(base.rename({"t2m": "otra"}))
        otro = base.copy(deep=True)
        otro.t2m.attrs["GRIB_paramId"] = 130
        otro.t2m.attrs["long_name"] = "Temperature at pressure level"
        invalidos.append(otro)
        for ds in invalidos:
            with self.subTest(ds=str(ds.dims)), self.assertRaises(ValueError):
                seleccionar(ds, "era5")
        origen = self.ruta / "nivel.grib2"
        grib_controlado(origen, {"centre": "ecmf"})
        with self.assertRaises(ValueError):
            producir(origen, self.ruta / "nivel", "gfs", controlado=True)

    def test_error_no_deja_receta_ni_salida_parcial(self):
        origen = self.ruta / "malo.grib2"
        origen.write_bytes(b"No es un GRIB")
        with self.assertRaises((ValueError, EOFError)):
            producir(origen, self.ruta / "lote", "gfs", controlado=True)
        self.assertFalse((self.ruta / "lote").exists())
        self.assertEqual([], list(self.ruta.glob(".raster-*")))

    def test_salida_cumple_publicador_php_real_de_62(self):
        origen = self.ruta / "fixture.grib2"
        grib_controlado(origen)
        meta = producir(origen, self.ruta / "lote", "gfs", controlado=True)
        php = os.environ.get("RASTER_PHP") or shutil.which("php")
        self.assertIsNotNone(php, "Configura RASTER_PHP para comprobar el publicador real.")
        proceso = subprocess.run([php, str(BASE / "tests/publicador_real.php"), str(self.ruta / "lote")], capture_output=True, text=True, timeout=15)
        self.assertEqual(proceso.returncode, 0, proceso.stdout + proceso.stderr)
        r = json.loads(proceso.stdout)
        self.assertEqual(r["cuadros"], 1)
        self.assertEqual(r["sha256"], meta["salida"]["sha256"])
        self.assertEqual(r["rejilla"], meta["rejilla"])

    def test_cli_sin_entrada_valida_no_escribe(self):
        proceso = subprocess.run([sys.executable, str(BASE / "producir.py"), "gfs", "--entrada", str(self.ruta / "ausente"), "--salida", str(self.ruta / "lote")], capture_output=True, text=True, timeout=15)
        self.assertEqual(proceso.returncode, 2)
        self.assertFalse((self.ruta / "lote").exists())


if __name__ == "__main__":
    unittest.main()
