"""Verifica respuestas HTTP conservadas, contrato y trazabilidad; no realiza red."""
import hashlib
import json
import subprocess
import sys
import unittest
from pathlib import Path
from jsonschema import Draft202012Validator, FormatChecker

RAIZ = Path(__file__).resolve().parents[2]
CONTRATO = RAIZ / "compartido/api/sequias"
EVIDENCIA = RAIZ / "docs/evidencias/api02-http"

def leer(archivo):
    return json.loads(archivo.read_text(encoding="utf-8"))

class ContratoHttpTest(unittest.TestCase):
    def test_respuestas_http_validan_esquemas_y_formatos(self):
        for nombre in ("consulta", "detalle", "resumen", "departamento-parcial"):
            with self.subTest(respuesta=nombre):
                esquema = leer(CONTRATO / ("resumen.schema.json" if nombre == "resumen" else "respuesta.schema.json"))
                Draft202012Validator.check_schema(esquema)
                Draft202012Validator(esquema, format_checker=FormatChecker()).validate(leer(EVIDENCIA / f"{nombre}.json"))

    def test_manifest_conserva_cuatro_200_reales_y_hashes(self):
        manifest = leer(EVIDENCIA / "manifest.json")
        self.assertEqual(len(manifest), 4)
        for entrada in manifest:
            self.assertEqual(entrada["estado"], 200)
            self.assertTrue(entrada["url"].startswith("http://127.0.0.1:5789/api/v1/sequias"))
            self.assertIn("no-store", entrada["cacheControl"])
            self.assertEqual(hashlib.sha256((EVIDENCIA / entrada["archivo"]).read_bytes()).hexdigest(), entrada["sha256"])

    def test_resumen_detalle_y_cobertura_parcial_no_inventan_superficie(self):
        consulta = leer(EVIDENCIA / "consulta.json")
        detalle = leer(EVIDENCIA / "detalle.json")
        resumen = leer(EVIDENCIA / "resumen.json")
        departamento = leer(EVIDENCIA / "departamento-parcial.json")
        self.assertEqual(consulta["datos"], detalle["datos"])
        self.assertEqual(consulta["resumen"], resumen["resumen"])
        self.assertNotIn("datos", resumen)
        self.assertEqual(departamento["cobertura"]["muestrasSeleccionadas"], 56)
        self.assertEqual(len(departamento["cobertura"]["muestrasSinPreparar"]), 55)
        for r in (consulta, detalle, resumen, departamento):
            s = r["resumen"]
            self.assertEqual(s["totalMuestras"], s["muestrasConIndice"] + s["muestrasSinIndice"])
            self.assertEqual(s["totalMuestras"] + len(r["cobertura"]["muestrasSinPreparar"]), r["cobertura"]["muestrasSeleccionadas"])
            self.assertIsNone(r["cobertura"]["superficieEvaluadaKm2"])
        self.assertEqual(consulta["datos"][0]["territorioContexto"], "BO070101")
        self.assertEqual(consulta["datos"][0]["municipioDelCentroCelda"], "BO070103")

    def test_captura_original_objetivo_y_contraste_con_referencia_revisada(self):
        r = leer(EVIDENCIA / "consulta.json")
        objetivo = next(x for x in r["fuentes"][0]["capturas"] if x["periodo"]["desde"] == "2024-07-01")
        self.assertEqual(hashlib.sha256((EVIDENCIA / "objetivo-original.json").read_bytes()).hexdigest(), objetivo["sha256"])
        serie = leer(EVIDENCIA / "objetivo-original.json")
        self.assertEqual(len(serie["daily"]["time"]), 92)
        self.assertAlmostEqual(sum(serie["daily"]["precipitation_sum"]), r["datos"][0]["precipitacionAcumuladaMm"], places=8)
        # El valor del piloto acordado en #35; tolerancia del portado PHP.
        self.assertAlmostEqual(r["datos"][0]["spi"]["valor"], -0.6410480784168507, delta=1e-6)
        self.assertEqual(r["datos"][0]["spi"]["categoria"], "normal")
        self.assertTrue(r["consulta"]["obtencion"]["esRespuestaHttp"])

    def test_esquemas_se_reproducen_sin_cambiar_focos_o_propuesta(self):
        subprocess.run([sys.executable, str(RAIZ / "herramientas/sequias/publicar-contrato.py"), "--check"], check=True, capture_output=True)

if __name__ == "__main__":
    unittest.main(verbosity=2)
