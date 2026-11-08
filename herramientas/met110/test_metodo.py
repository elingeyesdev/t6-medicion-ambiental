from copy import deepcopy
from io import BytesIO
import json
from pathlib import Path
import shutil
import tempfile
import unittest
from unittest.mock import Mock

import numpy as np
from PIL import Image, TiffImagePlugin

from escenas import ROOT, escenas, parejas, revisar
from calidad import MAX_ASSET, clases_validas, descargar_scl, leer_recorte, resumen_calidad

EVIDENCE = ROOT / "docs/inundaciones110/evidencias"


class EscenasTest(unittest.TestCase):
    def test_catalogo_real_y_candidatas_se_reproducen(self):
        result = revisar(EVIDENCE)
        frozen = json.loads((EVIDENCE / "candidatas.json").read_text(encoding="utf-8"))
        self.assertEqual(result, frozen)
        self.assertEqual({k: len(v) for k, v in result["escenas"].items()}, {"A": 8, "B": 10})
        self.assertEqual(result["haySiguientePagina"], {"A": False, "B": False})
        self.assertFalse(result["metodoAprobado"])
        self.assertTrue(all(not p["aprobada"] for p in result["parejasCandidatas"]))
        self.assertIn("20250225", result["parejasCandidatas"][0]["referencia"])

    def test_no_parear_plataformas_mallas_orbitas_o_baselines_distintos(self):
        result = revisar(EVIDENCE)
        pair = result["parejasCandidatas"][0]
        a = next(x for x in result["escenas"]["A"] if x["id"] == pair["referencia"])
        b = next(x for x in result["escenas"]["B"] if x["id"] == pair["comparacion"])
        for key in ("plataforma", "tile", "orbita", "baseline", "epsg"):
            changed = deepcopy(b)
            changed[key] = "otro"
            with self.subTest(key=key):
                self.assertEqual(parejas([a], [changed]), [])
        changed = deepcopy(b)
        changed["scl"]["proj:transform"][2] += 20
        self.assertEqual(parejas([a], [changed]), [])

    def test_no_parear_bbox_parcial_o_resolucion_distinta(self):
        result = revisar(EVIDENCE)
        a = next(x for x in result["escenas"]["A"] if x["bboxContieneArea"])
        b = deepcopy(a)
        b["fecha"] = "2025-03-07T00:00:00Z"
        for changed in (dict(b, bboxContieneArea=False), deepcopy(b)):
            if changed["bboxContieneArea"]:
                changed["scl"]["gsd"] = 60
            self.assertEqual(parejas([a], [changed]), [])

    def test_pagina_incompleta_no_es_catalogo_completo(self):
        raw = json.loads((EVIDENCE / "A.json").read_text(encoding="utf-8"))
        raw["links"].append({"rel": "next", "href": "https://planetarycomputer.microsoft.com/api/stac/v1/search"})
        _, next_page = escenas(json.dumps(raw).encode(), "application/geo+json", 200, "A")
        self.assertTrue(next_page)

    def test_html_y_fecha_fuera_de_ventana_no_son_cero_escenas(self):
        with self.assertRaises(ValueError):
            escenas(b"<html>rechazo</html>", "text/html", 200, "A")
        raw = json.loads((EVIDENCE / "A.json").read_text(encoding="utf-8"))
        raw["features"][0]["properties"]["datetime"] = "2024-09-03T00:00:00Z"
        with self.assertRaises(ValueError):
            escenas(json.dumps(raw).encode(), "application/geo+json", 200, "A")

    def test_recibo_alterado_otra_consulta_y_archivo_repetido_se_rechazan(self):
        for change in ("bytes", "url", "duplicado"):
            with tempfile.TemporaryDirectory() as temp:
                target = Path(temp) / "e"
                shutil.copytree(EVIDENCE, target)
                manifest = json.loads((target / "consultas.json").read_text(encoding="utf-8"))
                if change == "bytes":
                    (target / "A.json").write_text("{}", encoding="utf-8")
                elif change == "url":
                    manifest[0]["url"] += "&otro=1"
                else:
                    manifest[1] = manifest[0]
                (target / "consultas.json").write_text(json.dumps(manifest), encoding="utf-8")
                with self.subTest(change=change), self.assertRaises(ValueError):
                    revisar(target)


