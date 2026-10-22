"""Comprueba la referencia conservada sin ejecutar el proyecto de origen."""
import argparse
import hashlib
import json
import sys
import zipfile
from pathlib import Path, PurePosixPath


def git_blob(data):
    return hashlib.sha1(b"blob " + str(len(data)).encode() + b"\0" + data).hexdigest()


def verificar(original_zip=None):
    base = Path(__file__).resolve().parent
    codigo = base / "codigo"
    manifiesto = json.loads((base / "manifiesto.json").read_text(encoding="utf-8"))
    archivos = manifiesto["archivos"]
    esperados = {a["ruta"] for a in archivos}
    if len(esperados) != len(archivos) or len(archivos) != 352:
        raise ValueError("Inventario incompleto o con rutas duplicadas")
    reales = {p.relative_to(codigo).as_posix() for p in codigo.rglob("*") if p.is_file()}
    if reales != esperados:
        raise ValueError(f"Inventario distinto: faltan {len(esperados - reales)}, sobran {len(reales - esperados)}")
    sustituidos = 0
    for archivo in archivos:
        ruta = PurePosixPath(archivo["ruta"])
        if ruta.is_absolute() or ".." in ruta.parts:
            raise ValueError("Ruta no permitida en el inventario")
        data = (codigo / archivo["ruta"]).read_bytes()
        if hashlib.sha256(data).hexdigest() != archivo["sha256Referencia"]:
            raise ValueError("SHA256 distinto: " + archivo["ruta"])
        if archivo["cambios"]:
            sustituidos += 1
        elif git_blob(data) != archivo["blobOriginalGit"]:
            raise ValueError("Archivo original modificado: " + archivo["ruta"])
    if sustituidos != 2:
        raise ValueError("No coinciden las dos sustituciones declaradas")
    contraste = "no solicitado"
    if original_zip:
        zip_path = Path(original_zip)
        if hashlib.sha256(zip_path.read_bytes()).hexdigest() != manifiesto["archivoDescargadoSha256"]:
            raise ValueError("El ZIP no coincide con el archivo de origen registrado")
        with zipfile.ZipFile(zip_path) as archive:
            miembros = {m.filename.split("/", 1)[1]: m for m in archive.infolist() if not m.is_dir()}
            if set(miembros) != esperados:
                raise ValueError("El inventario del ZIP no coincide")
            for archivo in archivos:
                if git_blob(archive.read(miembros[archivo["ruta"]])) != archivo["blobOriginalGit"]:
                    raise ValueError("El blob de origen no coincide: " + archivo["ruta"])
        contraste = "352 blobs originales coinciden"
    return {
        "resultado": "correcto",
        "archivos": len(archivos),
        "identicosAlOrigen": len(archivos) - sustituidos,
        "archivosConSustituciones": sustituidos,
        "contrasteZip": contraste,
        "commitOriginal": manifiesto["commitOriginal"],
    }


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--original-zip", help="ZIP local descargado del commit fijado")
    args = parser.parse_args()
    try:
        print(json.dumps(verificar(args.original_zip), ensure_ascii=False, indent=2))
    except (OSError, ValueError, KeyError, zipfile.BadZipFile) as error:
        print("Referencia no verificada: " + str(error), file=sys.stderr)
        sys.exit(1)
