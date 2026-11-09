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
