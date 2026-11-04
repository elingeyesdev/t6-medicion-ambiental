"""Datos controlados de contrato. No contienen capturas NOAA ni datos de estaciones."""
from pathlib import Path

import eccodes as ec
import numpy as np
import xarray as xr


def grib_controlado(ruta, cambios=None):
    mensaje = ec.codes_grib_new_from_samples("regular_ll_sfc_grib2")
    try:
        claves = {"centre": "kwbc", "Ni": 3, "Nj": 3, "latitudeOfFirstGridPointInDegrees": -17,
                  "latitudeOfLastGridPointInDegrees": -17.5, "longitudeOfFirstGridPointInDegrees": 278,
                  "longitudeOfLastGridPointInDegrees": 278.5, "iDirectionIncrementInDegrees": .25,
                  "jDirectionIncrementInDegrees": .25, "jScansPositively": 0, "dataDate": 20261027,
                  "dataTime": 600, "typeOfLevel": "heightAboveGround", "level": 2,
                  "shortName": "2t", "stepType": "instant", "forecastTime": 0, "bitmapPresent": 1,
                  "missingValue": 9999, "bitsPerValue": 24}
        for clave, valor in (claves | (cambios or {})).items():
            ec.codes_set(mensaje, clave, valor)
        # Tres temperaturas conocidas, faltante, fuera de escala y ceros °C válidos.
        ec.codes_set_values(mensaje, [243.15, 263.15, 283.15, 9999, 343.15, 273.15, 273.15, 293.15, 303.15])
        with Path(ruta).open("wb") as archivo:
            ec.codes_write(mensaje, archivo)
    finally:
        ec.codes_release(mensaje)


def dataset_era5():
    datos = np.array([[[273.15, np.nan, 293.15], [253.15, 263.15, 283.15]],
                      [[274.15, 284.15, 294.15], [254.15, 264.15, 284.15]]], dtype=np.float64)
    return xr.Dataset({"t2m": (("valid_time", "latitude", "longitude"), datos,
                              {"units": "K", "standard_name": "air_temperature", "long_name": "2 metre temperature", "GRIB_paramId": 167})},
                      coords={"valid_time": np.array(["2026-10-27T12:00:00", "2026-10-27T13:00:00"], dtype="datetime64[s]"),
                              "latitude": ("latitude", [-18., -17.], {"units": "degrees_north"}),
                              "longitude": ("longitude", [298., 299., 300.], {"units": "degrees_east"})},
                      attrs={"geosur_fixture": "Datos controlados; no captura real", "title": "ERA5 fixture", "institution": "Prueba local"})


def netcdf_controlado(ruta):
    dataset_era5().to_netcdf(ruta, engine="netcdf4", encoding={"t2m": {"_FillValue": -9999.}})
