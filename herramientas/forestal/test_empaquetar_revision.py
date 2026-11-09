from contextlib import redirect_stdout, redirect_stderr
import io
import hashlib
import json
from pathlib import Path
import shutil
import tempfile
import unittest
from unittest.mock import patch

from empaquetar_revision import empaquetar, main
from recorte_raster import guardar_json

ROOT = Path(__file__).resolve().parents[2]
CASO = ROOT / "docs/forestal147/evidencias/recorte-20261108"


class PaqueteTest(unittest.TestCase):
    def test_paquete_real_repetible_con_huellas_y_estado_tecnico(self):
        with tempfile.TemporaryDirectory() as t, \
                patch("urllib.request.OpenerDirector.open", side_effect=AssertionError("red")):
            identidad = empaquetar(CASO, t)
            self.assertEqual(identidad, empaquetar(CASO, t))
            carpeta = Path(t) / identidad
            raw = (carpeta / "manifiesto.json").read_bytes()
            self.assertEqual(hashlib.sha256(raw).hexdigest(), identidad)
            manifiesto = json.loads(raw)
            self.assertEqual(manifiesto["estado"], "revision_tecnica")
            self.assertFalse(manifiesto["identidad"]["criterio"]["adoptado"])
            for archivo, sha in manifiesto["archivos"].items():
                self.assertEqual(hashlib.sha256((carpeta / archivo).read_bytes()).hexdigest(), sha)
            self.assertEqual(len(list(Path(t).iterdir())), 1)

    def test_no_sustituye_un_paquete_alterado(self):
        with tempfile.TemporaryDirectory() as t:
            identidad = empaquetar(CASO, t)
            archivo = Path(t) / identidad / "perdida.geojson"
            archivo.write_bytes(b"{}")
            with self.assertRaises(ValueError):
                empaquetar(CASO, t)
            self.assertEqual(archivo.read_bytes(), b"{}")

    def test_rechaza_caso_modificado_antes_de_crear_destino(self):
        with tempfile.TemporaryDirectory() as t:
            caso = Path(t) / "caso"
            shutil.copytree(CASO, caso)
            (caso / "resultado/perdida.geojson").write_bytes(b"{}")
            destino = Path(t) / "paquetes"
            with self.assertRaises((ValueError, KeyError)):
                empaquetar(caso, destino)
            self.assertFalse(destino.exists())

    def test_otro_periodo_tiene_otra_identidad_sin_reutilizar_capa(self):
        with tempfile.TemporaryDirectory() as t:
            caso = Path(t) / "caso"
            shutil.copytree(CASO, caso)
            resumen = json.loads((caso / "resultado/resumen.json").read_text("utf-8"))
            capa = json.loads((caso / "resultado/perdida.geojson").read_text("utf-8"))
            resumen["consulta"]["hasta"] = 2024
            resumen["total_periodo_ha"] = next(f["perdida_ha"] for f in resumen["serie"] if f["anio"] == 2024)
            capa["features"] = [f for f in capa["features"] if f["properties"]["anio"] == 2024]
            resumen["conteos_pixeles"]["perdida_periodo"] = len(capa["features"])
            guardar_json(caso / "resultado/resumen.json", resumen)
            guardar_json(caso / "resultado/perdida.geojson", capa)
            destino = Path(t) / "paquetes"
            self.assertNotEqual(empaquetar(CASO, destino), empaquetar(caso, destino))
            self.assertEqual(len(list(destino.iterdir())), 2)

    def test_no_deja_paquete_parcial_si_falla_la_escritura(self):
        with tempfile.TemporaryDirectory() as t:
            with patch.object(Path, "write_bytes", side_effect=OSError("sin espacio")):
                with self.assertRaises(OSError):
                    empaquetar(CASO, t)
            self.assertEqual(list(Path(t).iterdir()), [])

    def test_cli_error_no_anuncia_identidad(self):
        with tempfile.TemporaryDirectory() as t:
            salida = io.StringIO()
            with redirect_stdout(salida), redirect_stderr(io.StringIO()):
                self.assertEqual(main(["--caso", t, "--destino", str(Path(t)/"paquetes")]), 1)
            self.assertEqual(salida.getvalue(), "")


if __name__ == "__main__":
    unittest.main()
