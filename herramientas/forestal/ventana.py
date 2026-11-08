"""Resumen anual por ventana desde una captura MET-03, sin red ni publicación."""
import argparse
from copy import deepcopy
from decimal import Context, Decimal, localcontext
import hashlib
import json
from pathlib import Path
import sys

try:
    from .verificar_fuente import leer_json, revisar
except ImportError:
    from verificar_fuente import leer_json, revisar


def acotar(informe, desde, hasta):
    """Conserva identidades y cobertura; recalcula solo el período solicitado."""
    disponibles = informe["fuente"]["anios"]
    if (type(desde) is not int or type(hasta) is not int or desde > hasta
            or desde not in disponibles or hasta not in disponibles):
        raise ValueError("Elige años publicados y un intervalo ordenado.")
    ventana = list(range(desde, hasta + 1))
    if any(anio not in disponibles for anio in ventana):
        raise ValueError("La versión no publica todos los años solicitados.")
    filas = {}
    for fila in informe["serie"]:
        anio = fila["anio"]
        if type(anio) is not int or anio not in disponibles or anio in filas:
            raise ValueError("Año duplicado o ajeno a la versión.")
        estado, valor = fila["estado"], fila["perdida_ha"]
        if estado == "dato" and isinstance(valor, str):
            try:
                area = Decimal(valor)
            except ArithmeticError as error:
                raise ValueError("Área inválida.") from error
            if not area.is_finite() or area < 0:
                raise ValueError("Área negativa o no finita.")
        elif estado != "no_informado" or valor is not None:
            raise ValueError("El estado y el área no coinciden.")
        filas[anio] = fila

    serie = [deepcopy(filas.get(anio, {
        "anio": anio, "estado": "no_informado", "perdida_ha": None,
    })) for anio in ventana]
    areas = [Decimal(f["perdida_ha"]) for f in serie if f["estado"] == "dato"]
    subtotal = None
    if areas:
        # Suma exacta de los decimales recibidos, independiente del contexto global.
        decimales = max(0, max(-a.as_tuple().exponent for a in areas))
        enteros = max(1, max(a.adjusted() + 1 for a in areas))
        with localcontext(Context(prec=enteros + decimales + len(str(len(areas))) + 1)):
            subtotal = format(sum(areas, Decimal(0)), "f")
    salida = deepcopy(informe)
    salida["consulta"] = {"desde": desde, "hasta": hasta, "extremos_incluidos": True}
    salida["serie"] = serie
    salida["resumen"] = {
        "anios_con_dato": len(areas),
        "anios_sin_dato": [f["anio"] for f in serie if f["estado"] == "no_informado"],
        "subtotal_informado_ha": subtotal,
        "total_periodo_ha": subtotal if len(areas) == len(ventana) else None,
    }
    return salida


def consultar(directorio, desde, hasta):
    """Verifica los cuatro originales antes de calcular una ventana."""
    directorio = Path(directorio)
    recibos = (directorio / "consultas.json").read_bytes()
    informe = revisar(directorio)
    if recibos != (directorio / "consultas.json").read_bytes():
        raise ValueError("La captura cambió durante la lectura; vuelve a intentarlo.")
    salida = acotar(informe, desde, hasta)
    salida["reproduccion"] = {
        "recibos_sha256": hashlib.sha256(recibos).hexdigest(),
        "archivos_sha256": {
            r["archivo"]: r["sha256"] for r in leer_json(recibos)
        },
        "lectura": "captura_local_verificada",
    }
    return salida


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--directorio", type=Path, required=True)
    parser.add_argument("--desde", type=int, required=True)
    parser.add_argument("--hasta", type=int, required=True)
    parser.add_argument("--salida", type=Path)
    args = parser.parse_args(argv)
    try:
        salida = consultar(args.directorio, args.desde, args.hasta)
        contenido = json.dumps(salida, ensure_ascii=False, indent=2) + "\n"
        if args.salida:
            # Nunca sustituir evidencia ni un resultado anterior.
            with args.salida.open("x", encoding="utf-8", newline="\n") as archivo:
                archivo.write(contenido)
            print(json.dumps(salida["resumen"], ensure_ascii=False))
        else:
            print(contenido, end="")
        return 0
    except (ValueError, KeyError, TypeError, OSError) as error:
        print("No se pudo preparar el resumen: " + str(error), file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
