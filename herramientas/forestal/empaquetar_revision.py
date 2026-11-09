"""Empaqueta una revisión técnica comprobada; nunca publica un producto aprobado."""
import argparse
import hashlib
import json
from pathlib import Path
import shutil
import sys
import tempfile

from contrastar_recorte import contrastar, leer_json


def bytes_json(dato):
    return (json.dumps(dato, sort_keys=True, ensure_ascii=False,
                       separators=(",", ":"), allow_nan=False)+"\n").encode("utf-8")


def empaquetar(caso, destino):
    caso, destino = Path(caso), Path(destino)
    contraste = contrastar(caso)
    resumen = leer_json(caso / "resultado/resumen.json", 200000)
    archivos = {nombre: (caso / "resultado" / nombre).read_bytes()
                for nombre in ("resumen.json", "perdida.geojson")}
    # Repetir después de leer: la captura y los resultados no pueden cambiar entre pasos.
    if contraste != contrastar(caso):
        raise ValueError("El caso cambió durante la preparación.")
    for nombre, contenido in archivos.items():
        if contenido != (caso / "resultado" / nombre).read_bytes():
            raise ValueError("El resultado cambió durante la preparación.")
    if json.loads(archivos["resumen.json"]) != resumen:
        raise ValueError("El resumen cambió durante la preparación.")
    manifiesto = {
        "formato": "revision-forestal-1",
        "estado": "revision_tecnica",
        "identidad": {
            "fuente_version": resumen["fuente"]["version"],
            "tesela": resumen["fuente"]["tesela"],
            "captura_sha256": resumen["fuente"]["captura_sha256"],
            "criterio": resumen["criterio"],
            "ventana_pixeles": resumen["ventana_pixeles"],
            "consulta": resumen["consulta"],
        },
        "archivos": {n: hashlib.sha256(b).hexdigest() for n, b in archivos.items()},
    }
    contenido = bytes_json(manifiesto)
    identidad = hashlib.sha256(contenido).hexdigest()
    destino.mkdir(parents=True, exist_ok=True)
    carpeta = destino / identidad
    esperado = {**archivos, "manifiesto.json": contenido}

    def comprobar_existente():
        if carpeta.is_symlink() or not carpeta.is_dir():
            raise ValueError("Destino de revisión incompatible.")
        for nombre, original in esperado.items():
            path = carpeta / nombre
            if path.is_symlink():
                raise ValueError("No se admiten enlaces simbólicos.")
            with path.open("rb") as archivo:
                actual = archivo.read(len(original)+1)
            if actual != original:
                raise ValueError("El paquete existente fue modificado.")

    if carpeta.exists() or carpeta.is_symlink():
        comprobar_existente()
        return identidad
    temporal = Path(tempfile.mkdtemp(prefix=".revision-", dir=destino))
    try:
        for nombre, dato in esperado.items():
            (temporal / nombre).write_bytes(dato)
        try:
            temporal.rename(carpeta)
        except FileExistsError:
            comprobar_existente()
    finally:
        if temporal.exists():
            shutil.rmtree(temporal)
    return identidad


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--caso", required=True, type=Path)
    parser.add_argument("--destino", required=True, type=Path)
    args = parser.parse_args(argv)
    try:
        print(empaquetar(args.caso, args.destino))
        return 0
    except (OSError, ValueError, KeyError, TypeError) as error:
        print("No se pudo preparar la revisión: "+str(error), file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
