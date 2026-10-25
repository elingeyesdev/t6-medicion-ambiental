"""Pruebas de metadatos sin red; no verifican clasificación ni inundaciones."""
from copy import deepcopy
from contextlib import redirect_stdout
import io
import json
import unittest

import catalogo


class CatalogoTest(unittest.TestCase):
    def setUp(self):
        self.raw = (catalogo.RAIZ / "docs/evidencias/ana01/mpc-A/respuesta.json").read_bytes()
        self.datos = json.loads(self.raw)

    def respuesta(self, datos):
        return catalogo.interpretar(json.dumps(datos).encode(), "application/geo+json", 200, "A")

    def test_originales_congelados_y_resumen_conservan_hash_parametros_y_faltantes(self):
        salida = io.StringIO()
        with redirect_stdout(salida):
            self.assertEqual(catalogo.revisar(), 0)
        self.assertIn("mpc A: 8", salida.getvalue())
        self.assertIn("mpc B: 12", salida.getvalue())
        self.assertIn("agua e inundación no calculadas", salida.getvalue())

    def test_no_hay_resultado_de_agua_al_encontrar_metadatos(self):
        r = catalogo.informe(self.raw, "application/geo+json", 200, "mpc", "A", "2026-10-25T21:36:25Z")
        self.assertEqual(len(r["escenas"]), 8)
        self.assertFalse(r["rasterDescargado"])
        self.assertFalse(r["metodoAprobado"])
        self.assertIsNone(r["pixelesAgua"])
        self.assertIsNone(r["superficieInundadaKm2"])
        self.assertTrue(all(s["pixelesAgua"] is None and s["nubesEnAreaPorcentaje"] is None for s in r["escenas"]))

    def test_http_200_html_no_equivale_a_catalogo_sin_escenas(self):
        r = catalogo.informe(b"<html>Request rejected</html>", "text/html", 200, "cdse", "A", "2026-10-25T00:00:00Z")
        self.assertEqual(r["estado"], "catalogo_no_verificado")
        self.assertEqual(r["motivo"], "RESPUESTA_NO_JSON")
        self.assertIsNone(r["escenas"])

    def test_solo_feature_collection_valida_vacia_acredita_cero_coincidencias(self):
        r = catalogo.informe(b'{"type":"FeatureCollection","features":[],"links":[]}', "application/json", 200, "mpc", "A", "2026-10-25T00:00:00Z")
        self.assertEqual(r["estado"], "consulta_valida_sin_coincidencias")
        self.assertEqual(r["escenas"], [])
        self.assertFalse(r["tieneSiguientePagina"])

    def test_fallo_http_json_invalido_y_respuesta_excesiva_no_se_interpretan(self):
        for cuerpo, tipo, estado in [(self.raw, "application/json", 401), (b'{"type":', "application/json", 200), (b"x" * (catalogo.MAX_BYTES + 1), "application/json", 200), (b'{}', "application/json", 200)]:
            with self.subTest(estado=estado, bytes=len(cuerpo)):
                with self.assertRaises(ValueError):
                    catalogo.interpretar(cuerpo, tipo, estado, "A")

    def test_fecha_fuera_de_ventana_otra_coleccion_y_duplicados_se_rechazan(self):
        cambios = [
            lambda d: d["features"][0].update({"collection": "sentinel-2-l1c"}),
            lambda d: d["features"][0]["properties"].update({"datetime": "2024-08-31T00:00:00Z"}),
            lambda d: d["features"][0]["properties"].update({"datetime": "2024-09-03T00:00:00"}),
            lambda d: d["features"].append(deepcopy(d["features"][0])),
        ]
        for cambiar in cambios:
            d = deepcopy(self.datos)
            cambiar(d)
            with self.assertRaises(ValueError):
                self.respuesta(d)

    def test_geometria_y_nubosidad_incompatibles_no_pasan_como_datos_validos(self):
        for campo, valor in [("bbox", [-60, -12, -59, -11]), ("bbox", [float("nan"), -18, -62, -17]), ("nubes", 101), ("nubes", -1), ("nubes", "0")]:
            d = deepcopy(self.datos)
            if campo == "bbox":
                d["features"][0]["bbox"] = valor
            else:
                d["features"][0]["properties"]["eo:cloud_cover"] = valor
            with self.assertRaises(ValueError):
                self.respuesta(d)

    def test_nubosidad_desconocida_no_se_convierte_en_cero(self):
        self.datos["features"][0]["properties"].pop("eo:cloud_cover", None)
        self.assertIsNone(self.respuesta(self.datos)["escenas"][0]["nubesEscenaPorcentaje"])

    def test_ausencia_scl_no_implica_activo_descargable(self):
        self.datos["features"][0]["assets"].pop("SCL")
        r = self.respuesta(self.datos)["escenas"][0]
        self.assertFalse(r["sclDisponibleEnMetadatos"])
        self.assertIsNone(r["sclUrlSinFirma"])

    def test_no_se_guardan_urls_firmadas_o_credenciales_en_el_resumen(self):
        for url in ["https://example.org/SCL.tif?sig=token", "https://usuario:clave@example.org/SCL.tif", "http://example.org/SCL.tif"]:
            d = deepcopy(self.datos)
            d["features"][0]["assets"]["SCL"]["href"] = url
            with self.assertRaises(ValueError):
                self.respuesta(d)
        self.datos["features"][0]["assets"]["B04"]["href"] = "https://example.org/B04.tif?sig=no-publicar"
        with self.assertRaises(ValueError):
            self.respuesta(self.datos)

    def test_paginacion_pendiente_se_conserva_y_no_se_descarga_automaticamente(self):
        self.datos["links"].append({"rel": "next", "href": "https://example.org/otra-pagina"})
        self.assertTrue(self.respuesta(self.datos)["tieneSiguientePagina"])
        self.assertIsNone(catalogo.SinRedirecciones().redirect_request(None, None, 302, "", {}, "https://example.org"))


if __name__ == "__main__":
    unittest.main()
