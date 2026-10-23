"""Envuelve el cálculo real conservado en el contrato candidato; no es una respuesta HTTP."""
import json
from referencia import RAIZ, reproducir


def ejemplo():
    r = reproducir()
    valido = r["resultado"]["valor"] is not None
    return {
        "versionContrato": "sequias-0.1-propuesta",
        "datos": [{"idMuestra": r["muestra"], "territorioContexto": r["territorioContexto"], "municipioDelCentroCelda": r["municipioDelCentroCelda"],
                   "coordenadaSolicitada": r["coordenadaSolicitada"], "coordenadaCelda": r["coordenadaCelda"],
                   "naturaleza": r["naturaleza"], "fuentesRef": ["open_meteo_era5"], "metodo": r["metodo"],
                   "precipitacionAcumuladaMm": r["precipitacionAcumuladaMm"], "spi": r["resultado"]}],
        "consulta": {"estado": r["estado"], **r["periodo"], "municipio": "BO070101", "provincia": "BO0701",
                     "muestra": r["muestra"], "obtencion": {"tipo": "reproduccion_captura", "obtenidoEn": r["fuentes"][-1]["obtenidoEn"], "esRespuestaHttp": False}},
        "fuentes": [{"id": "open_meteo_era5", "proveedor": "Open-Meteo", "producto": "ERA5",
                     "naturaleza": "reanalisis", "variable": "precipitation_sum", "unidad": "mm",
                     "resolucionGrados": 0.25, "licencia": "https://creativecommons.org/licenses/by/4.0/",
                     "atribucion": "Open-Meteo.com; ECMWF / Copernicus Climate Change Service, ERA5.",
                     "fechaActualizacion": None, "capturas": r["fuentes"]}],
        "resumen": {"totalMuestras": 1, "muestrasConIndice": int(valido), "muestrasSinIndice": int(not valido),
                    "muestrasConDeficit": int(valido and r["resultado"]["valor"] <= -1),
                    "spiMinimo": r["resultado"]["valor"], "unidadConteo": "muestras_no_superficie"},
        "cobertura": {"estadoEspacial": "muestras_puntuales", "municipiosEnFiltro": 1,
                      "municipiosConMuestra": 1, "municipiosConIndice": int(valido), "celdasUnicas": 1, "superficieEvaluadaKm2": None},
        "advertencias": ["Ejemplo candidato generado; no se consultó una API de sequías implementada.",
                         r["limitacion"], "El centro de celda cae en BO070103; el punto solicitado está en BO070101. No reasignar el contexto territorial.",
                         "Sin contraste con estaciones locales; no es el producto SPI oficial de Copernicus."],
        "paginacion": {"aplica": False},
        "representacionMapa": {"tipo": "Point", "alcance": "conjunto", "rellenarPoligonos": False},
    }


if __name__ == "__main__":
    salida = RAIZ / "compartido/api/sequias-propuesta/respuesta.ejemplo.json"
    salida.write_text(json.dumps(ejemplo(), indent=2, ensure_ascii=False, allow_nan=False) + "\n", encoding="utf-8")
    print(salida)
