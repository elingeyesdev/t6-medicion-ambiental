"""Captura externa acotada de MET-01. No sirve datos a la aplicación."""
import argparse
from datetime import datetime, timezone
import hashlib
import json
from pathlib import Path
from urllib.parse import urlencode
from urllib.request import Request, urlopen

RAIZ = Path(__file__).resolve().parents[2]
PARAMETROS = {
    "latitude": "-17.7833", "longitude": "-63.1821",
    "daily": "precipitation_sum", "models": "era5", "timezone": "GMT",
    "precipitation_unit": "mm", "cell_selection": "nearest", "elevation": "nan",
}
PERIODOS = {
    "base": ("1990-11-01", "2020-12-31"),
    "objetivo": ("2024-07-01", "2024-09-30"),
}


def capturar(destino):
    # No sobrescribir una captura usada como evidencia; cada nueva descarga es otro corte.
    destino.mkdir(parents=True, exist_ok=False)
    manifiesto = {
        "tipo": "consulta_externa_real", "proveedor": "Open-Meteo",
        "productoSolicitado": "ERA5", "naturaleza": "reanalisis",
        "muestra": "scz-piloto-01", "territorioContexto": "BO070101",
        "parametrosComunes": PARAMETROS, "archivos": [],
        "atribucion": "Open-Meteo.com; ECMWF / Copernicus Climate Change Service, ERA5.",
        "licencia": "https://creativecommons.org/licenses/by/4.0/",
    }
    for nombre, (desde, hasta) in PERIODOS.items():
        url = "https://archive-api.open-meteo.com/v1/archive?" + urlencode(
            {**PARAMETROS, "start_date": desde, "end_date": hasta})
        with urlopen(Request(url, headers={"User-Agent": "GeoCruz-MET01/0.1 (academic validation)"}), timeout=60) as respuesta:
            crudo = respuesta.read(8 * 1024 * 1024 + 1)
            if len(crudo) > 8 * 1024 * 1024:
                raise ValueError("Respuesta supera 8 MiB; captura incompleta, no usar.")
            datos = json.loads(crudo)
            if respuesta.status != 200 or datos.get("error"):
                raise ValueError("El proveedor no devolvió datos válidos.")
            instante = datetime.now(timezone.utc).isoformat().replace("+00:00", "Z")
            cabeceras = {k: respuesta.headers.get(k) for k in ("Date", "Content-Type")}
        archivo = f"{nombre}.json"
        (destino / archivo).write_bytes(crudo)
        manifiesto["archivos"].append({
            "archivo": archivo, "url": url, "obtenidoEn": instante,
            "http": 200, "cabeceras": cabeceras, "bytes": len(crudo),
            "sha256": hashlib.sha256(crudo).hexdigest(),
            "diasDevueltos": len(datos.get("daily", {}).get("time", [])),
            "coordenadaDevuelta": [datos["longitude"], datos["latitude"]],
            "desde": desde, "hasta": hasta,
        })
        print(f"{nombre}: HTTP 200; {len(crudo)} bytes; recuperado {instante}")
    (destino / "manifest.json").write_text(
        json.dumps(manifiesto, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--salida", type=Path, required=True,
                        help="Directorio nuevo, por ejemplo tmp/met35-nueva-captura")
    capturar(parser.parse_args().salida)
