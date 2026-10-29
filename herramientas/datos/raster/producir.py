"""Produce un PNG de temperatura y una receta #62 desde un GRIB2 GFS o NetCDF ERA5 local."""
import argparse
from datetime import datetime
import hashlib
import importlib.metadata
import json
import os
from pathlib import Path
import shutil
import tempfile

import numpy as np
from PIL import Image
import xarray as xr

SA = (-82.0, -57.0, -34.0, 13.0)
MAX_PIXELES = 2_200_000
MAX_PNG = 5_242_880
MAX_ENTRADA = 128 * 1024 * 1024
PERFILES = {
    "gfs": {"proveedor": "NOAA/NWS/NCEP", "fuente": "NOAA/NCEP · GFS 0.25°",
            "producto": "Modelo GFS · temperatura instantánea a 2 m",
            "atribucion": "Datos NOAA/NWS/NCEP. Conversión y codificación GeoSur; sin aval de NOAA/NWS.",
            "documentacion": "https://www.nco.ncep.noaa.gov/pmb/products/gfs/gfs.t00z.pgrb2.0p25.f000.shtml"},
    "era5": {"proveedor": "Copernicus Climate Change Service (C3S)/ECMWF", "fuente": "Copernicus C3S/ECMWF · ERA5",
             "producto": "Reanálisis ERA5 · temperatura instantánea a 2 m",
             "atribucion": "Contiene información Copernicus Climate Change Service modificada por GeoSur. C3S/ECMWF no avalan esta transformación.",
             "documentacion": "https://cds.climate.copernicus.eu/datasets/reanalysis-era5-single-levels"},
}


def fecha_utc(valor):
    v = np.asarray(valor)
    if v.size != 1 or not np.issubdtype(v.dtype, np.datetime64) or np.isnat(v.reshape(-1)[0]):
        raise ValueError("Se requiere un instante gregoriano único en los metadatos.")
    instante = v.reshape(-1)[0]
    if instante != instante.astype("datetime64[s]"):
        raise ValueError("No se admiten instantes con fracciones de segundo.")
    return np.datetime_as_string(instante, unit="s") + "Z"


def instante_argumento(texto):
    fecha = datetime.strptime(texto, "%Y-%m-%dT%H:%M:%SZ")
    if fecha.strftime("%Y-%m-%dT%H:%M:%SZ") != texto:
        raise ValueError("Usa fecha UTC ISO 8601 con segundos y Z.")
    return np.datetime64(texto[:-1], "s")


def eje_regular(coordenada, nombre):
    valores = np.asarray(coordenada.values, dtype=float)
    if valores.ndim != 1 or len(valores) < 2 or not np.isfinite(valores).all():
        raise ValueError(f"{nombre}: se requiere un eje geográfico unidimensional de al menos dos muestras.")
    paso = np.diff(valores)
    if not ((paso > 0).all() or (paso < 0).all()) or not np.allclose(paso, paso[0], rtol=1e-6, atol=1e-7):
        raise ValueError(f"{nombre}: eje irregular, duplicado o no monótono.")
    return valores


