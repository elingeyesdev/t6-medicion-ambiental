"""Contrasta una publicación real con SciPy, sin importar el motor PHP."""
import argparse
import hashlib
import json
from pathlib import Path

import scipy
from scipy.stats import gamma, norm


def contrastar(documento):
    informe = documento["datos"]["informe"]
    ventanas = informe["base"]["ventanas"]
    if len(ventanas) != 30 or [v["anioFin"] for v in ventanas] != list(range(1991, 2021)):
        raise ValueError("Se esperan las treinta ventanas de referencia, sin sustituir faltantes.")
    valores = [v["acumuladoMm"] for v in ventanas]
    positivos = [v for v in valores if v >= 0.01]
    forma, _, escala = gamma.fit(positivos, floc=0, method="MLE")
    cero = (len(valores) - len(positivos)) / len(valores)
    lluvia = informe["precipitacionAcumuladaMm"]
    probabilidad = float(cero + (1 - cero) * gamma.cdf(0 if lluvia < 0.01 else lluvia, forma, scale=escala))
    referencia = float(norm.ppf(probabilidad))
    laravel = informe["resultado"]["valor"]
    diferencia = abs(referencia - laravel)
    return {
        "ejecucion": "contraste_independiente_de_la_publicacion_29_10_2026",
        "scipy": scipy.__version__,
        "metodo": "Gamma MLE, posición fija en cero, mezcla de ceros y normal inversa",
        "muestra": informe["muestra"], "periodo": informe["periodo"],
        "ventanas": ventanas, "precipitacionAcumuladaMm": lluvia,
        "versiones": informe["versiones"],
        "laravel": laravel, "referencia": referencia,
        "forma": float(forma), "escalaMm": float(escala),
        "probabilidadCero": cero, "diferenciaAbsoluta": diferencia,
        "tolerancia": 1e-8, "coincide": diferencia <= 1e-8,
        "alcance": "Resultado puntual de una celda; no acredita sequía de todo un municipio.",
    }


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--entrada", required=True, type=Path)
    parser.add_argument("--salida", required=True, type=Path)
    args = parser.parse_args()
    crudo = args.entrada.read_bytes()
    resultado = contrastar(json.loads(crudo.decode("utf-8-sig")))
    resultado["sha256Entrada"] = hashlib.sha256(crudo).hexdigest()
    args.salida.parent.mkdir(parents=True, exist_ok=True)
    args.salida.write_text(json.dumps(resultado, ensure_ascii=False, allow_nan=False, indent=2) + "\n", encoding="utf-8")
    print(json.dumps({k: resultado[k] for k in ("scipy", "laravel", "referencia", "diferenciaAbsoluta", "coincide")}))
    raise SystemExit(0 if resultado["coincide"] else 1)
