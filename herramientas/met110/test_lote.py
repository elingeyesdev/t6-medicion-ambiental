from copy import deepcopy
import hashlib
import json
from pathlib import Path
import tempfile
import unittest
from unittest.mock import Mock, patch

import numpy as np

from lote_calidad import MAX_RED, comprobar, resumir, reusar


def caso():
    scenes = [{"id": x, "scl": {"href": "https://ejemplo.test/" + x}} for x in ("a", "b", "c")]
    catalog = {"bbox": [0, 0, 1, 1], "haySiguientePagina": {"A": False, "B": True},
               "escenas": {"A": scenes[:1], "B": scenes[1:]},
               "parejasCandidatas": [
                   {"referencia": "a", "comparacion": "b", "separacionDias": 10, "peorNubeEscena": 1, "aprobada": False},
                   {"referencia": "a", "comparacion": "c", "separacionDias": 20, "peorNubeEscena": 99, "aprobada": False}]}
    crops = {"a": np.array([[4, 5, 6, 0]], np.uint8),
             "b": np.array([[4, 8, 0, 5]], np.uint8),
             "c": np.array([[4, 5, 6, 0]], np.uint8)}
    grid = {"epsg": 32720, "ventanaColumnasFilas": [1, 2, 5, 3], "tamanoRecorte": [4, 1]}
    return catalog, crops, {k: deepcopy(grid) for k in crops}, {k: {"id": k} for k in crops}


