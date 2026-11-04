"""Contratos del export revisado; solo fixtures sintéticos y archivos temporales."""
import importlib.util
import json
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest

BASE = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location("normalizar", BASE / "normalizar-historial.py")
modulo = importlib.util.module_from_spec(spec)
spec.loader.exec_module(modulo)


class NormalizarTest(unittest.TestCase):
    def setUp(self):
        self.filas = json.loads((BASE / "ejemplos/export-revisado.json").read_text(encoding="utf-8"))
        self.mapa = json.loads((BASE / "ejemplos/mapa-localidades.json").read_text(encoding="utf-8"))

    def test_normaliza_zona_sin_inferir_procedencia_y_retira_identificadores(self):
        self.filas[0].update(ciudad="Ejemplo", latitud=-17, longitud=-63)
        a = modulo.normalizar(self.filas, self.mapa)
        self.assertEqual(a, modulo.normalizar(self.filas, self.mapa))
        self.assertEqual(a["lecturas"][0]["tiempo"], "2026-10-27T12:00:00Z")
        self.assertEqual(a["lecturas"][0]["localidad"], "santacruz")
        self.assertNotIn("localidad_id", a["lecturas"][0])
        self.assertNotIn("ciudad", a["lecturas"][0])
        self.assertTrue(all(f["origen"] == "simulado" for f in a["lecturas"]))

    def test_export_del_endpoint_original_sin_fuente_no_se_puede_certificar(self):
        del self.filas[0]["origen"]
        with self.assertRaises(ValueError):
            modulo.normalizar(self.filas, self.mapa)

    def test_rechaza_faltantes_escalas_no_equivalentes_cuentas_nan_y_fecha_sin_zona(self):
        for cambios in [{"metrica": "aqi", "unidad": "european_aqi"}, {"valor": float("nan")},
                        {"valor": 10000}, {"valor": True}, {"usuario": "no copiar"},
                        {"fuente": "https://privado.test/clave"}, {"localidad_id": "ausente"},
                        {"ts": "2026-10-27T12:00:00"}, {"ts": "2026-02-30T12:00:00Z"}]:
            with self.subTest(cambios=cambios):
                fila = self.filas[0] | cambios
                with self.assertRaises((ValueError, TypeError)):
                    modulo.normalizar([fila], self.mapa)

    def test_limite_vacio_y_lotes_excesivos(self):
        for filas in [[], self.filas * 501]:
            with self.assertRaises(ValueError):
                modulo.normalizar(filas, self.mapa)

    def test_cli_no_sobrescribe_y_comprobar_reproduce_huella(self):
        carpeta = modulo.RAIZ / "tmp/pruebas-normalizador"
        carpeta.mkdir(parents=True, exist_ok=True)
        with tempfile.TemporaryDirectory(dir=carpeta) as temporal:
            salida = Path(temporal) / "historial.json"
            cmd = [sys.executable, str(BASE / "normalizar-historial.py"), str(BASE / "ejemplos/export-revisado.json"),
                   "--mapa", str(BASE / "ejemplos/mapa-localidades.json"), "--salida", str(salida)]
            primero = subprocess.run(cmd, capture_output=True, text=True)
            self.assertEqual(primero.returncode, 0, primero.stderr)
            contenido = salida.read_bytes()
            repetir = subprocess.run(cmd, capture_output=True, text=True)
            self.assertEqual(repetir.returncode, 2)
            self.assertEqual(contenido, salida.read_bytes())
            comprobar = subprocess.run(cmd + ["--comprobar"], capture_output=True, text=True)
            self.assertEqual(comprobar.returncode, 0, comprobar.stderr)
            self.assertEqual(primero.stdout.split("SHA-256: ")[1].split(".")[0], comprobar.stdout.split("SHA-256: ")[1].split(".")[0])


if __name__ == "__main__":
    unittest.main()
