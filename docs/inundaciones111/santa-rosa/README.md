# Piloto de Agua en Santa Rosa del Sara

Recorte de revisión de 500 m alrededor del polígono COSMO-SkyMed n.º 18 por área publicada en EMSR853 AOI02 DEL v2. Período OPERA 6–18 de diciembre de 2025. La referencia independiente es del 17 de diciembre. Este recorrido prepara evidencia para #111 y #115. El registro en el catálogo de GeoSur requiere revisar y adoptar el producto.

## Reproducir desde la raíz

1. Inicia sesión en [Earthdata](https://urs.earthdata.nasa.gov/) y guarda los cuatro GeoTIFF de OPERA, con sus nombres originales, en una carpeta. Los enlaces oficiales y huellas están en [fuentes.json](fuentes.json).
   - 2025-12-06 · [máscara RTC-S1](https://cumulus.asf.earthdatacloud.nasa.gov/OPERA/OPERA_L2_RTC-S1/OPERA_L2_RTC-S1_T083-177307-IW1_20251206T095035Z_20251206T125450Z_S1C_30_v1.0/OPERA_L2_RTC-S1_T083-177307-IW1_20251206T095035Z_20251206T125450Z_S1C_30_v1.0_mask.tif) y [capa WTR](https://archive.podaac.earthdata.nasa.gov/podaac-ops-cumulus-protected/OPERA_L3_DSWX-S1_V1/OPERA_L3_DSWx-S1_T20KMG_20251206T095036Z_20260413T201253Z_S1C_30_v1.0_B01_WTR.tif)
   - 2025-12-18 · [máscara RTC-S1](https://cumulus.asf.earthdatacloud.nasa.gov/OPERA/OPERA_L2_RTC-S1/OPERA_L2_RTC-S1_T083-177307-IW1_20251218T095035Z_20251218T134238Z_S1C_30_v1.0/OPERA_L2_RTC-S1_T083-177307-IW1_20251218T095035Z_20251218T134238Z_S1C_30_v1.0_mask.tif) y [capa WTR](https://archive.podaac.earthdata.nasa.gov/podaac-ops-cumulus-protected/OPERA_L3_DSWX-S1_V1/OPERA_L3_DSWx-S1_T20KMG_20251218T095044Z_20251218T162455Z_S1C_30_v1.0_B01_WTR.tif)
2. Con Python 3.12 y acceso HTTPS instala las dependencias en un entorno virtual activo. El preparador descarga el ZIP oficial de Copernicus y lee por rango las cuatro bandas públicas de Sentinel-1 RTC de Microsoft Planetary Computer.

```text
python -m pip install -r docs/inundaciones111/santa-rosa/requirements.txt
python docs/inundaciones111/santa-rosa/preparar_insumos.py tmp/agua111-insumos
python docs/inundaciones111/santa-rosa/reproducir.py tmp/agua111-insumos RUTA_A_LOS_CUATRO_GEOTIFF --salida tmp/agua111-revision
```

Sustituye `RUTA_A_LOS_CUATRO_GEOTIFF` por la carpeta de descargas. Usa comillas si el nombre contiene espacios. Los directorios de salida deben ser nuevos. La receta comprueba el tamaño y MD5 publicados por CMR para los cuatro GeoTIFF, las huellas de Copernicus y los conteos de cobertura del recorte de Sentinel-1. Guarda `contraste.json` con procedencia y métricas, y `capa-revision.geojson` en WGS84.

## Revisar la evidencia sin Earthdata

- [Informe de contraste](evidencia/contraste.json). Abre `dswx_en_interseccion_con_rtc.entorno500m` para comprobar cobertura y superficies. Las diferencias fuera de los polígonos de Copernicus se registran en `fueraDeReferenciaEnEntorno`.
- [Capa GeoJSON de revisión](evidencia/capa-revision.geojson). Contiene 77 polígonos en WGS84. El atributo `clase` distingue agua nueva, persistente, retirada y área no comparable.
- [Mapas antes y después](evidencia/comparacion-antes-despues.png) y [mapa de cambios frente a Copernicus](evidencia/comparacion-cambios-copernicus.png). Los dos se generaron con los GeoTIFF originales y el mismo recorte.

Para recalcular desde los píxeles, usa la receta anterior con los cuatro originales de OPERA.

## Resultado que debe obtener el revisor

- 2.490 de 2.974 celdas comparables a 30 m en UTM 20S (83,7256 %). Se conservan 484 celdas como no comparables.
- 49,68 ha pasan a agua abierta o vegetación inundada, 6,30 ha conservan alguna de esas clases y 5,67 ha dejan de estar en ellas.
- Dentro del polígono candidato de Copernicus, 237 de 392 celdas pasan a una clase de agua. Fuera de todos los polígonos de esa referencia en el entorno, 96 celdas comparables pasan a alguna clase de agua (8,64 ha). Revisar el desfase de un día entre COSMO-SkyMed y OPERA y la diferencia de clasificación.
- La capa debe contener 77 polígonos. El script comprueba las superficies en UTM y que cada clase reproduzca su máscara de píxeles al rasterizarla.

Fuentes: [Copernicus EMSR853](https://mapping.emergency.copernicus.eu/activations/EMSR853/), [PO.DAAC OPERA DSWx-S1](https://podaac.jpl.nasa.gov/dataset/OPERA_L3_DSWX-S1_V1), [máscara RTC-S1 ASF](https://hyp3-docs.asf.alaska.edu/hyp3-docs/guides/opera_rtc_product_guide/#validity-mask) y [Sentinel-1 RTC de Microsoft Planetary Computer](https://planetarycomputer.microsoft.com/dataset/sentinel-1-rtc).
