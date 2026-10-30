# Energía de superficie recuperada · #107

El operativo original usa `DSWRF` bajo el nombre UV y `PEVPR` bajo evaporación. Se recuperaron esos dos campos de GFS con nombres y unidades que corresponden a sus mensajes; no se convirtió radiación solar en índice UV ni energía en milímetros de agua.

## Qué muestra el mapa

- **Radiación solar · promedio:** flujo descendente de onda corta, W/m², promedio de un intervalo explícito. La fecha del cuadro es su final. En la captura del 29/10 a las 17:00 de Bolivia, el promedio corresponde a 14:00-17:00.
- **Evaporación potencial · energía:** PEVPR instantáneo, W/m². Conserva el signo; no es evaporación observada, evaporación acumulada, ni una pérdida de agua calculada. Los píxeles ausentes siguen transparentes.

Ambas aparecen en **Mapa → Ajustes → Capas del ambiente** cuando tienen archivos publicados. Se pueden comparar fechas/variables, reproducir, cambiar opacidad y leer el centro. Las unidades no dependen de la preferencia de temperatura o viento.

## Contrato y comprobación

NCEP local: centro `kwbc`, disciplina 0, tabla principal 2 y local 1. DSWRF es categoría 4/parámetro 192, superficie, W/m², plantilla 8, promedio estadístico 0 de un intervalo. PEVPR es categoría 1/parámetro 200, superficie, instantáneo y plantilla 0. Algunas versiones de ecCodes devuelven `unknown` como nombre/unidad del parámetro local 200: la unidad se resuelve solo para esta identidad exacta y tablas documentadas, nunca por posición del mensaje. Otra tabla, nivel, centro, unidad o proceso se rechaza.

La rejilla conserva los 193 × 281 nodos de GFS a 0,25° del recorte sudamericano. No se remuestrean los valores. Máscara GRIB, NaN y valores fuera de la escala de almacenamiento producen alfa cero; el cero válido se conserva. La escala solar es 0-1600 W/m². PEVPR usa -500 a 1500 W/m² con cero exacto en el byte 64. Los límites son del almacenamiento, no umbrales de riesgo. Error máximo de cuantización: 3,1373 y 3,9268 W/m², respectivamente.

La captura real del ciclo **29/10/2026 18 UTC**, plazos 003/006, hizo dos peticiones de **323 762 bytes en total** y produjo cuatro PNG. Auditoría de cada píxel válido contra GRIB: errores dentro de esos límites; faltantes y signos conservados. Los GRIB quedan privados en `tmp/energia107/captura-tarde`; recibos, recetas, productos, huellas, auditoría y capturas en [evidencias/energia107](evidencias/energia107/README.md).

Se publicaron esas cuatro imágenes sobre las 33 existentes, sin sustituirlas. Después se probó la actualización real del nuevo ciclo **30/10 00 UTC**, con otras cuatro imágenes de energía. El horario existente también incorporó sus dieciséis imágenes ambientales: **57 cuadros activos** en el corte final y las 33 huellas iniciales conservadas. Repetir la actualización de energía devolvió `al_dia` sin descargar otra vez. La tarea Windows existente terminó con resultado 0 el 30/10 a las 02:33 de Bolivia.

## Actualización

```powershell
./herramientas/php.ps1 web/artisan capas:actualizar --consultar-noaa --energia-superficie
```

Usa el mismo Python configurado, un estado privado separado con sufijo `-energia` y un perfil de publicación de cuatro cuadros: dos variables, plazos 3 y 6 del mismo ciclo. Exige W/m² y períodos correctos; un lote incompleto o incorrecto no sustituye el catálogo. Se conservan el bloqueo, el archivo de fechas y los límites de descarga. El script de la tarea Windows ejecuta primero las ocho capas previas y después energía, respetando diez segundos entre peticiones. No crea otra tarea, no despierta el equipo y sigue apagado por defecto en instalaciones nuevas. La descarga no ocurre al visitar una página.

Para adquirir un ciclo explícito y producir los archivos sin publicarlos:

```powershell
python herramientas/datos/raster/adquirir_gfs.py --ciclo 2026102918 --plazo 3 --energia-superficie --destino tmp/energia.grib2 --recibo tmp/energia-recibo.json --consultar-noaa
python herramientas/datos/raster/campos_gfs.py --entrada tmp/energia.grib2 --salida tmp/energia-lote --capas radiacion_solar evaporacion_potencial --promedio-desde 0
```

Usar el entorno raster ya preparado y destinos nuevos. No se siguen redirecciones ni se reintenta una descarga fallida. Las ocho capas originales siguen siendo el perfil predeterminado.

## Validación y pendientes

- **503 pruebas PHP / 6069 comprobaciones**, **121 JavaScript**, **34 del productor/orquestación**, Pint y revisión de diferencias correctos.
- Se comprueban los perfiles independientes, su coexistencia, repetición, unidades, períodos, calma/cero, máscaras, signo, promedio frente a instante y conservación del catálogo ante errores. La comparación visual usa datos reales y la cuenta demo sin elevar su rol.
- La recuperación de estos dos campos operativos está preparada para revisar. **No completa** índice UV raster, evaporación histórica ERA5, AQI raster continental, sensores/historial privados ni conexiones externas. No se publicaron las texturas antiguas sin fecha/procedencia como datos nuevos.
- El resto del candidato y su aceptación siguen sujetos a revisión de Jaziel y del docente. No se modificó Flutter ni main.

Fuentes primarias: [inventario GFS de NCEP](https://www.nco.ncep.noaa.gov/pmb/products/gfs/gfs.t00z.pgrb2.0p25.f003.shtml), [tabla de humedad y PEVPR](https://www.nco.ncep.noaa.gov/pmb/docs/grib2/grib2_doc/grib2_table4-2-0-1.shtml), [tabla de onda corta y DSWRF](https://www.nco.ncep.noaa.gov/pmb/docs/grib2/grib2_doc/grib2_table4-2-0-4.shtml). La tabla de NCEP advierte sobre las unidades históricas de PEVPR; esta implementación preserva la magnitud publicada en W/m² sin atribuirle una tasa de agua.
