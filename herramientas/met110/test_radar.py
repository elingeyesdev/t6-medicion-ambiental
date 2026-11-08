import hashlib
import json
from pathlib import Path
import shutil
import tempfile
import unittest

from escenas import ROOT
from radar import revisar, sin_secretos

EVIDENCE = ROOT / "docs/inundaciones110/evidencias/radar"


class RadarTest(unittest.TestCase):
    def test_catalogo_real_se_repite_sin_leer_pixeles_ni_afirmar_acceso(self):
        r = revisar(EVIDENCE)
        self.assertEqual({k: len(v) for k, v in r["escenas"].items()}, {"A": 1, "B": 2})
        self.assertEqual(r["haySiguientePagina"], {"A": False, "B": False})
        self.assertFalse(r["bandasDescargadas"])
        self.assertFalse(r["accesoRasterVerificado"])
        self.assertFalse(r["metodoAprobado"])
        self.assertIsNone(r["superficieInundadaHa"])
        # Misma plataforma/órbita no implica arrays del mismo tamaño ni origen.
        a, b = r["escenas"]["A"][0], r["escenas"]["B"][0]
        self.assertEqual(a["orbitaRelativa"], b["orbitaRelativa"])
        self.assertNotEqual(a["shape"], b["shape"])
        self.assertNotEqual(a["transform"], b["transform"])

    def cambiar(self, folder, callback):
        p = folder / "B.json"
        doc = json.loads(p.read_text(encoding="utf-8"))
        callback(doc)
        raw = json.dumps(doc).encode()
        p.write_bytes(raw)
        receipt_path = folder / "consultas.json"
        receipts = json.loads(receipt_path.read_text(encoding="utf-8"))
        item = next(x for x in receipts if x["archivo"] == "B.json")
        item.update(bytes=len(raw), sha256=hashlib.sha256(raw).hexdigest())
        receipt_path.write_text(json.dumps(receipts), encoding="utf-8")

    def test_pagina_siguiente_no_se_oculta_como_busqueda_agotada(self):
        with tempfile.TemporaryDirectory() as temp:
            target = Path(temp) / "e"
            shutil.copytree(EVIDENCE, target)
            self.cambiar(target, lambda x: x["links"].append({"rel": "next", "href": "https://ejemplo.test/next"}))
            self.assertTrue(revisar(target)["haySiguientePagina"]["B"])

    def test_rechaza_escena_duplicada_fuera_de_fecha_o_de_otra_coleccion(self):
        for case in ("duplicada", "fecha", "coleccion", "fuera_area"):
            with self.subTest(case=case), tempfile.TemporaryDirectory() as temp:
                target = Path(temp) / "e"
                shutil.copytree(EVIDENCE, target)
                def change(doc):
                    item = doc["features"][0]
                    if case == "duplicada":
                        doc["features"].append(item)
                    elif case == "fecha":
                        item["properties"]["datetime"] = "2024-01-01T00:00:00Z"
                    elif case == "coleccion":
                        item["collection"] = "sentinel-2-l2a"
                    else:
                        item["bbox"] = [0, 0, 1, 1]
                self.cambiar(target, change)
                with self.assertRaises(ValueError):
                    revisar(target)

    def test_rechaza_recibo_alterado_y_enlaces_con_firma(self):
        with tempfile.TemporaryDirectory() as temp:
            target = Path(temp) / "e"
            shutil.copytree(EVIDENCE, target)
            (target / "A.json").write_text("{}", encoding="utf-8")
            with self.assertRaises(ValueError):
                revisar(target)
        for key in ("sig", "token", "api-key", "access_token"):
            self.assertFalse(sin_secretos({"assets": [{"href": "https://ejemplo.test/a?" + key + "=PRUEBA"}]}))
        self.assertTrue(sin_secretos({"href": "https://ejemplo.test/a?assets=vv"}))


if __name__ == "__main__":
    unittest.main()
