"""Contraste local de raster, capa y áreas; no valida el método científico."""
import argparse
from collections import Counter
import hashlib
import json
import math
from pathlib import Path
import sys

from PIL import Image
from pyproj import Geod
from recorte_raster import Rangos, leer_banda


def leer_json(ruta, limite):
    with Path(ruta).open("rb") as archivo:
        dato = archivo.read(limite + 1)
    if len(dato) > limite:
        raise ValueError("Archivo demasiado grande.")
    def objeto(pares):
        salida = {}
        for k, v in pares:
            if k in salida:
                raise ValueError("Clave repetida.")
            salida[k] = v
        return salida
    def no_finito(_):
        raise ValueError("Número no finito.")
    return json.loads(dato, object_pairs_hook=objeto, parse_constant=no_finito)


def cerca(a, b, tolerancia=1e-7):
    if (isinstance(a, bool) or not isinstance(a, (int, float))
            or not math.isfinite(a) or abs(a-b) > tolerancia):
        raise ValueError("Valor o área no coincide.")


def contrastar(directorio):
    directorio = Path(directorio)
    r = leer_json(directorio / "resultado/resumen.json", 200000)
    capa = leer_json(directorio / "resultado/perdida.geojson", 16000000)
    recibos = leer_json(directorio / "captura/rangos.json", 100000)
    fuente = r["fuente"]
    if (fuente["version"] != "GFC-2025-v1.13" or fuente["tesela"] != "10S_070W"
            or fuente["cuadricula"] != {"epsg": 4326, "pixel": "area", "oeste": -70,
                "norte": -10, "paso_grados": 0.00025, "ancho": 40000, "alto": 40000}
            or r["estado"] != "recorte_tecnico_no_validado"
            or r["integrado_en_api"] is not False or r["criterio"]["adoptado"] is not False
            or r["cobertura_observaciones_fraccion"] is not None):
        raise ValueError("Identidad, cuadrícula o estado incompatibles.")
    if recibos["version"] != "gfc-rangos-1" or not 1 <= len(recibos["rangos"]) <= 24:
        raise ValueError("Recibo incompatible.")
    nombres = set()
    total_bytes = 0
    for i, recibo in enumerate(recibos["rangos"]):
        nombre = recibo["archivo"]
        if Path(nombre).name != nombre or nombre in nombres or not nombre.endswith(".bin"):
            raise ValueError("Archivo de rango inválido.")
        nombres.add(nombre)
        n = recibo["cantidad"]
        if type(n) is not int or not 0 < n <= 3000000 or total_bytes+n > 8000000:
            raise ValueError("Rango excedido.")
        with (directorio / "captura" / nombre).open("rb") as archivo:
            datos = archivo.read(n+1)
        if len(datos) != n or hashlib.sha256(datos).hexdigest() != recibo["sha256"]:
            raise ValueError("Rango alterado.")
        total_bytes += n
    with (directorio / "captura/rangos.json").open("rb") as archivo:
        huella = hashlib.sha256(archivo.read(100001)).hexdigest()
    if (huella != fuente["captura_sha256"] or total_bytes != fuente["bytes_transferidos_captura"]
            or len(nombres) != fuente["rangos"]):
        raise ValueError("El resumen no identifica esta captura.")
    v = r["ventana_pixeles"]
    c, f, w, h = (v[k] for k in ("columna", "fila", "ancho", "alto"))
    if (any(type(n) is not int for n in (c, f, w, h)) or
            not 1 <= w <= 128 or not 1 <= h <= 128 or c < 0 or f < 0 or
            c+w > 40000 or f+h > 40000):
        raise ValueError("Ventana inválida.")
    bandas = {}
    for banda, maximo in [("lossyear", 25), ("treecover2000", 100), ("datamask", 2)]:
        with Image.open(directorio / "resultado" / (banda+".png")) as imagen:
            if imagen.size != (w, h) or imagen.mode != "L":
                raise ValueError("Imagen fuera de ventana.")
            valores = list(imagen.getdata())
        if any(n > maximo for n in valores):
            raise ValueError("Valores raster fuera de versión.")
        bandas[banda] = valores
    original = Rangos(directorio / "captura", reproducir=True)
    for banda in ("lossyear", "treecover2000", "datamask"):
        _, pixeles = leer_banda(original, banda, c, f, w, h)
        if list(pixeles) != bandas[banda]:
            raise ValueError("El PNG no coincide con el raster original.")
    original.terminar()
    desde, hasta = r["consulta"]["desde"], r["consulta"]["hasta"]
    umbral = r["criterio"]["porcentaje"]
    if (any(type(n) is not int for n in (desde, hasta, umbral))
            or not 2001 <= desde <= hasta <= 2025 or not 1 <= umbral <= 100
            or r["consulta"]["extremos_incluidos"] is not True
            or r["criterio"]["base"] != 2000 or r["criterio"]["operador"] != ">="):
        raise ValueError("Consulta incompatible.")
    esperado = {}
    areas = {anio: [] for anio in range(2001, 2026)}
    contador = Counter()
    geod = Geod(ellps="WGS84")
    for i, (perdida, cobertura, mascara) in enumerate(zip(
            bandas["lossyear"], bandas["treecover2000"], bandas["datamask"])):
        contador[{0: "sin_dato_base", 1: "tierra_base", 2: "agua_base"}[mascara]] += 1
        if mascara != 1 or cobertura < umbral:
            continue
        contador["arbolado_base"] += 1
        if perdida == 0:
            continue
        y, x = divmod(i, w)
        oeste, norte = -70+(c+x)*0.00025, -10-(f+y)*0.00025
        este, sur = oeste+0.00025, norte-0.00025
        anillo = [[oeste, norte], [oeste, sur], [este, sur], [este, norte], [oeste, norte]]
        # Otro recorrido: área individual de cada celda en sus coordenadas reales.
        area = abs(geod.polygon_area_perimeter(*zip(*anillo))[0])/10000
        areas[2000+perdida].append(area)
        if desde <= 2000+perdida <= hasta:
            esperado[f"{f+y}-{c+x}"] = (2000+perdida, anillo, area)
    contador["perdida_periodo"] = len(esperado)
    for clave in ("sin_dato_base", "tierra_base", "agua_base", "arbolado_base", "perdida_periodo"):
        if r["conteos_pixeles"][clave] != contador[clave]:
            raise ValueError("Conteo no coincide con el raster.")
    bbox = [-70+c*0.00025, -10-(f+h)*0.00025, -70+(c+w)*0.00025, -10-f*0.00025]
    if len(r["bbox"]) != 4:
        raise ValueError("Extensión inválida.")
    for a, b in zip(r["bbox"], bbox):
        cerca(a, b, 1e-10)
    if len(r["serie"]) != 25 or [a["anio"] for a in r["serie"]] != list(areas):
        raise ValueError("Serie incompleta, desordenada o duplicada.")
    for fila in r["serie"]:
        cerca(fila["perdida_ha"], math.fsum(areas[fila["anio"]]))
    total = math.fsum(math.fsum(areas[a]) for a in range(desde, hasta+1))
    cerca(r["total_periodo_ha"], total)
    if capa["type"] != "FeatureCollection" or len(capa["features"]) != len(esperado):
        raise ValueError("Capa incompleta.")
    vistos = set()
    for feature in capa["features"]:
        identidad = feature["id"]
        if identidad in vistos or identidad not in esperado:
            raise ValueError("Celda duplicada o ajena.")
        vistos.add(identidad)
        anio, anillo, area = esperado[identidad]
        if (feature["type"] != "Feature" or feature["properties"]["anio"] != anio
                or feature["geometry"]["type"] != "Polygon"):
            raise ValueError("Año o geometría incompatible.")
        rings = feature["geometry"]["coordinates"]
        if len(rings) != 1 or len(rings[0]) != 5:
            raise ValueError("Anillo inválido.")
        for punto, original in zip(rings[0], anillo):
            if len(punto) != 2:
                raise ValueError("Coordenada inválida.")
            for a, b in zip(punto, original):
                cerca(a, b, 1e-10)
        cerca(feature["properties"]["area_ha"], area)
    return {"estado": "integridad_local_comprobada", "metodo_validado": False,
            "contraste_independiente_de_campo": False, "pixeles": w*h,
            "celdas_perdida_periodo": len(vistos), "captura_sha256": huella,
            "tolerancia_area_ha": 1e-7,
            "diferencia_total_ha": r["total_periodo_ha"]-total}


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--directorio", required=True, type=Path)
    parser.add_argument("--salida", required=True, type=Path)
    args = parser.parse_args(argv)
    try:
        informe = contrastar(args.directorio)
        with args.salida.open("x", encoding="utf-8", newline="\n") as salida:
            json.dump(informe, salida, ensure_ascii=False, indent=2)
            salida.write("\n")
        print("Contraste local correcto. El método sigue pendiente de validación.")
        return 0
    except (OSError, ValueError, TypeError, KeyError) as error:
        print("No se pudo contrastar el recorte: " + str(error), file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
