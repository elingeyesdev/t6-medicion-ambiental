# Carga raster recuperada de EnviroSense (#66 / #62)

29/10/2026. El productor convierte una temperatura GFS GRIB2 o un archivo ERA5 NetCDF local en un PNG de datos y una receta compatible con el publicador Laravel de #62. No ejecuta los scripts originales, no genera datos aleatorios y no transforma un pronóstico en una observación.

## Lo comprobado con datos reales

Se hicieron dos peticiones pequeñas al filtro público NOMADS de NOAA: ciclo GFS del 29/10/2026, 06 UTC, plazos 000 y 003. Cada respuesta tiene 88 308 bytes, temperatura instantánea a dos metros y recorte [-82, -57, -34, 13] en longitud/latitud. No se descargó un producto global completo. La salida tiene 193 × 281 nodos (54 233 valores), paso de 0,25°, filas norte-sur y escala de -50 a 50 °C con error máximo de cuantización de 0,1961 °C. Las fechas válidas son 06 y 09 UTC. El primer cuadro corresponde al análisis del modelo; el segundo, a su pronóstico de tres horas. Ambos son datos modelados.

Los recibos de adquisición, las huellas y los PNG derivados quedan en `docs/evidencias/raster66/`. Los GRIB originales y el entorno Python permanecen en la carpeta privada de trabajo, fuera de Git. Las respuestas se comprobaron como mensajes GRIB2 completos; su huella identifica la captura y no certifica por sí sola al proveedor. Los endpoints originales pueden dejar de conservar ese ciclo con el paso de los días.

## Preparar y ejecutar

Usar Python 3.12 en un entorno propio, sin modificar el Python del equipo:

```powershell
python -m venv tmp/raster-venv
tmp/raster-venv/Scripts/python.exe -m pip install -r herramientas/datos/raster/requirements.txt
New-Item -ItemType Directory -Force tmp/captura-noaa
tmp/raster-venv/Scripts/python.exe herramientas/datos/raster/adquirir_gfs.py --ciclo 2026102906 --plazo 0 --destino tmp/captura-noaa/temperatura.grib2 --recibo tmp/captura-noaa/recibo.json --consultar-noaa
tmp/raster-venv/Scripts/python.exe herramientas/datos/raster/producir.py gfs --entrada tmp/captura-noaa/temperatura.grib2 --salida tmp/captura-noaa/lote
./herramientas/php.ps1 docs/integracion62/publicar-capas.php tmp/captura-noaa/lote tmp/captura-noaa/lote/receta.json
```

El ciclo es explícito: sustituirlo por uno disponible al repetir una captura nueva. Cada comando rechaza salidas existentes. La adquisición admite una petición HTTPS, 30 segundos y 2 MB como máximo, sin reintentos ni redirecciones. HTML, errores y archivos truncados conservan diagnóstico y no se convierten en datos.

El productor local exige archivo regular de hasta 128 MiB. Verifica variable `t2m`, Kelvin, altura a dos metros, instante, coordenadas y rejilla. Para GFS comprueba centro NCEP, GRIB2, ciclo y plazo coherentes y resolución de 0,25°. No selecciona otro nivel, fecha, miembro de ensemble o variable en silencio. Reordena la rejilla y recorta Sudamérica sin interpolar los valores. Convierte K a °C; fuera de escala, NaN y faltantes quedan con alfa cero y lectura null. El cero válido se conserva. Publica el lote por renombrado, sin sobrescribir el destino, con hashes de entrada y salida.

Para ERA5 se requiere un NetCDF obtenido y revisado por el operador; no se creó una cuenta CDS ni se descargó ERA5:

```powershell
tmp/raster-venv/Scripts/python.exe herramientas/datos/raster/producir.py era5 --entrada C:/ruta/privada/temperatura.nc --instante 2024-09-01T12:00:00Z --salida tmp/captura-noaa/era5
```

La receta debe publicarse en el directorio privado configurado en `MAPAS_RASTER_DIRECTORIO`. Al reunir varias fechas, copiar los PNG sin cambiar nombres y enumerar todos sus cuadros en una receta; no publicar mientras se escribe un archivo. El publicador calcula integridad y conserva el catálogo anterior si falla. La web no ejecuta adquisiciones al abrir una pantalla.

## Pruebas y límites

Once pruebas verifican lectura GRIB/NetCDF controlada, fechas exactas, nivel/unidades, ejes, máscara, cero válido, error de cuantización, repetición, no sobrescritura, entradas dañadas, contrato con el publicador PHP real y límites/errores HTTP. Los fixtures están rotulados como controlados y requieren `--datos-controlados`; nunca se publicaron en el servidor del usuario.

```powershell
# PHP debe tener mbstring, como el entorno Laravel.
$env:RASTER_PHP = 'C:/ruta/php.exe'
# Si PHP es portable, usar el php.ini del wrapper del proyecto mediante PHPRC.
tmp/raster-venv/Scripts/python.exe -m unittest discover -s herramientas/datos/raster/tests -p 'test_*.py'
```

Esta entrega recupera el productor de temperatura y la lectura local ERA5. No acredita productores operativos de viento, lluvia, humedad, presión, nieve, visibilidad o AQI, ni todas las fechas históricas. Sus codificaciones están en el visor, pero necesitan insumos verificados. Las salidas ambiguas de UV/evaporación del original no se renombran como productos científicos distintos. Quedan registradas como pendientes de #66; no se declara recuperación ambiental completa por mostrar dos cuadros de temperatura.

Fuentes primarias consultadas: [filtro NOMADS](https://nomads.ncep.noaa.gov/cgi-bin/filter_gfs_0p25.pl), [inventario GFS de NCEP](https://www.nco.ncep.noaa.gov/pmb/products/gfs/) y [ERA5 C3S](https://cds.climate.copernicus.eu/datasets/reanalysis-era5-single-levels). Se conserva atribución NOAA/NWS/NCEP y Copernicus/ECMWF en cada producto correspondiente.
