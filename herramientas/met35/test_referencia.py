import calendar
from datetime import date
import hashlib
import json
import math
from pathlib import Path
import unittest
from unittest.mock import patch

from jsonschema import Draft202012Validator, FormatChecker
from scipy.integrate import quad
from scipy.special import digamma

from referencia import (RAIZ, CAPTURA, agregar_diarios, acumular, calcular_spi,
                        clasificar, cargar_captura, meses_ventana, reproducir)


def diario(anio=2000, mes=2):
    return {"daily_units": {"precipitation_sum": "mm"}, "utc_offset_seconds": 0,
            "daily": {"time": [date(anio, mes, n).isoformat() for n in range(1, calendar.monthrange(anio, mes)[1] + 1)],
                      "precipitation_sum": [1] * calendar.monthrange(anio, mes)[1]}}


class CalendarioTest(unittest.TestCase):
    def test_enero_incluye_noviembre_y_diciembre_anteriores(self):
        self.assertEqual(meses_ventana(1991, 1), [(1990, 11), (1990, 12), (1991, 1)])

    def test_bisiesto_completo(self):
        self.assertEqual(agregar_diarios(diario())[(2000, 2)], 29)

    def test_dia_ausente_y_null_no_se_vuelven_cero(self):
        for tipo in ("ausente", "null"):
            p = diario()
            if tipo == "ausente":
                p["daily"]["time"].pop(8)
                p["daily"]["precipitation_sum"].pop(8)
            else:
                p["daily"]["precipitation_sum"][8] = None
            self.assertIsNone(agregar_diarios(p)[2000, 2])

    def test_cero_conocido_es_valido(self):
        p = diario()
        p["daily"]["precipitation_sum"] = [0] * 29
        self.assertEqual(agregar_diarios(p)[2000, 2], 0)

    def test_mes_ausente_invalida_ventana(self):
        self.assertIsNone(acumular({(2024, 7): 20, (2024, 9): 30}, 2024, 9))

    def test_no_acepta_negativos_nan_booleanos_ni_unidad_erronea(self):
        for valor in (-1, float("nan"), float("inf"), True, "1"):
            p = diario()
            p["daily"]["precipitation_sum"][0] = valor
            with self.subTest(valor=valor), self.assertRaises(ValueError):
                agregar_diarios(p)
        p = diario()
        p["daily_units"]["precipitation_sum"] = "inch"
        with self.assertRaises(ValueError):
            agregar_diarios(p)

    def test_rechaza_duplicados_y_zona_distinta(self):
        p = diario()
        p["daily"]["time"][1] = p["daily"]["time"][0]
        with self.assertRaises(ValueError):
            agregar_diarios(p)
        p = diario()
        p["utc_offset_seconds"] = -14400
        with self.assertRaises(ValueError):
            agregar_diarios(p)


class MetodoTest(unittest.TestCase):
    def test_null_y_base_insuficiente(self):
        self.assertEqual(calcular_spi([1] * 29, 1)["motivo"], "BASE_INCOMPLETA")
        self.assertEqual(calcular_spi([1] * 29 + [None], 1)["motivo"], "BASE_INCOMPLETA")
        self.assertEqual(calcular_spi(list(range(1, 31)), None)["motivo"], "PERIODO_INCOMPLETO")

    def test_serie_constante_y_muchos_ceros_no_se_ajustan(self):
        for base in ([0] * 30, [1] * 30, [0] * 21 + list(range(1, 10))):
            self.assertEqual(calcular_spi(base, 10)["motivo"], "BASE_NO_AJUSTABLE")

    def test_masa_cero_y_distribucion_exponencial_analitica(self):
        # Gamma(1,100) es exponencial; su mediana analítica es 100*ln(2).
        with patch("referencia.gamma.fit", return_value=(1, 0, 100)):
            self.assertAlmostEqual(calcular_spi(list(range(1, 31)), 100 * math.log(2))["valor"], 0, places=12)
            r = calcular_spi([0] * 10 + list(range(1, 21)), 100 * math.log(2))
            self.assertAlmostEqual(r["probabilidadAcumulada"], 2 / 3, places=12)
            self.assertAlmostEqual(r["valor"], 0.4307272992954576, places=12)

    def test_no_inventa_indice_finito_en_probabilidades_extremas(self):
        r = calcular_spi(list(range(1, 31)), 0)
        self.assertIsNone(r["valor"])
        self.assertEqual(r["motivo"], "PROBABILIDAD_EXTREMA")

    def test_fronteras_sin_redondear(self):
        esperados = {-2: "extremadamente_seco", -1.5: "muy_seco", -1: "moderadamente_seco",
                     -0.9999: "normal", 1: "normal", 1.0001: "moderadamente_humedo",
                     1.5: "moderadamente_humedo", 2: "muy_humedo", 2.0001: "extremadamente_humedo"}
        for valor, categoria in esperados.items():
            self.assertEqual(clasificar(valor), categoria)

    def test_ajuste_mle_y_cdf_con_integracion_y_normal_con_erf(self):
        base = [40 + (n * 17) % 190 for n in range(30)]
        r = calcular_spi(base, 110)
        a, b = r["parametrosAjuste"]["forma"], r["parametrosAjuste"]["escalaMm"]
        media = math.fsum(base) / 30
        self.assertAlmostEqual(a * b, media, places=9)
        self.assertAlmostEqual(math.log(a) - digamma(a), math.log(media) - math.fsum(map(math.log, base)) / 30, places=9)
        densidad = lambda x: math.exp((a - 1) * math.log(x) - x / b - math.lgamma(a) - a * math.log(b))
        probabilidad, _ = quad(densidad, 0, 110, epsabs=1e-11)
        self.assertAlmostEqual(r["probabilidadAcumulada"], probabilidad, places=9)
        self.assertAlmostEqual((1 + math.erf(r["valor"] / math.sqrt(2))) / 2, probabilidad, places=9)


