"""Repite la comparación de calidad con los TIFF privados verificados."""
import argparse
import json
from pathlib import Path
import sys
from tempfile import TemporaryDirectory

CARPETA = Path(__file__).resolve().parent
sys.path.insert(0, str(CARPETA.parents[3] / "herramientas/met110"))
import escenas
import calidad
from lote_calidad import comprobar


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("tiff_privados", type=Path)
    args = parser.parse_args()
    configuracion = json.loads((CARPETA / "config.json").read_text(encoding="utf-8"))
    escenas.config = calidad.config = lambda: configuracion
    recibo = CARPETA / "revision.json"
    esperado = json.loads(recibo.read_text(encoding="utf-8"))
    with TemporaryDirectory() as temporal:
        calculado = comprobar(
            CARPETA, Path(temporal) / "recortes", [(recibo, args.tiff_privados)]
        )
    if calculado["parejasPorCalidadLocal"] != esperado["parejasPorCalidadLocal"]:
        raise SystemExit("La repetición no coincide con la evidencia.")
    print("Coinciden las seis parejas y sus fracciones de cobertura.")


if __name__ == "__main__":
    main()
