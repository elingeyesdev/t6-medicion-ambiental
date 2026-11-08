from contextlib import redirect_stderr, redirect_stdout
from copy import deepcopy
from decimal import localcontext
import hashlib
import io
import json
from pathlib import Path
import shutil
import tempfile
import unittest
from unittest.mock import patch

from ventana import acotar, consultar, main
from verificar_fuente import revisar

ROOT = Path(__file__).resolve().parents[2]
CAPTURA = ROOT / "docs/forestal116/evidencias/contraste-20261103/acceso"


class VentanaTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.base = revisar(CAPTURA)

    def test_captura_real_y_suma_2024_2025(self):
        dato = consultar(CAPTURA, 2024, 2025)
        self.assertEqual(dato["consulta"], {
            "desde": 2024, "hasta": 2025, "extremos_incluidos": True,
        })
        self.assertEqual([f["anio"] for f in dato["serie"]], [2024, 2025])
        self.assertEqual(dato["resumen"]["total_periodo_ha"], "1720254.57894")
        self.assertEqual(dato["fuente"]["anios"], list(range(2001, 2026)))

    def test_evidencia_guardada_reproducible(self):
        archivo = ROOT / "docs/forestal147/evidencias/2024-2025.json"
        self.assertEqual(consultar(CAPTURA, 2024, 2025),
                         json.loads(archivo.read_text(encoding="utf-8")))

    def test_un_anio_y_ventana_completa(self):
        dato = acotar(self.base, 2024, 2024)
        self.assertEqual(dato["resumen"]["anios_con_dato"], 1)
        self.assertEqual(dato["resumen"]["total_periodo_ha"], "1323408.83757")
        completo = acotar(self.base, 2001, 2025)
        self.assertEqual(completo["serie"], self.base["serie"])
        self.assertEqual(completo["resumen"], self.base["resumen"])

    def test_conserva_identidades_sin_aprobar_metodo_y_no_muta(self):
        original = deepcopy(self.base)
        dato = acotar(original, 2024, 2025)
        for campo in ["fuente", "criterio", "territorio", "cobertura",
                      "unidad", "decimales", "capa", "limites"]:
            self.assertEqual(dato[campo], self.base[campo])
        self.assertFalse(dato["integrado_en_api"])
        self.assertFalse(dato["criterio"]["adoptado"])
        self.assertEqual(dato["estado_metodo"], "pendiente_revision")
        self.assertIsNone(dato["cobertura"]["fraccion_valida"])
        self.assertEqual(original, self.base)

    def test_cero_valido_conserva_total_cero(self):
        base = deepcopy(self.base)
        base["serie"][0]["perdida_ha"] = "0"
        dato = acotar(base, 2001, 2001)
        self.assertEqual(dato["serie"][0]["estado"], "dato")
        self.assertEqual(dato["resumen"]["anios_con_dato"], 1)
        self.assertEqual(dato["resumen"]["total_periodo_ha"], "0")

    def test_anio_nulo_solo_tiene_subtotal(self):
        base = deepcopy(self.base)
        base["serie"][0].update(estado="no_informado", perdida_ha=None)
        dato = acotar(base, 2001, 2002)
        self.assertEqual(dato["resumen"]["anios_sin_dato"], [2001])
        self.assertEqual(dato["resumen"]["subtotal_informado_ha"], "118225.4508")
        self.assertIsNone(dato["resumen"]["total_periodo_ha"])

    def test_anio_ausente_se_conserva_como_faltante(self):
        base = deepcopy(self.base)
        base["serie"] = base["serie"][1:]
        dato = acotar(base, 2001, 2001)
        self.assertEqual(dato["serie"], [
            {"anio": 2001, "estado": "no_informado", "perdida_ha": None},
        ])
        self.assertIsNone(dato["resumen"]["subtotal_informado_ha"])
        self.assertIsNone(dato["resumen"]["total_periodo_ha"])

    def test_faltante_fuera_de_ventana_no_anula_total(self):
        base = deepcopy(self.base)
        base["serie"] = base["serie"][1:]
        dato = acotar(base, 2024, 2025)
        self.assertEqual(dato["resumen"]["total_periodo_ha"], "1720254.57894")

    def test_rechaza_rangos_incompatibles(self):
        for desde, hasta in [(True, 2025), (2001, False), (2025, 2024),
                              (2000, 2001), (2025, 2026),
                              ("2024", 2025), ("2024-01", 2025), (2001.0, 2025)]:
            with self.subTest(desde=desde, hasta=hasta):
                with self.assertRaises(ValueError):
                    acotar(self.base, desde, hasta)

    def test_rechaza_hueco_en_anios_publicados(self):
        base = deepcopy(self.base)
        base["fuente"]["anios"].remove(2024)
        with self.assertRaises(ValueError):
            acotar(base, 2023, 2025)

    def test_rechaza_anios_duplicados_o_ajenos(self):
        for anio in [2001, 2026, True]:
            base = deepcopy(self.base)
            fila = deepcopy(base["serie"][0])
            fila["anio"] = anio
            base["serie"].append(fila)
            with self.subTest(anio=anio), self.assertRaises(ValueError):
                acotar(base, 2024, 2025)

    def test_rechaza_areas_y_estados_inconsistentes(self):
        casos = [("dato", "-1"), ("dato", "NaN"), ("dato", "Infinity"),
                 ("dato", "x"), ("dato", True), ("dato", 0), ("dato", None),
                 ("no_informado", "0"), ("otro", None)]
        for estado, area in casos:
            base = deepcopy(self.base)
            base["serie"][0].update(estado=estado, perdida_ha=area)
            with self.subTest(estado=estado, area=area), self.assertRaises(ValueError):
                acotar(base, 2001, 2002)

    def test_suma_exacta_no_depende_del_contexto_decimal_global(self):
        base = deepcopy(self.base)
        base["serie"][0]["perdida_ha"] = "123456789012345678901234567890.123456789123456789"
        base["serie"][1]["perdida_ha"] = "0.000000000000000001"
        with localcontext() as contexto:
            contexto.prec = 6
            contexto.Emax = 9
            dato = acotar(base, 2001, 2002)
        self.assertEqual(dato["resumen"]["total_periodo_ha"],
                         "123456789012345678901234567890.123456789123456790")

    def test_recibos_y_archivos_quedan_identificados(self):
        dato = consultar(CAPTURA, 2024, 2025)
        self.assertEqual(dato["reproduccion"]["recibos_sha256"],
                         hashlib.sha256((CAPTURA / "consultas.json").read_bytes()).hexdigest())
        self.assertEqual(len(dato["reproduccion"]["archivos_sha256"]), 4)
        for nombre, huella in dato["reproduccion"]["archivos_sha256"].items():
            self.assertEqual(huella, hashlib.sha256((CAPTURA / nombre).read_bytes()).hexdigest())

    def test_captura_alterada_se_rechaza(self):
        with tempfile.TemporaryDirectory() as temporal:
            copia = Path(temporal) / "captura"
            shutil.copytree(CAPTURA, copia)
            with (copia / "serie.json").open("ab") as archivo:
                archivo.write(b" ")
            with self.assertRaises(ValueError):
                consultar(copia, 2024, 2025)

    def test_no_consulta_la_red(self):
        with patch("urllib.request.urlopen", side_effect=AssertionError("red")), \
                patch("urllib.request.OpenerDirector.open", side_effect=AssertionError("red")):
            self.assertEqual(consultar(CAPTURA, 2024, 2025)["resumen"]["anios_con_dato"], 2)

    def test_cli_guarda_json_nuevo_y_no_sobrescribe(self):
        with tempfile.TemporaryDirectory() as temporal:
            salida = Path(temporal) / "salida.json"
            argumentos = ["--directorio", str(CAPTURA), "--desde", "2024",
                          "--hasta", "2025", "--salida", str(salida)]
            with redirect_stdout(io.StringIO()), redirect_stderr(io.StringIO()):
                self.assertEqual(main(argumentos), 0)
                original = salida.read_bytes()
                self.assertEqual(json.loads(original), consultar(CAPTURA, 2024, 2025))
                self.assertEqual(main(argumentos), 1)
            self.assertEqual(salida.read_bytes(), original)

    def test_cli_error_no_crea_salida_y_stdout_es_json(self):
        with tempfile.TemporaryDirectory() as temporal:
            salida = Path(temporal) / "salida.json"
            with redirect_stdout(io.StringIO()), redirect_stderr(io.StringIO()):
                codigo = main(["--directorio", str(CAPTURA), "--desde", "2024",
                               "--hasta", "2026", "--salida", str(salida)])
            self.assertEqual(codigo, 1)
            self.assertFalse(salida.exists())
        texto = io.StringIO()
        with redirect_stdout(texto):
            self.assertEqual(main(["--directorio", str(CAPTURA), "--desde", "2024",
                                   "--hasta", "2025"]), 0)
        self.assertEqual(json.loads(texto.getvalue()), consultar(CAPTURA, 2024, 2025))


    def test_recibos_cambiados_durante_lectura_no_generan_resultado(self):
        with tempfile.TemporaryDirectory() as temporal:
            copia = Path(temporal) / "captura"
            shutil.copytree(CAPTURA, copia)
            def leer_y_cambiar(directorio):
                dato = revisar(directorio)
                with (directorio / "consultas.json").open("ab") as archivo:
                    archivo.write(b" ")
                return dato
            with patch("ventana.revisar", side_effect=leer_y_cambiar):
                with self.assertRaisesRegex(ValueError, "cambió durante la lectura"):
                    consultar(copia, 2024, 2025)


if __name__ == "__main__":
    unittest.main()