def seleccionar(ds, perfil, instante=None):
    if "t2m" not in ds.data_vars:
        raise ValueError("Falta t2m: no se elige otra variable por posición.")
    campo = ds.t2m
    if campo.attrs.get("units") != "K" or campo.attrs.get("standard_name") != "air_temperature":
        raise ValueError("Se requiere temperatura del aire en Kelvin, sin conversiones inferidas.")
    if perfil == "gfs":
        if (ds.attrs.get("GRIB_edition") != 2 or ds.attrs.get("GRIB_centre") != "kwbc" or
                campo.attrs.get("GRIB_paramId") != 167 or campo.attrs.get("GRIB_gridType") != "regular_ll" or
                campo.attrs.get("GRIB_typeOfLevel") != "heightAboveGround" or campo.attrs.get("GRIB_stepType") != "instant" or
                "heightAboveGround" not in ds.coords or float(ds.heightAboveGround.values) != 2):
            raise ValueError("El insumo no cumple GRIB2 NCEP, temperatura instantánea a 2 m, rejilla regular lat/lon.")
        if any(k not in ds.coords for k in ("time", "step", "valid_time")):
            raise ValueError("Faltan ciclo, plazo o fecha válida del GFS.")
        ciclo, valido = fecha_utc(ds.time.values), fecha_utc(ds.valid_time.values)
        paso = np.asarray(ds.step.values)
        if paso.size != 1 or not np.issubdtype(paso.dtype, np.timedelta64):
            raise ValueError("Selecciona un archivo GFS de un único plazo.")
        horas = float(paso.reshape(-1)[0] / np.timedelta64(1, "h"))
        if not horas.is_integer() or not 0 <= horas <= 384 or np.datetime64(ciclo[:-1]) + paso.reshape(-1)[0] != np.datetime64(valido[:-1]):
            raise ValueError("Ciclo, plazo y fecha válida GFS no concuerdan.")
        inicio = datetime.strptime(ciclo, "%Y-%m-%dT%H:%M:%SZ")
        if inicio.hour not in (0, 6, 12, 18) or inicio.minute or inicio.second:
            raise ValueError("Ciclo GFS inválido.")
        tiempo = {"instante": valido, "ciclo": ciclo, "plazo_horas": int(horas),
                  "tipo_temporal": "analisis" if horas == 0 else "pronostico",
                  "forma_tierra_grib": campo.attrs.get("GRIB_shapeOfTheEarth")}
    else:
        if campo.attrs.get("GRIB_paramId") != 167 and "2 metre temperature" not in campo.attrs.get("long_name", "").lower():
            raise ValueError("El NetCDF debe identificar temperatura a 2 m (parámetro 167).")
        dimensiones_tiempo = [d for d in ("time", "valid_time") if d in campo.dims]
        if len(dimensiones_tiempo) != 1:
            raise ValueError("ERA5 requiere una dimensión time o valid_time inequívoca.")
        dimension = dimensiones_tiempo[0]
        valores = np.asarray(ds[dimension].values)
        if not np.issubdtype(valores.dtype, np.datetime64) or valores.ndim != 1 or np.isnat(valores).any() or len(np.unique(valores)) != len(valores):
            raise ValueError("Eje temporal inválido o duplicado.")
        if instante is None:
            if len(valores) != 1:
                raise ValueError("El NetCDF tiene varias fechas; indica --instante exacto.")
            indice = 0
        else:
            coincidencias = np.flatnonzero(valores == instante_argumento(instante))
            if len(coincidencias) != 1:
                raise ValueError("El instante no existe en el NetCDF; no se toma una fecha cercana.")
            indice = int(coincidencias[0])
        campo = campo.isel({dimension: indice})
        tiempo = {"instante": fecha_utc(valores[indice]), "tipo_temporal": "reanalisis"}
    if instante is not None and tiempo["instante"] != instante:
        raise ValueError("La fecha solicitada no coincide con la fecha válida del insumo.")
    if any(d not in ("latitude", "longitude") for d in campo.dims):
        raise ValueError("No se eligen niveles, miembros de ensemble o dimensiones extra silenciosamente.")
    if set(campo.dims) != {"latitude", "longitude"}:
        raise ValueError("Se requieren dimensiones latitude y longitude.")
    if campo.attrs.get("grid_mapping"):
        raise ValueError("No se admiten rejillas rotadas o proyectadas en este productor.")
    for nombre, unidad in (("latitude", "degrees_north"), ("longitude", "degrees_east")):
        if campo[nombre].attrs.get("units") != unidad:
            raise ValueError(f"Faltan unidades geográficas verificables en {nombre}.")
    lat = eje_regular(campo.latitude, "latitude")
    lon = eje_regular(campo.longitude, "longitude")
    if (lat.min() < -90 or lat.max() > 90 or lon.min() < -180 or lon.max() > 360 or lon.max() - lon.min() > 360):
        raise ValueError("Coordenadas fuera del dominio geográfico.")
    # Reordenar muestras, sin remuestrear ni alterar valores. La rejilla de salida usa longitudes firmadas.
    campo = campo.assign_coords(longitude=((lon + 180) % 360) - 180).sortby("longitude").sortby("latitude", ascending=False)
    campo = campo.sel(longitude=slice(SA[0], SA[2]), latitude=slice(SA[3], SA[1])).transpose("latitude", "longitude")
    lat = eje_regular(campo.latitude, "latitude recortada")
    lon = eje_regular(campo.longitude, "longitude recortada")
    if campo.size > MAX_PIXELES:
        raise ValueError("La rejilla excede el límite de píxeles del consumidor #62.")
    if perfil == "gfs" and (not np.isclose(abs(lat[1] - lat[0]), .25) or not np.isclose(lon[1] - lon[0], .25)):
        raise ValueError("Este perfil requiere GFS 0.25°, no otra resolución declarada como equivalente.")
    rejilla = {"proyeccion": "EPSG:4326", "registro": "nodos", "filas": "norte-sur",
               "oeste": float(lon[0]), "este": float(lon[-1]), "sur": float(lat[-1]), "norte": float(lat[0])}
    return campo, tiempo, rejilla


