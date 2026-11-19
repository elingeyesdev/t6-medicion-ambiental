# Resumen anual forestal por período

Avance de #147 desde la captura revisada en #116. El cálculo funciona sin Internet y vuelve a verificar los cuatro originales y sus huellas antes de seleccionar los años. Todavía faltan el criterio aprobado, el piloto de #117 y la capa espacial para completar el producto forestal. No se añade una pantalla ni una ruta pública.

## Repetir el cálculo

Desde la raíz del repositorio, con Python 3.12 o posterior:

```powershell
python herramientas/forestal/ventana.py --directorio docs/forestal116/evidencias/contraste-20261103/acceso --desde 2024 --hasta 2025 --salida tmp/forestal-2024-2025.json
python -m unittest discover -s herramientas/forestal -p 'test_*.py' -v
```

La carpeta de salida debe existir y el archivo debe ser nuevo. Sin --salida, el resultado se imprime en pantalla. No se sobrescriben archivos ni se consultan proveedores. La [salida guardada](evidencias/2024-2025.json) permite repetir este caso desde los mismos originales del 03/11; no es una consulta nueva al proveedor del 08/11.

## Qué conserva y qué calcula

Los años inicial y final están incluidos. Solo se admiten años publicados por la versión de la captura; la disponibilidad completa de fuente.anios se conserva aunque se solicite un intervalo menor. Cambiar el intervalo recalcula el subtotal y el total desde las filas elegidas, sin reutilizar el total completo ni convertir las hectáreas a kilómetros cuadrados.

El cálculo usa decimales exactos y conserva las cadenas de cada año. Cero continúa siendo un dato válido. Un año ausente o nulo queda como no_informado. Si falta un año del intervalo, total_periodo_ha queda nulo y solo se presenta el subtotal conocido. Si ninguno tiene datos, también el subtotal queda nulo.

Se conservan la identidad GADM del proveedor, versión, criterio candidato, atribución y límites. La cobertura espacial continúa desconocida y la capa continúa nula. El resumen no se convierte en un resultado municipal, no acredita cobertura completa ni identifica la causa de la pérdida. El hash de los recibos y las huellas de los cuatro archivos quedan en reproduccion.

Esta rama depende del PR #119 y prepara el cálculo temporal de #147. No da por terminados #117, #147 o #148 ni modifica Flutter.

## Verificación

Pasaron las 32 pruebas de fuente y ventana temporal: 13 regresiones existentes y 19 del cálculo nuevo. Se comprobaron cero, faltantes, años no publicados, captura alterada, cambio de recibos durante la lectura, suma con más de 28 cifras, repetición de la evidencia y protección de archivos existentes. [Salida local](evidencias/pruebas.txt). El resultado de GitHub se comprueba por separado en el PR.

## Recorte raster oficial pequeño

Se comprobó una segunda vía para #147 sin credenciales: leer solo rangos del GeoTIFF oficial. La [captura del 08/11](evidencias/recorte-20261108/README.md) conserva las respuestas y permite repetir el cálculo sin Internet. Es un recorte técnico de 64 por 64 píxeles; no reemplaza el piloto municipal de #117 ni el total GADM del ejemplo temporal.

Instalar las dos dependencias en un entorno virtual y ejecutar desde la raíz:

~~~powershell
python -m pip install -r herramientas/forestal/requirements-raster.txt
python herramientas/forestal/recorte_raster.py --columna 25200 --fila 28000 --ancho 64 --alto 64 --umbral 30 --desde 2024 --hasta 2025 --captura docs/forestal147/evidencias/recorte-20261108/captura --salida tmp/recorte-repetido --reproducir
python -m unittest discover -s herramientas/forestal -p 'test_*.py' -v
~~~

La carpeta de salida debe ser nueva. Para una captura nueva, quitar --reproducir y elegir otra carpeta de captura también nueva. Las direcciones de descarga están fijadas a las tres bandas de GFC-2025-v1.13, tesela 10S_070W. No se admiten archivos locales arbitrarios, otras teselas ni una descarga completa. Los límites actuales son 128 por 128 píxeles, 24 peticiones, 3 MB por rango y 8 MB en total. Si cambia el formato, la cuadrícula, la versión durante la lectura o el servidor ignora Range, el proceso falla.

El resultado entrega resumen anual, píxeles originales en PNG y polígonos individuales de las celdas con pérdida en los años elegidos. PNG conserva los valores, no es una imagen satelital de color natural. La geometría es WGS84 y el área se calcula por fila sobre el elipsoide, sin asumir 900 m² por píxel.

La capa continúa fuera de la API y sin pantalla pública. Antes de usarla faltan método acordado, recorte al territorio aprobado, contraste independiente y tratamiento de incertidumbre. No se deben equiparar los valores de esta ventana con los del departamento GADM o con el área actual de bosque.

Ahora pasan 51 pruebas: las 32 anteriores y 19 del raster. La automatización instala las mismas versiones de Pillow y pyproj y repite las capturas en Linux y Windows, sin consultar al proveedor.

## Contraste local de integridad

El verificador contrastar_recorte.py vuelve a leer los rangos capturados y comprueba los tres PNG contra sus píxeles originales. Recalcula el área de cada celda en sus coordenadas reales, sin usar el cálculo de resúmenes del generador. Comprueba años, conteos, total, extensión y cada polígono; detecta también duplicados o celdas ausentes. No es una validación de campo ni adopta el método.

