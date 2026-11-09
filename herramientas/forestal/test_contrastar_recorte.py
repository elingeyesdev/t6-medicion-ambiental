from copy import deepcopy
from contextlib import redirect_stdout, redirect_stderr
import io
import json
from pathlib import Path
import shutil
import tempfile
import unittest
from unittest.mock import patch
from PIL import Image

from contrastar_recorte import contrastar, main

ROOT = Path(__file__).resolve().parents[2]
CASO = ROOT / "docs/forestal147/evidencias/recorte-20261108"


class ContrasteTest(unittest.TestCase):
    def copia(self, carpeta):
        ruta = Path(carpeta) / "caso"
        shutil.copytree(CASO, ruta)
        return ruta

    def cambiar(self, ruta, archivo, cambio):
        path = ruta / "resultado" / archivo
        dato = json.loads(path.read_text("utf-8"))
        cambio(dato)
        path.write_text(json.dumps(dato), encoding="utf-8")

    def test_caso_real_sin_red_y_sin_aprobar_el_metodo(self):
        with patch("urllib.request.OpenerDirector.open", side_effect=AssertionError("red")):
            informe = contrastar(CASO)
        self.assertFalse(informe["metodo_validado"])
        self.assertFalse(informe["contraste_independiente_de_campo"])
        self.assertEqual(informe["celdas_perdida_periodo"], 26)
        self.assertLess(abs(informe["diferencia_total_ha"]), 1e-7)

    def test_detecta_geometria_area_anio_y_duplicado(self):
        cambios = [
            lambda c: c["features"][0]["geometry"]["coordinates"][0][0].__setitem__(0, -60),
            lambda c: c["features"][0]["properties"].__setitem__("area_ha", 900),
            lambda c: c["features"][0]["properties"].__setitem__("anio", 2023),
            lambda c: c["features"].__setitem__(1, deepcopy(c["features"][0])),
            lambda c: c["features"].pop(),
        ]
        for cambio in cambios:
            with self.subTest(cambio=cambio), tempfile.TemporaryDirectory() as t:
                ruta = self.copia(t)
                self.cambiar(ruta, "perdida.geojson", cambio)
                with self.assertRaises(ValueError):
                    contrastar(ruta)

    def test_detecta_resumen_modificado(self):
        cambios = [
            lambda r: r.__setitem__("total_periodo_ha", 100),
            lambda r: r["serie"][0].__setitem__("perdida_ha", None),
            lambda r: r["serie"].pop(),
            lambda r: r["conteos_pixeles"].__setitem__("arbolado_base", 0),
            lambda r: r["bbox"].__setitem__(0, -60),
            lambda r: r.__setitem__("integrado_en_api", True),
            lambda r: r["criterio"].__setitem__("adoptado", True),
            lambda r: r.__setitem__("cobertura_observaciones_fraccion", 1),
        ]
        for cambio in cambios:
            with self.subTest(cambio=cambio), tempfile.TemporaryDirectory() as t:
                ruta = self.copia(t)
                self.cambiar(ruta, "resumen.json", cambio)
                with self.assertRaises(ValueError):
                    contrastar(ruta)

    def test_detecta_captura_y_png_modificados(self):
        for archivo in ["captura/06-lossyear-83338429-83549788.bin",
                        "captura/rangos.json", "resultado/lossyear.png"]:
            with self.subTest(archivo=archivo), tempfile.TemporaryDirectory() as t:
                ruta = self.copia(t)
                path = ruta / archivo
                if path.suffix == ".png":
                    Image.new("L", (64, 64), 0).save(path)
                else:
                    path.write_bytes(path.read_bytes()+b" ")
                with self.assertRaises(ValueError):
                    contrastar(ruta)

    def test_png_y_resumen_coherentes_pero_distintos_del_original_se_rechazan(self):
        with tempfile.TemporaryDirectory() as t:
            ruta = self.copia(t)
            Image.new("L", (64, 64), 100).save(ruta / "resultado/treecover2000.png")
            with self.assertRaisesRegex(ValueError, "raster original"):
                contrastar(ruta)

    def test_cli_no_sobrescribe_y_no_guarda_error(self):
        with tempfile.TemporaryDirectory() as t:
            salida = Path(t) / "contraste.json"
            args = ["--directorio", str(CASO), "--salida", str(salida)]
            with redirect_stdout(io.StringIO()), redirect_stderr(io.StringIO()):
                self.assertEqual(main(args), 0)
                anterior = salida.read_bytes()
                self.assertEqual(main(args), 1)
            self.assertEqual(salida.read_bytes(), anterior)
            ruta = self.copia(t)
            self.cambiar(ruta, "resumen.json", lambda r: r.__setitem__("total_periodo_ha", -1))
            otra = Path(t) / "error.json"
            with redirect_stdout(io.StringIO()), redirect_stderr(io.StringIO()):
                self.assertEqual(main(["--directorio", str(ruta), "--salida", str(otra)]), 1)
            self.assertFalse(otra.exists())


if __name__ == "__main__":
    unittest.main()
