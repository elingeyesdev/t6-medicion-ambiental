"""Compara las dos series guardadas conservando sus decimales originales."""
import argparse
from decimal import Decimal
import json
from pathlib import Path

YEAR = "umd_tree_cover_loss__year"


def load_series(folder):
    rows = json.loads((folder / "serie.json").read_text(encoding="utf-8"), parse_float=Decimal)
    return {row[YEAR]: row["perdida_ha"] for row in rows}


def compare(original_folder, current_folder):
    original = load_series(original_folder)
    current = load_series(current_folder)
    if set(original) != set(current):
        raise ValueError("Cambió la cobertura temporal.")
    differences = []
    for year in sorted(original):
        if original[year] != current[year]:
            differences.append({"anio": year, "anteriorHa": str(original[year]),
                                "actualHa": str(current[year]),
                                "diferenciaHa": str(current[year] - original[year])})
    return {
        "anios": len(current), "aniosConDiferenciaDecimal": len(differences),
        "maximaDiferenciaAbsolutaHa": str(max((abs(current[y] - original[y]) for y in original), default=Decimal(0))),
        "diferenciaSumaHa": str(sum(current.values(), Decimal(0)) - sum(original.values(), Decimal(0))),
        "diferencias": differences,
    }


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("original", type=Path)
    parser.add_argument("actual", type=Path)
    args = parser.parse_args()
    print(json.dumps(compare(args.original, args.actual), ensure_ascii=False, indent=2))