class EvidenciaYContratoTest(unittest.TestCase):
    def comparar_resultado(self, actual, esperado):
        for clave in ("valor", "probabilidadAcumulada"):
            self.assertAlmostEqual(actual[clave], esperado[clave], places=10)
        for clave in ("forma", "escalaMm", "probabilidadCero"):
            self.assertAlmostEqual(actual["parametrosAjuste"][clave], esperado["parametrosAjuste"][clave], places=10)
        for clave in ("categoria", "motivo", "aniosValidos"):
            self.assertEqual(actual[clave], esperado[clave])

    def test_captura_real_hashes_y_reproduccion(self):
        manifiesto, meses, celda = cargar_captura()
        self.assertEqual(len(manifiesto["archivos"]), 2)
        self.assertEqual(celda, [-63.25, -17.75])
        self.assertEqual(len(meses), 365)  # 362 meses de base extendida + 3 de objetivo
        guardado = json.loads((RAIZ / "docs/evidencias/met35/resultado-referencia.json").read_text(encoding="utf-8"))
        actual = reproducir()
        self.comparar_resultado(actual["resultado"], guardado["resultado"])
        actual["resultado"] = guardado["resultado"]
        self.assertEqual(actual, guardado)
        self.assertAlmostEqual(guardado["precipitacionAcumuladaMm"], 114.5)
        self.assertAlmostEqual(guardado["resultado"]["valor"], -0.6410480784168507, places=10)

    def test_ejemplos_validos_y_filtro_fecha_diaria_rechazado(self):
        carpeta = RAIZ / "compartido/api/sequias-propuesta"
        for tipo in ("respuesta", "consulta"):
            schema = json.loads((carpeta / f"{tipo}.schema.json").read_text(encoding="utf-8"))
            Draft202012Validator.check_schema(schema)
            validador = Draft202012Validator(schema, format_checker=FormatChecker())
            ejemplo = json.loads((carpeta / f"{tipo}.ejemplo.json").read_text(encoding="utf-8"))
            validador.validate(ejemplo)
            if tipo == "consulta":
                for invalido in ({"mesFinal": "2024-9"}, {"mesFinal": "2024-13"}, {"mesFinal": "2024-09", "desde": "2024-07-01"}):
                    self.assertFalse(validador.is_valid(invalido))
            else:
                ejemplo["datos"][0]["spi"]["valor"] = None
                self.assertFalse(validador.is_valid(ejemplo))

    def test_ejemplo_conserva_resultado_real(self):
        carpeta = RAIZ / "compartido/api/sequias-propuesta"
        ejemplo = json.loads((carpeta / "respuesta.ejemplo.json").read_text(encoding="utf-8"))
        r = reproducir()
        self.comparar_resultado(ejemplo["datos"][0]["spi"], r["resultado"])
        self.assertEqual(ejemplo["datos"][0]["precipitacionAcumuladaMm"], r["precipitacionAcumuladaMm"])


if __name__ == "__main__":
    unittest.main(verbosity=2)