class CalidadTest(unittest.TestCase):
    def test_faltantes_sombras_nubes_y_nieve_no_comparables(self):
        a = np.arange(12, dtype=np.uint8).reshape(3, 4)
        mask = clases_validas(a)
        self.assertEqual(np.where(mask.ravel())[0].tolist(), [4, 5, 6])
        result = resumen_calidad(a, a)
        self.assertEqual(result["fraccionComparableAmbas"], 0.25)
        self.assertIsNone(result["pixelesAgua"])
        self.assertIsNone(result["superficieInundadaHa"])

    def test_interseccion_no_es_union_y_no_data_no_es_agua_ausente(self):
        a = np.array([[4, 5, 0, 9]], dtype=np.uint8)
        b = np.array([[8, 6, 0, 4]], dtype=np.uint8)
        result = resumen_calidad(a, b)
        self.assertEqual(result["fraccionComparableA"], 0.5)
        self.assertEqual(result["fraccionComparableB"], 0.5)
        self.assertEqual(result["fraccionComparableAmbas"], 0.25)
        empty = resumen_calidad(np.zeros((2, 2), np.uint8), np.zeros((2, 2), np.uint8))
        self.assertEqual(empty["fraccionComparableAmbas"], 0)
        self.assertIsNone(empty["superficieInundadaHa"])

    def test_rechaza_clases_mallas_o_tipo_invalidos(self):
        for a, b in ((np.array([[12]], np.uint8), np.array([[4]], np.uint8)),
                     (np.array([[4.0]]), np.array([[4.0]])),
                     (np.ones((2, 2), np.uint8), np.ones((3, 2), np.uint8))):
            with self.assertRaises(ValueError):
                resumen_calidad(a, b)

    def test_rango_demasiado_grande_y_url_firmada_se_rechazan_sin_leer(self):
        scene = revisar(EVIDENCE)["escenas"]["A"][0]
        signed = deepcopy(scene)
        signed["scl"]["href"] += "?sig=PRUEBA"
        opener = Mock()
        with self.assertRaises(ValueError):
            descargar_scl(signed, "sig=PRUEBA", opener)
        opener.open.assert_not_called()
        response = Mock(status=206, headers={"Content-Range": f"bytes 0-{MAX_ASSET - 1}/{MAX_ASSET + 1}"})
        context = Mock()
        context.__enter__ = Mock(return_value=response)
        context.__exit__ = Mock(return_value=False)
        opener.open.return_value = context
        with self.assertRaises(ValueError):
            descargar_scl(scene, "sig=PRUEBA", opener)
        response.read.assert_not_called()

    def test_tiff_controlado_comprueba_ventana_sin_remuestrear(self):
        scene = deepcopy(revisar(EVIDENCE)["escenas"]["A"][0])
        scene["scl"]["proj:shape"] = [3200, 600]
        scene["scl"]["proj:transform"] = [20.0, 0.0, 499980.0, 0.0, -20.0, 8100040.0]
        scene["epsg"] = 32720
        tags = TiffImagePlugin.ImageFileDirectory_v2()
        tags[33550] = (20.0, 20.0, 0.0)
        tags[33922] = (0.0, 0.0, 0.0, 499980.0, 8100040.0, 0.0)
        tags[34735] = (1, 1, 0, 1, 3072, 0, 1, 32720)
        memory = BytesIO()
        Image.fromarray(np.full((3200, 600), 4, dtype=np.uint8)).save(memory, format="TIFF", tiffinfo=tags, compression="tiff_deflate")
        pixels, grid = leer_recorte(memory.getvalue(), scene)
        self.assertEqual(pixels.shape, (554, 478))
        self.assertTrue(np.all(pixels == 4))
        self.assertEqual(grid["ventanaColumnasFilas"], [54, 2575, 532, 3129])
        scene["epsg"] = 32620
        with self.assertRaises(ValueError):
            leer_recorte(memory.getvalue(), scene)


if __name__ == "__main__":
    unittest.main()
