"""Extrae Santa Cruz del ZIP COD-AB fijado, sin modificar sus geometrías."""

import argparse
import hashlib
import json
import re
import unicodedata
import zipfile
from pathlib import Path
from urllib.request import urlopen


RAIZ = Path(__file__).resolve().parents[1]
VERSION = "scz-codab-v02-1.0.0-propuesta"
ARCHIVOS = {1: "departamentos.geojson", 2: "provincias.geojson", 3: "municipios.geojson"}
TIPOS = {1: "departamento", 2: "provincia", 3: "municipio"}


def leer_json(archivo):
    return json.loads(archivo.read_text(encoding="utf-8"))


def guardar_json(archivo, contenido, compacto=False):
    archivo.parent.mkdir(parents=True, exist_ok=True)
    opciones = {"separators": (",", ":")} if compacto else {"indent": 2}
    # write_text convierte LF a CRLF en Windows; las huellas deben ser portables.
    archivo.write_bytes((json.dumps(contenido, ensure_ascii=False, allow_nan=False, **opciones) + "\n").encode("utf-8"))


def clave_nombre(nombre):
    # Solo concilia etiquetas. La jerarquía siempre procede de los PCODE de HDX.
    nombre = nombre.split(" (")[0]
    nombre = unicodedata.normalize("NFKD", nombre).encode("ascii", "ignore").decode().lower()
    return re.sub(r"[^a-z0-9]", "", nombre)


def preparar(zip_origen):
    fuentes = leer_json(RAIZ / "fuentes/fuentes.json")
    nombres = leer_json(RAIZ / "fuentes/nombres_ine_2024.json")["nombres"]
    if hashlib.sha256(zip_origen.read_bytes()).hexdigest() != fuentes["geometrias"]["sha256Zip"]:
        raise ValueError("El ZIP no coincide con la fuente fijada. Revisar la nueva versión antes de actualizar.")
    etiquetas = {clave_nombre(n): n for n in nombres}
    if len(etiquetas) != 56:
        raise ValueError("Los nombres INE deben ser 56 y no ambiguos al conciliarse.")

    catalogo = []
    conciliados = []
    usados = set()
    with zipfile.ZipFile(zip_origen) as archivo_zip:
        for nivel in (1, 2, 3):
            origen = json.loads(archivo_zip.read(f"bol_admin{nivel}.geojson"))
            if origen["crs"]["properties"]["name"] != "urn:ogc:def:crs:OGC:1.3:CRS84":
                raise ValueError("El CRS de origen cambió; no se reproyecta silenciosamente.")
            if len(origen["features"]) != fuentes["geometrias"]["unidadesBolivia"][f"ADM{nivel}"]:
                raise ValueError("Cambió la cobertura del conjunto de origen.")
            seleccion = sorted(
                (f for f in origen["features"] if f["properties"]["adm1_pcode"] == "BO07"),
                key=lambda f: f["properties"][f"adm{nivel}_pcode"],
            )
            if len(seleccion) != {1: 1, 2: 15, 3: 56}[nivel]:
                raise ValueError(f"Cantidad inesperada de unidades ADM{nivel} en Santa Cruz.")
            features = []
            for original in seleccion:
                p = original["properties"]
                identificador = p[f"adm{nivel}_pcode"]
                nombre_fuente = p[f"adm{nivel}_name"]
                padre = None if nivel == 1 else p[f"adm{nivel-1}_pcode"]
                nombre = nombre_fuente
                if nivel == 3:
                    clave = clave_nombre(nombre_fuente)
                    if clave not in etiquetas or clave in usados:
                        raise ValueError(f"Conciliación INE ausente o ambigua: {identificador} {nombre_fuente}")
                    usados.add(clave)
                    nombre = etiquetas[clave]
                    conciliados.append({"identificador": identificador, "nombreGeometria": nombre_fuente, "nombreINE2024": nombre})
                registro = {
                    "identificador": identificador,
                    "tipo": TIPOS[nivel],
                    "nombre": nombre,
                    "nombreFuenteGeometria": nombre_fuente,
                    "padre": padre,
                    "fuenteGeometria": fuentes["geometrias"]["identificador"],
                    "archivoLimite": "limites/" + ARCHIVOS[nivel],
                    "denominacionAutonomiaINE": nivel == 3 and "(Autonomía" in nombre,
                }
                catalogo.append(registro)
                features.append({
                    "type": "Feature", "id": identificador,
                    "properties": {
                        "identificador": identificador, "nombre": nombre, "tipo": TIPOS[nivel],
                        "padre": padre, "codigoFuente": identificador, "versionFuente": p["version"],
                    },
                    "geometry": original["geometry"],
                })
            guardar_json(RAIZ / "datos/limites" / ARCHIVOS[nivel], {
                "type": "FeatureCollection", "versionCatalogo": VERSION,
                "fuente": fuentes["geometrias"]["identificador"],
                "origen": fuentes["geometrias"]["pagina"],
                "atribucion": fuentes["geometrias"]["atribucion"],
                "licencia": fuentes["geometrias"]["urlLicencia"],
                "fechaGeometrias": fuentes["geometrias"]["fechaGeometrias"],
                "sistemaCoordenadas": "OGC:CRS84; longitud, latitud; grados decimales",
                "features": features,
            }, compacto=True)

    if usados != set(etiquetas):
        raise ValueError("Hay nombres del INE sin geometría conciliada.")
    guardar_json(RAIZ / "datos/catalogo.json", {
        "version": VERSION, "estado": "propuesta_pendiente_revision",
        "espacioIdentificadores": "PCODE_OCHA_CODAB_BOL_v02",
        "crs": "OGC:CRS84", "ordenCoordenadas": ["longitud", "latitud"],
        "territorios": sorted(catalogo, key=lambda r: r["identificador"]),
    })
    guardar_json(RAIZ / "fuentes/conciliacion_nombres.json", {
        "metodo": "Etiqueta sin diacríticos, espacios ni paréntesis; unión uno a uno de los 56 nombres. Sin inferencia espacial o por proximidad.",
        "correspondencias": sorted(conciliados, key=lambda r: r["identificador"]),
    })
    archivos = [RAIZ / "datos/catalogo.json", *sorted((RAIZ / "datos/limites").glob("*.geojson")),
                *sorted((RAIZ / "fuentes").glob("*.json"))]
    guardar_json(RAIZ / "manifiesto.json", {
        "versionCatalogo": VERSION,
        "archivos": {p.relative_to(RAIZ).as_posix(): hashlib.sha256(p.read_bytes()).hexdigest() for p in archivos},
    })
    print("Catálogo preparado: 1 departamento, 15 provincias y 56 unidades de nivel municipal; coordenadas de origen conservadas.")


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--zip", type=Path, help="ZIP COD-AB original verificado")
    parser.add_argument("--descargar", action="store_true", help="Descargar el recurso publicado y exigir su SHA256 fijado")
    args = parser.parse_args()
    if args.descargar:
        destino = RAIZ.parents[1] / "tmp/bol_limites.geojson.zip"
        destino.parent.mkdir(parents=True, exist_ok=True)
        fuente = leer_json(RAIZ / "fuentes/fuentes.json")["geometrias"]
        with urlopen(fuente["recurso"], timeout=90) as respuesta:
            destino.write_bytes(respuesta.read())
        preparar(destino)
    elif args.zip:
        preparar(args.zip)
    else:
        parser.error("Indique --zip o --descargar.")
