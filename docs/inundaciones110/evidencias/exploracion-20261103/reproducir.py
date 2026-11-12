"""Reproduce la exploración de fechas con metadatos guardados, sin red."""
from hashlib import sha256
import json
from pathlib import Path
import sys
from unittest.mock import patch
from urllib.parse import parse_qs, urlsplit

FOLDER = Path(__file__).resolve().parent
ROOT = FOLDER.parents[3]
sys.path.insert(0, str(ROOT / "herramientas/met110"))
import escenas


def read_checked(path, receipt):
    raw = path.read_bytes()
    if len(raw) != receipt["bytes"] or sha256(raw).hexdigest() != receipt["sha256"]:
        raise ValueError("Los metadatos no coinciden con su recibo.")
    if receipt["http"] != 200:
        raise ValueError("El recibo no acredita acceso correcto.")
    return raw


def result():
    config = json.loads((FOLDER / "config.json").read_text(encoding="utf-8"))
    receipt_a = json.loads((FOLDER / "recibo.json").read_text(encoding="utf-8"))
    query = parse_qs(urlsplit(receipt_a["url"]).query)
    if query.get("datetime") != [config["ventanas"]["A"]]:
        raise ValueError("La ventana consultada no coincide con la propuesta.")
    previous = FOLDER.parent
    receipts = json.loads((previous / "consultas.json").read_text(encoding="utf-8"))
    receipt_b = next(item for item in receipts if item["archivo"] == "B.json")
    raw_a = read_checked(FOLDER / "A.json", receipt_a)
    raw_b = read_checked(previous / "B.json", receipt_b)
    # El lector recibe esta configuración solo dentro de la reproducción.
    with patch.object(escenas, "config", return_value=config):
        records_a, next_a = escenas.escenas(raw_a, receipt_a["tipo"], 200, "A")
        records_b, next_b = escenas.escenas(raw_b, receipt_b["tipo"], 200, "B")
    pairs = escenas.parejas(records_a, records_b)
    new_pairs = [pair for pair in pairs if next(
        item["fecha"] for item in records_a if item["id"] == pair["referencia"]
    ) < "2025-02-15"]
    quality_file = previous / "revision-completa.json"
    quality = json.loads(quality_file.read_text(encoding="utf-8"))
    if quality["bbox"] != config["bbox"]:
        raise ValueError("La calidad guardada corresponde a otra área.")
    bounds = []
    for pair in new_pairs:
        matches = [item["calidad"] for item in quality["parejasPorCalidadLocal"]
                   if item["comparacion"] == pair["comparacion"]]
        if not matches:
            raise ValueError("Falta calidad previa de la comparación.")
        values = {item["fraccionComparableB"] for item in matches}
        if len(values) != 1 or any(item["politicaComparables"] != [4, 5, 6]
                                   or item["pixelesRecorte"] != 264812 for item in matches):
            raise ValueError("La calidad previa no usa una ventana y máscara comunes.")
        bounds.append({
            "referencia": pair["referencia"],
            "comparacion": pair["comparacion"],
            "limiteSuperiorFraccionComparable": values.pop(),
            "interpretacion": "La intersección A y B nunca supera las celdas válidas de B.",
        })
    return {
        "estado": "exploracion_para_revision",
        "referenciaAmpliada": config["ventanas"]["A"],
        "consultaReferenciaUTC": receipt_a["consultadoUTC"],
        "comparacionReutilizadaUTC": receipt_b["consultado_utc"],
        "escenas": {"A": len(records_a), "B": len(records_b)},
        "haySiguientePagina": {"A": next_a, "B": next_b},
        "parejasCompatibles": len(pairs),
        "parejasConReferenciaNueva": new_pairs,
        "calidadLocalComprobadaParaReferenciasNuevas": False,
        "cotasPorComparacionConocida": bounds,
        "calidadPreviaSha256": sha256(quality_file.read_bytes()).hexdigest(),
        "bandasDescargadas": False,
        "metodoAprobado": False,
    }


if __name__ == "__main__":
    print(json.dumps(result(), ensure_ascii=False, indent=2))