class LoteTest(unittest.TestCase):
    def test_prioriza_visibilidad_local_aunque_la_nube_global_diga_lo_contrario(self):
        catalog, crops, grids, receipts = caso()
        result = resumir(catalog, crops, grids, receipts)
        self.assertEqual(result["cantidadParejasRevisadas"], 2)
        self.assertEqual(result["cantidadEscenasUnicas"], 3)
        self.assertEqual(result["parejasPorCalidadLocal"][0]["comparacion"], "c")
        self.assertEqual(result["parejasPorCalidadLocal"][0]["calidad"]["fraccionComparableAmbas"], .75)
        self.assertTrue(result["haySiguientePagina"]["B"])
        self.assertFalse(result["metodoAprobado"])
        self.assertIsNone(result["parejaAdoptada"])
        self.assertIsNone(result["superficieInundadaHa"])

    def test_completitud_no_oculta_un_activo_ausente(self):
        catalog, crops, grids, receipts = caso()
        for collection in (crops, grids, receipts):
            saved = collection.pop("c")
            with self.assertRaises(ValueError):
                resumir(catalog, crops, grids, receipts)
            collection["c"] = saved

    def test_ventanas_distintas_no_se_parean_por_tener_el_mismo_tamano(self):
        catalog, crops, grids, receipts = caso()
        grids["c"]["ventanaColumnasFilas"][0] += 1
        with self.assertRaises(ValueError):
            resumir(catalog, crops, grids, receipts)

    def test_cero_comparable_no_es_cero_inundacion(self):
        catalog, crops, grids, receipts = caso()
        for k in crops:
            crops[k][:] = 0
        result = resumir(catalog, crops, grids, receipts)
        self.assertTrue(all(p["calidad"]["fraccionComparableAmbas"] == 0 for p in result["parejasPorCalidadLocal"]))
        self.assertIsNone(result["pixelesAgua"])

    def test_reusa_solo_tiff_integros_y_el_href_exacto_sin_firma(self):
        scenes = {k: {"scl": {"href": "https://ejemplo.test/" + k}} for k in ("a", "b")}
        with tempfile.TemporaryDirectory() as temp:
            folder = Path(temp)
            records = []
            for label, scene_id in zip(("A", "B"), ("a", "b")):
                raw = (label * 10).encode()
                (folder / (label + ".tif")).write_bytes(raw)
                records.append({"id": scene_id, "bytes": len(raw), "sha256": hashlib.sha256(raw).hexdigest(),
                                "urlSinFirma": scenes[scene_id]["scl"]["href"]})
            receipt = folder / "recibo.json"
            receipt.write_text(json.dumps({"escenas": records}), encoding="utf-8")
            self.assertEqual(set(reusar([(receipt, folder)], scenes)), {"a", "b"})
            (folder / "A.tif").write_bytes(b"Z" * 10)
            with self.assertRaises(ValueError):
                reusar([(receipt, folder)], scenes)
            (folder / "A.tif").write_bytes(b"A" * 10)
            records[0]["urlSinFirma"] += "?sig=PRUEBA"
            receipt.write_text(json.dumps({"escenas": records}), encoding="utf-8")
            with self.assertRaises(ValueError):
                reusar([(receipt, folder)], scenes)

    def test_sin_opcion_de_red_no_inicia_descargas(self):
        catalog, _, _, _ = caso()
        opener = Mock()
        with tempfile.TemporaryDirectory() as temp, patch("lote_calidad.revisar", return_value=catalog):
            target = Path(temp) / "salida"
            with self.assertRaises(ValueError):
                comprobar(Path(temp), target, opener=opener)
            self.assertFalse(target.exists())
            opener.open.assert_not_called()

    def test_el_recibo_de_lote_reutiliza_activos_sin_recortar_su_lista(self):
        scenes = {k: {"scl": {"href": "https://ejemplo.test/" + k}} for k in ("a", "b", "c")}
        with tempfile.TemporaryDirectory() as temp:
            folder = Path(temp)
            records = []
            for scene_id in scenes:
                raw = (scene_id * 10).encode()
                filename = hashlib.sha256(scene_id.encode()).hexdigest()[:24] + ".tif"
                (folder / filename).write_bytes(raw)
                records.append({"id": scene_id, "bytes": len(raw), "sha256": hashlib.sha256(raw).hexdigest(),
                                "urlSinFirma": scenes[scene_id]["scl"]["href"]})
            receipt = folder / "recibo.json"
            batch = {"escenas": records, "cantidadEscenasUnicas": 3}
            receipt.write_text(json.dumps(batch), encoding="utf-8")
            self.assertEqual(set(reusar([(receipt, folder)], scenes)), set(scenes))
            batch["escenas"] = records[:2]
            receipt.write_text(json.dumps(batch), encoding="utf-8")
            with self.assertRaises(ValueError):
                reusar([(receipt, folder)], scenes)

    def test_presupuesto_conjunto_limita_la_siguiente_lectura_y_no_da_exito_parcial(self):
        catalog, _, _, _ = caso()
        response = Mock(status=200)
        response.read.return_value = json.dumps({"token": "sig=PRUEBA", "msft:expiry": "2099-01-01T00:00:00Z"}).encode()
        context = Mock()
        context.__enter__ = Mock(return_value=response)
        context.__exit__ = Mock(return_value=False)
        opener = Mock()
        opener.open.return_value = context
        download = Mock(side_effect=[b"12345678", ValueError("Supera el saldo.")])
        grid = {"epsg": 32720, "ventanaColumnasFilas": [1, 2, 5, 3], "tamanoRecorte": [4, 1]}
        with tempfile.TemporaryDirectory() as temp, patch("lote_calidad.revisar", return_value=catalog), \
                patch("lote_calidad.descargar_scl", download), \
                patch("lote_calidad.leer_recorte", return_value=(np.ones((1, 4), np.uint8), grid)):
            target = Path(temp) / "salida"
            with self.assertRaises(ValueError):
                comprobar(Path(temp), target, limite_red=12, consultar=True, opener=opener)
            self.assertEqual([c.kwargs["limite"] for c in download.call_args_list], [12, 4])
            self.assertFalse((target / "revision.json").exists())

    def test_no_admite_aumentar_presupuesto_o_sustituir_un_directorio_existente(self):
        catalog, _, _, _ = caso()
        with tempfile.TemporaryDirectory() as temp, patch("lote_calidad.revisar", return_value=catalog):
            for limit in (0, -1, True, MAX_RED + 1):
                with self.subTest(limit=limit), self.assertRaises(ValueError):
                    comprobar(Path(temp), Path(temp) / "nuevo", limite_red=limit)
            with self.assertRaises(FileExistsError):
                comprobar(Path(temp), Path(temp), consultar=True)


if __name__ == "__main__":
    unittest.main()
