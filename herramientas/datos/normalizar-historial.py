"""Normaliza un export JSON revisado. No conecta bases, proveedores ni cuentas."""
import argparse
from datetime import datetime, timezone
import hashlib
import json
import math
from pathlib import Path
import re

RAIZ = Path(__file__).resolve().parents[2]
CATALOGO = json.loads((RAIZ / "compartido/sensores/catalogo-envirosense.json").read_text(encoding="utf-8"))
MAXIMO = 2 * 1024 * 1024


def leer(archivo):
    ruta = Path(archivo)
    if ruta.is_symlink() or not ruta.is_file() or ruta.stat().st_size > MAXIMO:
        raise ValueError("Elige un JSON local de hasta 2 MB.")
    return json.loads(ruta.read_text(encoding="utf-8-sig"),
                      parse_constant=lambda _: (_ for _ in ()).throw(ValueError("Valor no finito.")))


def normalizar(filas, mapa):
    if not isinstance(mapa, dict) or set(mapa) != {"localidades"} or not isinstance(mapa["localidades"], dict):
        raise ValueError("El mapa requiere un objeto localidades de identificador a código.")
    if not isinstance(filas, list) or not 1 <= len(filas) <= 1000:
        raise ValueError("El export debe contener entre 1 y 1000 lecturas.")
    requeridos = {"ts", "localidad_id", "metrica", "valor", "unidad", "origen", "fuente"}
    permitidos = requeridos | {"ciudad", "latitud", "longitud"}
    salida = []
    for n, fila in enumerate(filas, 1):
        if not isinstance(fila, dict) or not requeridos <= set(fila) or set(fila) - permitidos:
            raise ValueError(f"Fila {n}: faltan columnas de procedencia o hay columnas ajenas.")
        identificador = fila["localidad_id"]
        if isinstance(identificador, bool) or not isinstance(identificador, (int, str)):
            raise ValueError(f"Fila {n}: identificador inválido.")
        punto = mapa["localidades"].get(str(identificador))
        if not isinstance(punto, str) or not re.fullmatch(r"[a-z0-9][a-z0-9_-]{0,59}", punto):
            raise ValueError(f"Fila {n}: falta el código de punto revisado en el mapa.")
        metrica = fila["metrica"]
        if not isinstance(metrica, str) or metrica not in CATALOGO["metricas"]:
            raise ValueError(f"Fila {n}: variable desconocida.")
        limites = CATALOGO["metricas"][metrica]
        valor = fila["valor"]
        if isinstance(valor, bool) or not isinstance(valor, (int, float)) or not math.isfinite(valor) or not limites["minimo"] <= valor <= limites["maximo"]:
            raise ValueError(f"Fila {n}: valor fuera de rango.")
        if fila["unidad"] != limites["unidad"] or fila["origen"] not in {"observado", "estimado", "simulado"}:
            raise ValueError(f"Fila {n}: unidad u origen incompatible.")
        if not isinstance(fila["fuente"], str) or not re.fullmatch(r"[a-z0-9][a-z0-9._-]{0,79}", fila["fuente"]):
            raise ValueError(f"Fila {n}: fuente inválida.")
        texto = fila["ts"]
        if not isinstance(texto, str) or not re.fullmatch(r"\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(Z|[+-](0\d|1[0-4]):[0-5]\d)", texto) or re.search(r"[+-]14:(?!00)", texto):
            raise ValueError(f"Fila {n}: fecha ISO 8601 con segundos y zona requerida.")
        fecha = datetime.fromisoformat(texto.replace("Z", "+00:00")).astimezone(timezone.utc)
        if fecha > datetime.now(timezone.utc):
            raise ValueError(f"Fila {n}: fecha futura.")
        salida.append({"tiempo": fecha.strftime("%Y-%m-%dT%H:%M:%SZ"), "localidad": punto,
                       "metrica": metrica, "valor": valor, "unidad": fila["unidad"],
                       "origen": fila["origen"], "fuente": fila["fuente"]})
    return {"formato": "geosur.historial.v1", "lecturas": salida}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("export")
    parser.add_argument("--mapa", required=True)
    parser.add_argument("--salida")
    parser.add_argument("--comprobar", action="store_true")
    args = parser.parse_args()
    try:
        documento = normalizar(leer(args.export), leer(args.mapa))
        contenido = (json.dumps(documento, ensure_ascii=False, indent=2, allow_nan=False) + "\n").encode("utf-8")
        if len(contenido) > MAXIMO:
            raise ValueError("La salida supera 2 MB. Divide el export en lotes completos más pequeños.")
        if not args.comprobar:
            if not args.salida:
                raise ValueError("Indica --salida o --comprobar.")
            salida = Path(args.salida).resolve()
            if any(salida.is_relative_to(RAIZ / carpeta) for carpeta in ("referencias", "movil")):
                raise ValueError("La salida debe estar en una carpeta de trabajo de datos.")
            # Exclusivo: nunca sobrescribir el export, el mapa ni otro trabajo.
            with salida.open("xb") as archivo:
                archivo.write(contenido)
        print(f"Lecturas: {len(documento['lecturas'])}. SHA-256: {hashlib.sha256(contenido).hexdigest()}. "
              + ("Comprobado sin escribir." if args.comprobar else "Archivo saneado creado."))
        return 0
    except (ValueError, OSError, TypeError):
        parser.exit(2, "No se creó la salida. Revisa columnas, procedencia, mapa, fechas y permisos; no se sobrescriben archivos.\n")


if __name__ == "__main__":
    raise SystemExit(main())
