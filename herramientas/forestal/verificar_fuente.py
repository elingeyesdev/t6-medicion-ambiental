"""Comprobación de fuente para MET-03; no publica datos en GeoSur."""
import argparse
from datetime import datetime, timezone
from decimal import Decimal
import hashlib
import json
from pathlib import Path
import urllib.error
import urllib.parse
import urllib.request

BASE = "https://data-api.globalforestwatch.org/dataset/umd_tree_cover_loss"
YEAR = "umd_tree_cover_loss__year"
PERCENT = "umd_tree_cover_density_2000__percent"
YEARS = list(range(2001, 2026))
MAX_BYTES = 2_000_000
SQL = f"SELECT {YEAR}, SUM(area__ha) AS perdida_ha FROM data WHERE {YEAR} >= 2001 AND {PERCENT} >= 30 GROUP BY {YEAR} ORDER BY {YEAR}"


def urls():
    query = urllib.parse.urlencode({
        "sql": SQL, "aoi[type]": "admin", "aoi[country]": "BOL",
        "aoi[region]": "8", "aoi[provider]": "gadm", "aoi[version]": "4.1",
    })
    return {"producto": BASE, "version": BASE + "/v1.13",
            "campos": BASE + "/v1.13/fields",
            "serie": BASE + "/v1.13/download_by_aoi/json?" + query}