def codificar(campo):
    celsius = np.asarray(campo, dtype=np.float64) - 273.15
    finitos = np.isfinite(celsius)
    validos = finitos & (celsius >= -50) & (celsius <= 50)
    if not validos.any():
        raise ValueError("No hay valores representables; no se produce un cuadro vacío.")
    rgba = np.zeros((*celsius.shape, 4), dtype=np.uint8)
    codigos = np.rint((celsius[validos] + 50) * 255 / 100).astype(np.uint8)
    rgba[validos, :3] = codigos[:, None]
    rgba[validos, 3] = 255
    estadisticas = {"validos": int(validos.sum()), "ausentes_no_finitos": int((~finitos).sum()),
                    "fuera_de_escala": int((finitos & ~validos).sum()), "politica_fuera_de_escala": "null",
                    "minimo_celsius_valido": float(celsius[validos].min()), "maximo_celsius_valido": float(celsius[validos].max()),
                    "error_cuantizacion_maximo_celsius": 100 / 510}
    return rgba, estadisticas


def producir(entrada, salida, perfil, instante=None, controlado=False):
    origen, destino = Path(entrada), Path(salida)
    if not origen.is_file() or origen.is_symlink() or origen.stat().st_size > MAX_ENTRADA:
        raise ValueError("Elige un archivo local regular de hasta 128 MiB.")
    if destino.exists() or destino.is_symlink() or not destino.parent.is_dir():
        raise ValueError("La salida debe ser un directorio nuevo dentro de una carpeta existente.")
    with origen.open("rb") as archivo:
        huella = hashlib.file_digest(archivo, "sha256").hexdigest()
    opciones = {"engine": "netcdf4", "mask_and_scale": True}
    if perfil == "gfs":
        opciones = {"engine": "cfgrib", "backend_kwargs": {"indexpath": "", "filter_by_keys": {
            "typeOfLevel": "heightAboveGround", "level": 2, "shortName": "2t"}, "read_keys": ["shapeOfTheEarth"]}}
    with xr.open_dataset(origen, **opciones) as ds:
        if ds.attrs.get("geosur_fixture") and not controlado:
            raise ValueError("Insumo marcado como fixture: requiere --datos-controlados.")
        campo, tiempo, rejilla = seleccionar(ds, perfil, instante)
        rgba, estadisticas = codificar(campo)
    # Detectar cambios accidentales de la entrada durante la lectura.
    with origen.open("rb") as archivo:
        if hashlib.file_digest(archivo, "sha256").hexdigest() != huella:
            raise ValueError("El insumo cambió durante la lectura.")
    fechas = tiempo["instante"].translate(str.maketrans("", "", "-:TZ"))
    identidad = f"{perfil}-temp-{fechas}-{huella[:12]}" + ("-prueba" if controlado else "")
    archivo_png = "temperatura/" + identidad + ".png"
    proveedor = PERFILES[perfil]
    cuadro = {"id": identidad, "capa": "temperatura", "instante": tiempo["instante"], "archivo": archivo_png,
              "codificacion": "temp-era5", "fuente": "Datos controlados de prueba; sin captura del proveedor" if controlado else proveedor["fuente"],
              "producto": "Fixture técnico; no es una observación ni captura real" if controlado else proveedor["producto"],
              "rejilla": rejilla, "unidad": "°C", "atribucion": proveedor["atribucion"] if not controlado else "Fixture sintético generado para pruebas de contrato."}
    receta = {"version": 1, "cuadros": [cuadro]}
    metadata = {"version": 1, "perfil": perfil, "origen": "controlado" if controlado else "estimado", "datos_controlados": controlado,
                "proveedor_declarado": proveedor["proveedor"], "producto": cuadro["producto"], "atribucion": cuadro["atribucion"],
                "documentacion": proveedor["documentacion"], "tiempo": tiempo, "rejilla": rejilla,
                "unidades": {"entrada": "K", "salida": "°C", "conversion": "celsius = kelvin - 273.15"},
                "codificacion": {"nombre": "temp-era5", "formula": "celsius = R/255*100-50", "ausencia": "A=0 -> null", "canales": "RGBA, 8 bits"},
                "entrada": {"sha256": huella, "bytes": origen.stat().st_size}, "estadisticas": estadisticas,
                "software": {p: importlib.metadata.version(p) for p in ("numpy", "xarray", "cfgrib", "eccodes", "Pillow", "netCDF4")}}
    temporal = Path(tempfile.mkdtemp(prefix=".raster-", dir=destino.parent))
    try:
        (temporal / "temperatura").mkdir()
        Image.fromarray(rgba).save(temporal / archivo_png, format="PNG", compress_level=9, optimize=False)
        png = (temporal / archivo_png).read_bytes()
        if len(png) > MAX_PNG:
            raise ValueError("El PNG excede el límite del consumidor #62.")
        metadata["salida"] = {"archivo": archivo_png, "sha256": hashlib.sha256(png).hexdigest(), "bytes": len(png),
                              "ancho": rgba.shape[1], "alto": rgba.shape[0]}
        for nombre, datos in (("receta.json", receta), ("procedencia.json", metadata)):
            (temporal / nombre).write_text(json.dumps(datos, ensure_ascii=False, indent=2, allow_nan=False) + "\n", encoding="utf-8")
        if destino.exists() or destino.is_symlink():
            raise ValueError("La salida apareció durante el proceso; no se sobrescribe.")
        os.rename(temporal, destino)
        return metadata
    finally:
        if temporal.is_dir():
            if temporal.is_symlink() or temporal.resolve().parent != destino.parent.resolve():
                raise ValueError("La carpeta temporal cambió; se conserva para revisión.")
            shutil.rmtree(temporal)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("perfil", choices=PERFILES)
    parser.add_argument("--entrada", required=True)
    parser.add_argument("--salida", required=True)
    parser.add_argument("--instante")
    parser.add_argument("--datos-controlados", action="store_true")
    args = parser.parse_args()
    try:
        resultado = producir(args.entrada, args.salida, args.perfil, args.instante, args.datos_controlados)
        print(json.dumps({"estado": "producido", "instante": resultado["tiempo"]["instante"], "salida": resultado["salida"],
                          "estadisticas": resultado["estadisticas"], "datos_controlados": resultado["datos_controlados"]}, ensure_ascii=False))
        return 0
    except (ValueError, OSError, KeyError, RuntimeError) as error:
        parser.exit(2, "No se produjo el lote: " + str(error) + "\n")


if __name__ == "__main__":
    raise SystemExit(main())
