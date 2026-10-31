from pathlib import Path
import unittest
import xml.etree.ElementTree as ET

from extraer import NS, extraer

ROOT = Path(__file__).resolve().parents[2]
SOURCE = ROOT / "docs/planificacion109/referencia-v2.xml"


class PlannerTest(unittest.TestCase):
    def test_red_original_no_es_un_pronostico_y_conserva_discrepancia_m2(self):
        report = extraer(SOURCE.read_bytes())
        self.assertEqual(report["jornadasRedSinNivelar"], "30")
        self.assertEqual(report["minutosPorJornadaTecnica"], 240)
        self.assertIsNone(report["pronosticoActual"])
        self.assertFalse(report["disponibilidadActualConfirmada"])
        self.assertEqual(report["actividadesHolguraCero"], list("ABCEFGHIJKL"))
        d = next(x for x in report["actividades"] if x["actividad"] == "D")
        self.assertEqual(d["holguraJornadasRedSinNivelar"], "1")
        self.assertEqual([(w["tareaUid"], w["predecesoraUid"]) for w in report["advertenciasExportacion"]], [(16, 6)])

    def test_esfuerzo_no_se_suma_como_duracion_de_red(self):
        root = ET.fromstring(SOURCE.read_bytes())
        for work in root.findall("p:Tasks/p:Task/p:Work", NS):
            work.text = "PT500H0M0S"
        self.assertEqual(extraer(ET.tostring(root))["jornadasRedSinNivelar"], "30")

    def test_otra_rama_mas_larga_cambia_camino_critico(self):
        root = ET.fromstring(SOURCE.read_bytes())
        for task in root.findall("p:Tasks/p:Task", NS):
            if task.findtext("p:UID", namespaces=NS) == "5":
                task.find("p:Duration", NS).text = "PT20H0M0S"
        result = extraer(ET.tostring(root))
        self.assertEqual(result["jornadasRedSinNivelar"], "32")
        self.assertIn("D", result["actividadesHolguraCero"])
        self.assertNotIn("C", result["actividadesHolguraCero"])

    def test_rechaza_identificadores_repetidos_y_predecesoras_ausentes(self):
        for case in ("uid", "predecesora"):
            root = ET.fromstring(SOURCE.read_bytes())
            tasks = root.findall("p:Tasks/p:Task", NS)
            if case == "uid":
                tasks[-1].find("p:UID", NS).text = "2"
            else:
                tasks[2].find("p:PredecessorLink/p:PredecessorUID", NS).text = "999"
            with self.subTest(case=case), self.assertRaises(ValueError):
                extraer(ET.tostring(root))

    def test_rechaza_ciclo_sin_asignar_fechas_o_horas_actuales(self):
        root = ET.fromstring(SOURCE.read_bytes())
        a = root.findall("p:Tasks/p:Task", NS)[1]
        prefix = "{" + NS["p"] + "}"
        link = ET.SubElement(a, prefix + "PredecessorLink")
        for key, value in (("PredecessorUID", "3"), ("Type", "1"), ("LinkLag", "0")):
            ET.SubElement(link, prefix + key).text = value
        with self.assertRaises(ValueError):
            extraer(ET.tostring(root))


if __name__ == "__main__":
    unittest.main()