~~~powershell
python herramientas/forestal/contrastar_recorte.py --directorio docs/forestal147/evidencias/recorte-20261108 --salida tmp/contraste-forestal.json
~~~

La salida debe ser nueva. Las 57 pruebas actuales incluyen seis casos de contraste, con alteraciones de coordenadas, área, año, resumen, imágenes y archivos fuente. El informe deja metodo_validado: false. [Resultado del contraste](evidencias/recorte-20261108/contraste-local.json).

## Paquete para revisión privada

La [receta del paquete completo](evidencias/paquete-20261109/README.md) prepara los años2001–2025 desde los mismos originales, sin nuevas descargas. Vincula versión, captura, criterio, zona y período con las huellas de la serie y la capa. El empaquetado no publica ni adopta el resultado. Pasaron 63 pruebas de Python.

## Candidato de Santa Rosa para #147

La ventana de 64 × 64 celdas está íntegramente dentro del municipio Santa Rosa, BO070602 en el catálogo local. El rectángulo WGS84 se conserva en el [recibo reproducible](evidencias/piloto-santa-rosa.json). El alcance propuesto es esa ventana y el período 2001–2025, con la versión GFC-2025-v1.13 y la cohorte de copa de 2000. La fuente y las capas llevan atribución Hansen/UMD/Google/USGS/NASA y licencia CC BY 4.0.

La captura contiene 4.096 celdas de tierra y cero faltantes en la máscara de base. Con el criterio candidato de copa `≥30 %`, 2.838 celdas pasan el filtro y 1.118 registran pérdida en el período. El total de la ventana es 82,34172106 ha. Con `>30 %`, el total sería 82,19441566 ha. Las dos celdas que explican la diferencia se registraron en 2019 y 2020. La cobertura de observaciones anuales sigue sin cuantificar.

Para reproducir el contraste, prepara el directorio `tmp/forestal-completo` con los pasos de [Paquete completo](evidencias/paquete-20261109/README.md). Instala también Shapely 2.1.2 en el mismo entorno.

~~~powershell
python -m pip install shapely==2.1.2
python herramientas/forestal/verificar_piloto_santa_rosa.py tmp/forestal-completo
~~~

El verificador coteja los PNG y la capa con los rangos originales, valida el total y las celdas únicas, y comprueba la contención frente al límite municipal versionado. La repetición con los insumos de Git reprodujo el mismo recibo y la identidad del paquete `78a62654465d330e4aa0f241d74e237bfa65de709dee827099dc8594d4e88730`. Pasaron 63 pruebas forestales.

Para el contraste independiente de #117 se revisó la [Colección 4 de MapBiomas Bolivia](https://bolivia.mapbiomas.org/downloads/mapas-para-descargar-geotiff/). Los enlaces oficiales de 2023 y 2025 devolvieron 403 al pedir un rango de bytes. La clasificación de uso y cobertura terrestre requiere su propia interpretación y no se suma a la serie GFC. Quedan por acordar el umbral y el perímetro de publicación, y por completar el contraste independiente. Hasta entonces se usa el visor privado de revisión.

### Contraste con Sentinel-2 de las dos celdas sensibles al umbral

Se consultaron 30 escenas ópticas Sentinel-2 L2A en julio y agosto de 2018–2021, todas de la tesela 20KMG, órbita relativa 53 y versiones de procesamiento 02.12 o 03.00. Se leyeron las bandas de 17 escenas. El [recibo por escena](evidencias/contraste-sentinel2-santa-rosa.json) incluye fechas, versiones, clases SCL y valores disponibles. Las restantes no se incluyeron en las medianas por errores de lectura.

Se estimó el NDVI como (B08 − B04) / (B08 + B04) en nueve puntos de una malla de 10 m centrada en cada celda. Se conservaron muestras con valores positivos y SCL 4 o 5, y se exigieron al menos siete puntos por escena. El resumen anual usa la mediana de las escenas válidas para cada celda. La malla alrededor del centro es una aproximación del entorno, con distinto tamaño y alineación respecto a Hansen.

- Celda 421, pérdida GFC registrada en 2019. El NDVI mediano pasó de 0,7749 en 2019 (dos escenas) a 0,6112 en 2020 (tres) y 0,4957 en 2021 (cinco).
- Celda 1875, pérdida GFC registrada en 2020. El NDVI mediano pasó de 0,4893 en 2019 (dos escenas) a 0,5653 en 2020 (dos) y 0,6069 en 2021 (cinco).

En la primera celda se observa una disminución posterior en la señal de vegetación. En la segunda, el índice aumentó. El NDVI también responde a cultivos, rebrote y variación estacional. Estas dos muestras orientan la revisión de campo y no validan el año de pérdida, el umbral de copa ni la superficie del piloto. Fuente óptica [Copernicus Sentinel-2 MSI Level-2A](https://sentinels.copernicus.eu/sentinel-data-access/sentinel-products/sentinel-2-data-products/collection-0-level-2a), catálogo [Microsoft Planetary Computer](https://planetarycomputer.microsoft.com/dataset/sentinel-2-l2a).