class SinRedireccion(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        raise ValueError("No se siguen redirecciones de la consulta fijada.")


def leer_json(raw):
    def constante_invalida(value):
        raise ValueError("JSON no finito: " + value)
    def objeto(pairs):
        result = {}
        for key, value in pairs:
            if key in result:
                raise ValueError("Clave JSON duplicada: " + key)
            result[key] = value
        return result
    return json.loads(raw, parse_float=Decimal, parse_constant=constante_invalida,
                      object_pairs_hook=objeto)


def validar_metadatos(producto, version, campos):
    if producto.get("data", {}).get("dataset") != "umd_tree_cover_loss":
        raise ValueError("Producto distinto del propuesto.")
    v = version.get("data", {})
    if (v.get("dataset"), v.get("version"), v.get("is_mutable")) != (
            "umd_tree_cover_loss", "v1.13", False):
        raise ValueError("La versión no coincide con la captura propuesta.")
    entries = campos.get("data")
    if not isinstance(entries, list):
        raise ValueError("No hay catálogo de campos.")
    catalogo = {}
    for item in entries:
        key = item.get("pixel_meaning")
        if key in catalogo:
            raise ValueError("Campo duplicado.")
        catalogo[key] = item
    if not {"area__ha", YEAR, PERCENT}.issubset(catalogo):
        raise ValueError("Faltan campos de la consulta.")
    expected = [{"value": i, "meaning": 2000 + i} for i in range(1, 26)]
    if catalogo[YEAR].get("values_table", {}).get("rows") != expected:
        raise ValueError("Cambió la codificación o el período; revisar el contrato.")
    return v


def normalizar_serie(rows):
    if not isinstance(rows, list) or len(rows) > len(YEARS):
        raise ValueError("Respuesta no tabular o demasiadas filas.")
    known = {}
    for row in rows:
        if not isinstance(row, dict) or set(row) != {YEAR, "perdida_ha"}:
            raise ValueError("Fila con campos inesperados.")
        year, area = row[YEAR], row["perdida_ha"]
        if type(year) is not int or year not in YEARS or year in known:
            raise ValueError("Año inválido, fuera de versión o duplicado.")
        if area is not None:
            if isinstance(area, bool) or not isinstance(area, (int, float, Decimal)):
                raise ValueError("Área no numérica.")
            area = Decimal(str(area))
            if not area.is_finite() or area < 0:
                raise ValueError("Área negativa o no finita.")
        known[year] = area
    series = [{"anio": y, "estado": "dato" if known.get(y) is not None else "no_informado",
               "perdida_ha": str(known[y]) if known.get(y) is not None else None}
              for y in YEARS]
    present = [known[y] for y in YEARS if known.get(y) is not None]
    subtotal = str(sum(present, Decimal(0))) if present else None
    return {
        "serie": series,
        "resumen": {
            "anios_con_dato": len(present),
            "anios_sin_dato": [r["anio"] for r in series if r["estado"] != "dato"],
            "subtotal_informado_ha": subtotal,
            "total_periodo_ha": subtotal if len(present) == len(YEARS) else None,
        },
    }


def capturar(destino):
    if destino.exists() and any(destino.iterdir()):
        raise ValueError("Usar un directorio nuevo para no sustituir evidencia.")
    destino.mkdir(parents=True, exist_ok=True)
    opener = urllib.request.build_opener(SinRedireccion())
    recibos = []
    for name, url in urls().items():
        stamp = datetime.now(timezone.utc).isoformat()
        req = urllib.request.Request(url, headers={
            "Accept": "application/json", "User-Agent": "GeoSur-MET03/1.0"})
        with opener.open(req, timeout=50) as response:
            raw = response.read(MAX_BYTES + 1)
            content = response.headers.get("Content-Type", "")
            if response.status != 200 or len(raw) > MAX_BYTES:
                raise ValueError("Respuesta fallida o superior al límite.")
            if "json" not in content.lower() or response.geturl() != url:
                raise ValueError("Respuesta inesperada.")
            leer_json(raw)
            (destino / (name + ".json")).write_bytes(raw)
            recibos.append({
                "archivo": name + ".json", "url": url, "consultado_utc": stamp,
                "http": response.status, "bytes": len(raw),
                "sha256": hashlib.sha256(raw).hexdigest(),
                "credenciales": False, "content_type": content,
            })
        (destino / "consultas.json").write_text(
            json.dumps(recibos, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    return revisar(destino)


def revisar(directorio):
    manifest = leer_json((directorio / "consultas.json").read_bytes())
    if not isinstance(manifest, list) or len(manifest) != 4:
        raise ValueError("Se requieren los cuatro recibos de esta captura.")
    expected = urls()
    docs = {}
    for receipt in manifest:
        name = receipt.get("archivo")
        if name not in {key + ".json" for key in expected} or name in docs:
            raise ValueError("Archivo inesperado o duplicado en los recibos.")
        key = name[:-5]
        if (receipt.get("url") != expected[key] or receipt.get("http") != 200
                or receipt.get("credenciales") is not False
                or "json" not in receipt.get("content_type", "").lower()):
            raise ValueError("Consulta distinta del método propuesto o fallida.")
        stamp = datetime.fromisoformat(receipt["consultado_utc"])
        if stamp.utcoffset() is None:
            raise ValueError("La fecha necesita zona horaria.")
        raw = (directorio / name).read_bytes()
        if (len(raw) > MAX_BYTES or len(raw) != receipt.get("bytes")
                or hashlib.sha256(raw).hexdigest() != receipt.get("sha256")):
            raise ValueError("No coincide la integridad de " + name)
        docs[name] = leer_json(raw)
    version = validar_metadatos(docs["producto.json"], docs["version.json"], docs["campos.json"])
    data = normalizar_serie(docs["serie.json"])
    return {
        "contrato": "propuesta-met03-1", "estado_metodo": "pendiente_revision",
        "integrado_en_api": False, "unidad": "ha", "decimales": "cadena_decimal",
        "fuente": {
            "producto": "umd_tree_cover_loss", "version": version["version"],
            "anios": YEARS, "frecuencia": "anual", "resolucion_nominal_m": 30,
            "licencia_datos": "CC-BY-4.0", "atribucion": "Hansen/UMD/Google/USGS/NASA; consulta GFW",
        },
        "criterio": {"base_cobertura_anio": 2000, "campo": PERCENT,
                     "operador": ">=", "valor": 30, "adoptado": False},
        "territorio": {
            "nombre_proveedor": "Santa Cruz", "pais_proveedor": "BOL",
            "region_proveedor": "8", "proveedor": "GADM", "version": "4.1",
            "nivel": "departamento_proveedor", "equivalencia_catalogo_local": "pendiente",
            "id_catalogo_local": None, "resultados_municipales": False,
        },
        "cobertura": {
            "es_cobertura_completa_de_observacion": None, "fraccion_valida": None,
            "nota": "La serie no informa la fracción de observaciones válidas; no se inventa 100%.",
        },
        **data, "capa": None,
        "limites": [
            "Pérdida de cobertura arbórea del producto; no causa ni ilegalidad.",
            "No equivale a deforestación confirmada, pérdida neta o cobertura actual.",
            "No hay datos de 2026 en esta versión ni frecuencia diaria.",
            "La suma del proveedor no es una estimación de área ajustada por exactitud.",
            "No valida límites municipales ni la equivalencia con el catálogo local.",
        ],
    }


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("accion", choices=["revisar", "capturar"])
    parser.add_argument("--directorio", type=Path, required=True)
    parser.add_argument("--salida", type=Path)
    parser.add_argument("--consultar-gfw", action="store_true")
    args = parser.parse_args()
    try:
        if args.accion == "capturar" and not args.consultar_gfw:
            parser.error("La captura requiere --consultar-gfw; revisar funciona sin red.")
        result = capturar(args.directorio) if args.accion == "capturar" else revisar(args.directorio)
        output = json.dumps(result, ensure_ascii=False, indent=2) + "\n"
        if args.salida:
            args.salida.parent.mkdir(parents=True, exist_ok=True)
            args.salida.write_text(output, encoding="utf-8")
        print(json.dumps(result["resumen"], ensure_ascii=True))
    except (ValueError, KeyError, TypeError, OSError, urllib.error.URLError) as e:
        parser.exit(1, "No se valida la captura: " + str(e) + "\n")


if __name__ == "__main__":
    main()
