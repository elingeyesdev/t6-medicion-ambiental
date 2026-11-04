# Actualización local de capas · #99

GeoSur incorpora nuevos recortes GFS de temperatura, humedad, viento, presión, visibilidad, nieve equivalente de agua y precipitación acumulada/instantánea. Son estimaciones del modelo, no mediciones de estaciones. Continúa el productor de #66; una visita al mapa nunca inicia la adquisición.

## Funcionamiento

Cada hora se comprueba si hay un ciclo nuevo. Se calcula un candidato UTC con seis horas de margen propio y se solicitan los plazos 003 y 006. Ese margen reduce consultas prematuras; no garantiza la disponibilidad de NOAA. Solo se piden los campos y el recorte sudamericano acordados, con 2 MB y 30 segundos como máximo por petición. Hay diez segundos de espera entre solicitudes, según la [guía de NOMADS](https://nomads.ncep.noaa.gov/info.php?page=gribfilter). [GFS publica ciclos cada seis horas](https://nomads.ncep.noaa.gov/).

Si falla un archivo o no coinciden ciclo, plazo, unidades o intervalo de precipitación, no se publica un lote parcial. El estado privado conserva el último ciclo correcto y el diagnóstico. Se espera al menos una hora antes de repetir un intento fallido. Un ciclo publicado no se descarga otra vez. Después de estar apagado se busca el candidato actual; no se rellenan todos los ciclos omitidos ni se inventan horas.

Dos bloqueos separan adquisición/producción y publicación. También el publicador manual comparte el segundo. El catálogo se reemplaza de forma atómica después de validar el conjunto. Si una imagen anterior cambió, se detiene la actualización; no se recalcula su huella para ocultar la modificación.

## Conservación y comparación

El catálogo activo mantiene hasta 48 fechas automáticas por capa, conservando las cargas manuales anteriores. Los PNG y sus referencias privadas no se borran al salir del catálogo. Las imágenes elegidas en sesiones abiertas siguen sirviéndose por el mismo endpoint autenticado, con sus permisos e integridad comprobados.

La web revisa el catálogo cada minuto mientras la pestaña y el panel de capas están visibles. Ofrece **Hay nuevas fechas · Actualizar fechas** cuando aparecen imágenes nuevas. Aplicarlas conserva variables, fechas A/B, opacidad y comparación. No salta al último cuadro durante una reproducción. Los cuadros archivados se conservan en la selección existente; todavía no hay un explorador de todo el archivo. Un fallo de consulta conserva el mapa actual.

El almacenamiento privado crece con PNG, GRIB y recibos guardados. Este cambio no borra datos ni añade limpieza automática. El catálogo sigue limitado a 512 cuadros; si las cargas manuales impiden respetarlo, se conserva la publicación anterior y se registra el fallo.

## Preparación

Usar el entorno Python y dependencias fijadas de `herramientas/datos/raster/requirements.txt`, según `RASTER_ETL66.md`. Configurar solo en el `.env` propio:

```dotenv
MAPAS_ACTUALIZACION_PYTHON=C:/ruta/al/entorno/Scripts/python.exe
MAPAS_ACTUALIZACION_HABILITADA=true
```

`MAPAS_ACTUALIZACION_TRABAJO` es opcional; por defecto usa `web/storage/app/private/mapas/actualizacion`. Los PNG y el catálogo usan `MAPAS_RASTER_DIRECTORIO`. El ejemplo del repositorio mantiene la ejecución programada apagada. La orden manual requiere habilitar explícitamente la consulta:

```powershell
./herramientas/php.ps1 web/artisan capas:actualizar --consultar-noaa
```

`capas:incorporar LOTE` es una orden local auxiliar. No hay ruta HTTP para iniciar descargas ni introducir archivos.

## Programar y pausar en Windows

Desde la raíz, después de preparar PHP:

```powershell
$phpCapas = (Get-Command php).Source
$iniCapas = (& ./herramientas/php.ps1 -r 'echo php_ini_loaded_file();' | Select-Object -Last 1).Trim()
./herramientas/datos/raster/programar-local.ps1 -Accion instalar -Php $phpCapas -Ini $iniCapas
./herramientas/datos/raster/programar-local.ps1 -Accion estado
./herramientas/datos/raster/programar-local.ps1 -Accion pausar
```

La tarea pertenece al usuario actual, con privilegios limitados y ventana oculta. Se repite cada hora, evita instancias simultáneas y tiene un límite de cinco minutos. No despierta el equipo: necesita sesión abierta y respeta las condiciones de energía de Windows. En Linux, Laravel declara el mismo comando cada hora; el operador debe activar `schedule:run` en su cron.

En este equipo quedó instalada `GeoSur-Capas-BEFF1AE15638`, con ejecución comprobada y resultado 0. Se reutilizó el entorno raster ya preparado de `m3-etl-web`; su ruta está solo en el `.env` local. No borrar ese entorno sin sustituir la configuración. Si se cambia a una rama antigua sin estos comandos, pausar primero la tarea.

## Comprobación

[Captura real, recibos y conservación de A/B](evidencias/actualizacion99/README.md). El primer ciclo coordinado fue `2026102918`, con fechas válidas de 21 y 00 UTC: 17 y 20 horas de Bolivia. Se añadieron 16 imágenes a las 17 existentes. La ejecución posterior mediante Windows no repitió descargas. No se esperó un nuevo ciclo de seis horas para presentarlo como observado.

Se probaron límites/errores HTTP, espera, cambio de día, zona UTC, repetición, reintento espaciado, exclusión entre procesos, lote completo, conflicto, integridad anterior, archivo, rutas, permisos y conservación de A/B. Los datos controlados permanecen en directorios de prueba y nunca se publican en el mapa del usuario.

No se modifica móvil ni se publica M3 en main. AQI, otros productores y conexiones externas conservan sus tareas independientes.
