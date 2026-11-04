# Carga raster recuperada de EnviroSense (#66 / #62)

**Ampliación del 30/10 en #107:** [energía de superficie](ENERGIA_ENVIROSENSE.md). Se identificaron los dos campos del operativo original y se recuperan como radiación solar promedio y energía de evaporación potencial en W/m². Perfil separado, máscara, período y captura real; no se publican como índice UV ni mm de agua. El perfil predeterminado de ocho capas se conserva. Las afirmaciones de pendientes al final de esta guía describen el corte inicial y deben contrastarse con esta ampliación.

**Actualización posterior en #99:** coordinación automática local, conservación de cortes y aviso de fechas nuevas en la web. Se añadieron 16 imágenes reales a las 17 existentes. Ver [ACTUALIZACION_CAPAS.md](ACTUALIZACION_CAPAS.md); lo siguiente describe la preparación inicial del productor.

29/10/2026. El productor convierte temperatura GFS/ERA5 local y ocho campos GFS explícitos en PNG de datos y recetas compatibles con el publicador Laravel de #62. No ejecuta los scripts originales, no genera datos aleatorios y no transforma un pronóstico en una observación.

## Ampliación de los campos GFS

Se capturaron dos conjuntos públicos del ciclo 29/10/2026 06 UTC, plazos 003 y 006: 987 693 y 1 001 251 bytes, doce mensajes por conjunto. Cada uno produjo temperatura a 2 m (°C), humedad relativa a 2 m (%), viento a 10 m (km/h), presión al nivel del mar (hPa), visibilidad (km), nieve en equivalente de agua (mm), precipitación acumulada (mm) e intensidad instantánea de precipitación (mm/h). Son estimaciones del modelo; precipitación incluye fase líquida y sólida. Los dieciséis PNG, recetas, metadatos y recibos están en `evidencias/raster66/campos/`.

```powershell
tmp/raster-venv/Scripts/python.exe herramientas/datos/raster/adquirir_gfs.py --ciclo 2026102906 --plazo 3 --campos-ambientales --destino tmp/captura-noaa/campos.grib2 --recibo tmp/captura-noaa/campos-recibo.json --consultar-noaa
tmp/raster-venv/Scripts/python.exe herramientas/datos/raster/campos_gfs.py --entrada tmp/captura-noaa/campos.grib2 --salida tmp/captura-noaa/campos-lote --acumulado-desde 0
./herramientas/php.ps1 docs/integracion62/publicar-capas.php tmp/captura-noaa/campos-lote tmp/captura-noaa/campos-lote/receta.json
```

El acumulado se pide desde una hora explícita del ciclo. Si NOAA entrega intervalos diferentes, el productor exige elegir uno; no mezcla 0–9 h con 6–9 h. Duplicados idénticos se registran y se deduplican; valores distintos del mismo intervalo abortan la publicación. Los otros campos pueden procesarse sin elegir un acumulado no solicitado. PRATE instantáneo se multiplica por 3600; su promedio no lo sustituye. APCP y WEASD conservan equivalente de agua: no se confunden con lluvia líquida exclusiva ni profundidad de nieve.

Se aceptan procesos deterministas NCEP 96 y análisis 81 únicamente en plazo cero. U y V deben coincidir en rejilla y fecha. La nueva codificación `viento-uv127` conserva calma exacta con byte 127, sin cambiar la lectura de archivos antiguos. El error máximo de magnitud es 2,005 km/h. La humedad acepta una tolerancia de 0,00001 puntos porcentuales en los extremos por empaquetado GRIB; un valor realmente fuera de escala sigue siendo ausencia. Cada PNG informa su error y cantidad de faltantes. No se rellenan huecos.

Hay 22 pruebas offline del productor y adquisición (11 de temperatura/NetCDF y 11 adicionales), más el contrato JavaScript del viento. Se comprobaron máscaras, conversiones, componentes, distintos acumulados, duplicados, proceso de análisis y la publicación de las ocho capas usando las clases PHP reales. Las pruebas controladas no se publican en el mapa del usuario.

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

Las pruebas verifican lectura GRIB/NetCDF controlada, fechas exactas, nivel/unidades, ejes, máscara, cero válido, error de cuantización, repetición, no sobrescritura, entradas dañadas, contrato con el publicador PHP real y límites/errores HTTP. Los fixtures están rotulados como controlados y requieren `--datos-controlados`; nunca se publicaron en el servidor del usuario.

```powershell
# PHP debe tener mbstring, como el entorno Laravel.
$env:RASTER_PHP = 'C:/ruta/php.exe'
# Si PHP es portable, usar el php.ini del wrapper del proyecto mediante PHPRC.
tmp/raster-venv/Scripts/python.exe -m unittest discover -s herramientas/datos/raster/tests -p 'test_*.py'
```

Esta entrega recupera ocho capas GFS y la lectura de temperatura ERA5 local. No acredita un productor de AQI ni todas las fechas históricas. La actualización automática local se añadió posteriormente en #99. Las salidas ambiguas de UV/evaporación del original no se renombran como productos científicos distintos. Quedan registradas como pendientes de #66. Sensores y broker reales mantienen su validación independiente; un catálogo de imágenes no prueba su conexión.

Fuentes primarias consultadas: [filtro NOMADS](https://nomads.ncep.noaa.gov/cgi-bin/filter_gfs_0p25.pl), [inventario GFS de NCEP](https://www.nco.ncep.noaa.gov/pmb/products/gfs/) y [ERA5 C3S](https://cds.climate.copernicus.eu/datasets/reanalysis-era5-single-levels). Se conserva atribución NOAA/NWS/NCEP y Copernicus/ECMWF en cada producto correspondiente.
