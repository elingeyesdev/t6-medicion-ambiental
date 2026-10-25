"""Referencia científica propuesta SPI-3; no implementa una ruta Laravel."""
import argparse
import calendar
from datetime import date, timedelta
import hashlib
import json
import math
from pathlib import Path

import scipy
from scipy.stats import gamma, norm

RAIZ = Path(__file__).resolve().parents[2]
CAPTURA = RAIZ / "docs/evidencias/met35/fuente-20261022"
VERSION = "spi3-era5-gamma-mle-1991-2020-v0.1-propuesta"


def meses_ventana(anio, mes):
    fin = anio * 12 + mes - 1
    return [(n // 12, n % 12 + 1) for n in range(fin - 2, fin + 1)]


def agregar_diarios(payload):
    """Un mes requiere todos sus días y valores. Null nunca se transforma en cero."""
    if payload.get("daily_units", {}).get("precipitation_sum") != "mm":
        raise ValueError("UNIDAD_INVALIDA")
    if payload.get("utc_offset_seconds") != 0:
        raise ValueError("ZONA_INVALIDA")
    fechas = payload.get("daily", {}).get("time", [])
    valores = payload.get("daily", {}).get("precipitation_sum", [])
    if not fechas or len(fechas) != len(valores):
        raise ValueError("SERIE_INVALIDA")
    diarios = {}
    for texto, valor in zip(fechas, valores):
        dia = date.fromisoformat(texto)
        if dia.isoformat() != texto or dia in diarios:
            raise ValueError("FECHA_INVALIDA_O_DUPLICADA")
        if valor is not None and (isinstance(valor, bool) or
                not isinstance(valor, (int, float)) or not math.isfinite(valor) or valor < 0):
            raise ValueError("PRECIPITACION_INVALIDA")
        diarios[dia] = valor
    meses = {}
    for anio, mes in sorted({(d.year, d.month) for d in diarios}):
        dias = [diarios.get(date(anio, mes, n)) for n in range(1, calendar.monthrange(anio, mes)[1] + 1)]
        meses[anio, mes] = None if any(v is None for v in dias) else math.fsum(dias)
    return meses


def acumular(mensuales, anio, mes):
    valores = [mensuales.get(m) for m in meses_ventana(anio, mes)]
    return None if any(v is None for v in valores) else math.fsum(valores)


def clasificar(spi):
    # Extremos y fronteras de la tabla 2 de Copernicus; usar valor SIN redondear.
    if spi <= -2:
        return "extremadamente_seco"
    if spi <= -1.5:
        return "muy_seco"
    if spi <= -1:
        return "moderadamente_seco"
    if spi <= 1:
        return "normal"
    if spi <= 1.5:
        return "moderadamente_humedo"
    if spi <= 2:
        return "muy_humedo"
    return "extremadamente_humedo"


def calcular_spi(base, objetivo):
    resultado = {"valor": None, "categoria": None, "motivo": None,
                 "aniosValidos": sum(v is not None for v in base),
                 "parametrosAjuste": None, "probabilidadAcumulada": None}
    for valor in [*base, objetivo]:
        if valor is not None and (isinstance(valor, bool) or not isinstance(valor, (int, float))
                or not math.isfinite(valor) or valor < 0):
            raise ValueError("ACUMULACION_INVALIDA")
    if len(base) != 30 or resultado["aniosValidos"] != 30:
        resultado["motivo"] = "BASE_INCOMPLETA"
        return resultado
    if objetivo is None:
        resultado["motivo"] = "PERIODO_INCOMPLETO"
        return resultado
    positivos = [v for v in base if v >= 0.01]
    if len(positivos) < 10 or max(positivos) - min(positivos) <= 1e-10:
        resultado["motivo"] = "BASE_NO_AJUSTABLE"
        return resultado
    q = (30 - len(positivos)) / 30
    try:
        forma, _, escala = gamma.fit(positivos, floc=0, method="MLE")
    except (ValueError, RuntimeError, ArithmeticError):
        resultado["motivo"] = "AJUSTE_FALLIDO"
        return resultado
    if not all(math.isfinite(v) and v > 0 for v in (forma, escala)):
        resultado["motivo"] = "AJUSTE_FALLIDO"
        return resultado
    resultado["parametrosAjuste"] = {"forma": float(forma), "escalaMm": float(escala),
                                    "probabilidadCero": q, "positivos": len(positivos)}
    x = 0 if objetivo < 0.01 else objetivo
    probabilidad = float(q + (1 - q) * gamma.cdf(x, forma, loc=0, scale=escala))
    resultado["probabilidadAcumulada"] = probabilidad
    # No recortar colas a un SPI inventado; el límite numérico es explícito.
    if not 0 < probabilidad < 1:
        resultado["motivo"] = "PROBABILIDAD_EXTREMA"
        return resultado
    valor = float(norm.ppf(probabilidad))
    resultado.update(valor=valor, categoria=clasificar(valor))
    return resultado


def cargar_captura(directorio=CAPTURA):
    manifiesto = json.loads((directorio / "manifest.json").read_text(encoding="utf-8"))
    mensuales, coordenada = {}, None
    for registro in manifiesto["archivos"]:
        crudo = (directorio / registro["archivo"]).read_bytes()
        if hashlib.sha256(crudo).hexdigest() != registro["sha256"]:
            raise ValueError("CAPTURA_MODIFICADA")
        payload = json.loads(crudo)
        actual = [payload["longitude"], payload["latitude"]]
        if coordenada is not None and coordenada != actual:
            raise ValueError("CELDA_INCONSISTENTE")
        coordenada = actual
        fechas = payload["daily"]["time"]
        desde, hasta = date.fromisoformat(registro["desde"]), date.fromisoformat(registro["hasta"])
        esperadas = [(desde + timedelta(days=n)).isoformat() for n in range((hasta - desde).days + 1)]
        if fechas != esperadas or len(fechas) != registro["diasDevueltos"]:
            raise ValueError("CALENDARIO_CAPTURA_INCOMPLETO")
        nuevos = agregar_diarios(payload)
        if set(mensuales) & set(nuevos):
            raise ValueError("CAPTURAS_SUPERPUESTAS")
        mensuales.update(nuevos)
    return manifiesto, mensuales, coordenada


def reproducir(directorio=CAPTURA):
    manifiesto, mensuales, coordenada = cargar_captura(directorio)
    base = [{"anioFin": anio, "acumuladoMm": acumular(mensuales, anio, 9)}
            for anio in range(1991, 2021)]
    objetivo = acumular(mensuales, 2024, 9)
    resultado = calcular_spi([v["acumuladoMm"] for v in base], objetivo)
    return {
        "estado": "propuesta_pendiente_revision", "metodo": VERSION,
        "naturaleza": "derivado_de_reanalisis", "muestra": manifiesto["muestra"],
        "territorioContexto": manifiesto["territorioContexto"], "municipioDelCentroCelda": "BO070103",
        "coordenadaSolicitada": [-63.1821, -17.7833], "coordenadaCelda": coordenada,
        "periodo": {"mesFinal": "2024-09", "desde": "2024-07-01", "hastaIncluido": "2024-09-30", "zona": "UTC"},
        "base": {"primerAnioFin": 1991, "ultimoAnioFin": 2020, "mesFin": 9,
                 "ventanas": base},
        "precipitacionAcumuladaMm": objetivo, "resultado": resultado,
        "reproductor": {"biblioteca": "SciPy", "version": scipy.__version__},
        "fuentes": manifiesto["archivos"],
        "limitacion": "Una celda piloto; no estima superficie afectada ni sequía de todo el municipio.",
    }


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--captura", type=Path, default=CAPTURA)
    parser.add_argument("--salida", type=Path)
    args = parser.parse_args()
    contenido = json.dumps(reproducir(args.captura), ensure_ascii=False, indent=2, allow_nan=False) + "\n"
    if args.salida:
        args.salida.parent.mkdir(parents=True, exist_ok=True)
        args.salida.write_text(contenido, encoding="utf-8")
    else:
        print(contenido, end="")
