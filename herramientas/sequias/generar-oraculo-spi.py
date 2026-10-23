"""Congelar referencias SciPy para contrastar PHP; sin red ni importación de código PHP."""
import argparse
import hashlib
import json
from pathlib import Path

import scipy
from scipy.stats import gamma, norm


def esperado(base, objetivo):
    positivos = [v for v in base if v >= 0.01]
    a, _, escala = gamma.fit(positivos, floc=0, method="MLE")
    q = (30 - len(positivos)) / 30
    p = float(q + (1 - q) * gamma.cdf(0 if objetivo < 0.01 else objetivo, a, scale=escala))
    return {"forma": float(a), "escalaMm": float(escala), "probabilidadCero": q,
            "probabilidadAcumulada": p, "valor": float(norm.ppf(p)) if 0 < p < 1 else None}


def generar(piloto):
    crudo = piloto.read_bytes()
    original = json.loads(crudo)
    casos = [("piloto_externo", [v["acumuladoMm"] for v in original["base"]["ventanas"]], original["precipitacionAcumuladaMm"])]
    comunes = [40 + (n * 17) % 190 for n in range(30)]
    casos += [("controlada_regular", comunes, 110),
              ("controlada_ceros", [0] * 10 + list(range(1, 21)), 0),
              ("controlada_umbral", [0.009] * 10 + [0.01 + n for n in range(20)], 0.009),
              ("controlada_asimetrica", [0.01 * 2 ** (n / 2) for n in range(30)], 1),
              ("controlada_escala_grande", [v * 1e100 for v in comunes], 110e100),
              ("controlada_concentrada", [100 + n / 10 for n in range(30)], 101.5),
              ("controlada_cola_seca", comunes, 0.01),
              ("controlada_cola_humeda", comunes, 1000)]
    return {"estado": "oraculo_de_contraste_no_adopta_metodo", "scipy": scipy.__version__,
            "piloto": {"commitOrigen": "1d1865f9686a97c9a61a3ecccd07006040e2b7f5",
                       "archivoOrigen": "docs/evidencias/met35/resultado-referencia.json",
                       "sha256ArchivoOrigenLF": hashlib.sha256(crudo.replace(b"\r\n", b"\n")).hexdigest(),
                       "fuentes": [{k: f[k] for k in ("archivo", "sha256", "url", "obtenidoEn")} for f in original["fuentes"]]},
            "casos": [{"id": nombre, "base": base, "objetivo": objetivo, "esperado": esperado(base, objetivo)} for nombre, base, objetivo in casos],
            "gamma": [{"forma": a, "x": a * f, "p": float(gamma.cdf(a * f, a)), "q": float(gamma.sf(a * f, a))}
                      for a in (0.05, 0.5, 1, 7.5, 100, 10000, 1e6) for f in (0.1, 0.8, 1, 1.2, 3)],
            "normal": [{"p": p, "z": float(norm.ppf(p))} for p in (1e-300, 1e-100, 1e-20, 0.001, 0.25, 0.5, 0.9, 1 - 1e-12)]}


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--piloto", type=Path, required=True)
    parser.add_argument("--salida", type=Path, required=True)
    args = parser.parse_args()
    if scipy.__version__ != "1.16.3":
        raise SystemExit("Usar scipy==1.16.3 para reproducir este oráculo.")
    args.salida.parent.mkdir(parents=True, exist_ok=True)
    args.salida.write_text(json.dumps(generar(args.piloto), ensure_ascii=False, indent=2, allow_nan=False) + "\n", encoding="utf-8", newline="\n")
