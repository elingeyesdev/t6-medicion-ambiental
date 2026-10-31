# GeoSur - candidato M3 actualizado al 30/10/2026

Josué Padilla prepara la web y revisa el móvil. Jaziel Díaz desarrolla Flutter y revisa la web. EnviroSense es el sistema principal; GeoSur adapta sus funciones a Laravel/Blade, API compartida y Flutter. El chatbot quedó fuera por decisión de Josué. El pedido actual es preparar hoy M3 y recuperar la web, sin modificar móvil.

**Estado:** candidato integrado y probado en local. Requiere revisión de Jaziel, resolver los tres casos de interfaz Flutter pendientes en #88 (el fallo Android anterior ya se corrigió) y aceptación de la entrega. No se declara toda la migración operativa ni se promueve M3 a main. Allí continúa M2, `v0.2.0`, commit `851a79f5c699249c284001114f76f994c9ce4335`.

## Continuación del 31/10: faltantes en reportes

La corrección #64 agrega al PDF el detalle de fechas, variables, unidades y origen de los valores no disponibles que ya figuraban en pantalla y Excel. [Reproducción y pruebas](../REVISION_REPORTES64.md). Esta continuación sobre #108 queda en revisión de Jaziel. La suite web completa pasó **505 pruebas / 6103 comprobaciones**; las tres pruebas JavaScript de reportes también pasaron. Son verificaciones controladas, sin consultar proveedores.

El PDF de presentación enlazado más abajo corresponde al corte anterior; se regenerará al fijar el candidato revisado. La corrección del PDF de reportes no cierra #39, #41 ni toda la recuperación. Main sigue en M2 y no se promueve este candidato sin aprobación del ingeniero.

## Paquete y recorrido

- [Radiación y evaporación potencial](../ENERGIA_ENVIROSENSE.md): #107 recupera DSWRF promedio y PEVPR instantáneo del operativo original en W/m². Dos capas nuevas, captura real, perfiles de actualización separados y conservación del archivo. No equivalen a índice UV ni evaporación acumulada en mm.

- [Viento animado del mapa](../VIENTO_ENVIROSENSE.md): continuación #105 sobre #104. Trazos según la capa de viento, control de activación, comparación A/B y pausa. No representa trayectorias de humo ni modifica el tiempo de la capa.

- [Reportes atmosféricos comprobados](../REPORTES_ATMOSFERICOS_VERIFICADOS.md): continuación #103 sobre #102. Dos escalas de aire identificadas, verificación de lugar/zona/unidades, captura externa real y PDF/Excel contrastados en una base temporal.

- [Calidad del aire del lugar](../CALIDAD_AIRE_ENVIROSENSE.md): continuación #101 sobre #100. Consulta actual y 24 horas del índice europeo original, con CAMS global/Open-Meteo, partículas y procedencia. No se mezcla con AQI de otra escala ni completa la capa raster pendiente.

- [Actualización automática local de capas](../ACTUALIZACION_CAPAS.md): continuación #99 sobre el frente recuperado, con una nueva captura real, conservación del archivo y horario Windows comprobado. El candidato original conserva sus pruebas como registro; la corrección visual y esta actualización se revisan en sus propios PR.

- [Corrección del frente de EnviroSense](../evidencias/frente47/README.md): recorrido del original, portada/acceso, mapa y pantallas internas, con capturas comparables y pruebas. Propuesta sobre este candidato, pendiente de revisión.

- [PDF para presentar](../../output/pdf/GeoSur-M3.pdf), diez páginas revisadas visualmente, con capturas, recorrido y límites vigentes.
- [Base y alcance de EnviroSense](../ENVIROSENSE_COMO_BASE.md). El catálogo principal comprende doce países sudamericanos y Guyana Francesa como territorio. Focos y sequías mantienen cobertura probada de Santa Cruz.
- [Comparación de sequías](../qa39/README.md): respuesta, lluvia de las treinta ventanas, contraste SciPy, capturas web y evidencia móvil de Jaziel. PR #88, tarea #39.
- [Avance parcial de agua/inundaciones](../ANA01_INUNDACIONES.md): propuesta, catálogo y adquisición reproducible. PR #46, tarea #40. No hay resultado de inundación ni análisis de píxeles.
- [Cargas e historial](../ENTREGA_DATOS66.md): permisos, CSV/JSON saneado, worker, MQTT y pruebas. PR #91.
- [Capas del mapa](../CAPAS_MAPA_62.md): codificaciones, fechas, reproducción y comparación. PR #93. [Productores GFS/ERA5](../RASTER_ETL66.md): #95 depende de #93 y #97 amplía #95 a ocho capas ambientales.
- [Simulaciones y reportes](../INTEGRACION_M3_SIMULACIONES_REPORTES.md): estado compartido, cortes, gráficos, PDF/Excel y faltantes. PR #92. El candidato M3 añade la capa de simulación continua al mapa; no crea lecturas al dibujarla.
- [Avisos y cuentas](../M3_AVISOS_CUENTAS_WEB.md): bandeja, consentimiento, cola y estados de canales externos. PR #94.
- [Guía del curso](../GUIA_CURSO_ESTADO.md): 61 páginas contrastadas, formularios de tareas/errores y pendientes de permisos. PR #90.

El arranque existente se conserva. Servidor del checkout activo: `http://127.0.0.1:5791`; no iniciar otro servidor sobre el mismo puerto. La cuenta demo mantiene Visualizador y sus credenciales ya entregadas, sin incluirlas en este paquete. Tabler 1.7.1 sigue como base de componentes internos, y portada/acceso recuperan el diseño de EnviroSense. El código del proveedor permanece separado de los componentes propios.

## Integración local realizada

Se reunieron los candidatos de las tareas en `feature/41-evidencias-entrega-m3`, sin fusionar los PR propios sin revisión. Se conectaron rutas de capas, simulación, avisos y cuenta; se conservaron los middleware y se resolvieron los conflictos de rutas/bootstrap manteniendo ambos cambios.

Se guardó un respaldo SQLite consistente antes de aplicar las migraciones `003000`, `004001` y `004002`. No se ejecutó `migrate:fresh` ni se cambió el rol o contraseña de la demo. La base, el respaldo, el entorno y los logs permanecen fuera de Git. Las sesiones y los originales privados de EnviroSense no se importan.

Se publicaron inicialmente 17 PNG GFS reales en almacenamiento privado: temperatura de las 06 UTC y ocho capas de las 09 y 12 UTC del 29/10. Incluyen temperatura, humedad, viento, presión, visibilidad, nieve equivalente de agua, precipitación acumulada e intensidad de precipitación. Se comprobaron sus huellas y el navegador los recibe por rutas autenticadas. El mapa distingue registros, estimaciones y simulación continua. La continuación #99 añade actualización automática local, con 16 imágenes nuevas del ciclo 18 UTC sin eliminar las anteriores; la ejecución periódica necesita el equipo disponible y su revisión propia.

## Validación

**Corte actualizado del 30/10 (#107):** 503 pruebas PHP / 6069 comprobaciones, 121 JavaScript y 34 del productor/orquestación, Pint correcto. Captura real y lectura de las dos capas de energía; publicación de cuatro imágenes de tarde y otras cuatro del nuevo ciclo. El horario incorporó también el ciclo ambiental nuevo: 57 cuadros activos, con las 33 imágenes anteriores conservadas. Estado y evidencia en la guía de energía. Los resultados siguientes conservan el registro de la integración base, sin sustituirlos por el resultado actual.

- Integración PHP: **477 pruebas, 5717 comprobaciones correctas**. Vistas compiladas en un directorio de pruebas separado. Bases y transportes controlados.
- JavaScript integrado: **101 pruebas correctas**, incluidos filtro territorial, capa continua y calma exacta del viento.
- Productor raster: **22 pruebas correctas**, incluyendo GRIB/NetCDF controlados, máscaras, unidades, geografía, intervalos de precipitación, duplicados, fallos HTTP y publicación PHP real de ocho capas. El contrato anterior de 11 pruebas pasó CI Linux; la ampliación se revisa en #97.
- Normalizador: **5 pruebas Python**. SQLite concurrente: procesos independientes, con lotes repetidos y conflictos, modos DELETE y WAL.
- PostgreSQL **16.11**: **476 pruebas, 5707 comprobaciones correctas**, incluyendo cuatro importaciones simultáneas y rollback del lote en conflicto. [Entorno aislado y reproducción](../POSTGRESQL_LOCAL_M3.md). El clúster quedó detenido y la demo sigue en SQLite.
- Pint completo correcto. Se corrigió el formato de las rutas añadidas en los PR #92/#93 y de la salida de sesión en #94; sus verificaciones vuelven a ejecutarse después de cada push.
- Sequías real: 114,5 mm; SPI Laravel `-0.6413769540120462`; SciPy `-0.6413769540120433`; diferencia `2.886579864025407e-15`. Cobertura parcial: 1 de 56 municipios. Una celda no acredita la condición de todo el municipio.
- Navegador: demo, mapa continental, comparación de temperaturas, viento y precipitación reales y consulta de sequías con sesión. [Ocho capas disponibles y comparación](evidencias/mapa-ocho-capas-noaa.png), [temperatura](evidencias/mapa-temperatura-noaa.png) y [detalle de sequías](../qa39/evidencias/web-sequias-detalle-2024-09.png). En el centro de la captura nueva se leen 8 km/h y 0 mm, con fechas y período del acumulado visibles.

Comandos desde la raíz, con PHP/Node/Python preparados:

```powershell
$env:VIEW_COMPILED_PATH = Join-Path (Get-Location) 'web/storage/framework/views-pruebas'
./herramientas/php.ps1 web/artisan test --compact
node --test web/tests/Js/*.test.mjs web/tests/JavaScript/*.test.cjs
python herramientas/datos/test_normalizar_historial.py
python herramientas/entregas/generar-m3.py
```

El productor raster utiliza su entorno aislado; ver su guía. Las pruebas no acreditan conexiones externas que se sustituyeron por transportes controlados.

## Lo que sigue abierto

1. Jaziel: corregir el conflicto de manifiestos Android en #88 y repetir CI; revisar el candidato web y los PR #90-95 y #97. El fallo se dejó comentado con el ajuste concreto. #39 no se cierra con Android fallido.
2. Flutter: trabajo ampliado de EnviroSense en #89, con Jaziel. No se desarrolló móvil después de la instrucción de reservarle ese trabajo. APK release e instalación siguen pendientes.
3. Datos: dispositivos/broker reales, importación de un historial saneado real, capa raster AQI, índice UV raster y evaporación histórica ERA5. #107 recupera las dos magnitudes del operativo original como radiación solar y energía potencial, sin confundirlas con esos productos pendientes. La consulta puntual del índice europeo y sus 24 horas está en #101. Las ocho capas GFS previas, las dos de energía y PostgreSQL local con concurrencia están comprobados; esto no acredita un histórico completo ni un despliegue.
4. Servicios externos: correo, Telegram, WhatsApp y push configurables, apagados por defecto; faltan credenciales y pruebas autorizadas. La consulta atmosférica de Reportes se comprobó con el proveedor real y se habilitó en este equipo en #103; una instalación nueva la conserva apagada. No se envían mensajes desde una prueba ni se usa el WhatsApp personal como integración.
5. Inundaciones: revisar método, objetivo/evento y referencia de escenas antes de procesar; la adquisición parcial está documentada en #40/#46.
6. Gestión: estimar el alcance ampliado y recalcular ProjectLibre conservando la línea base; propietario configura protecciones, rama predeterminada y borrado global. Railway/publicación siguen pospuestos por Josué.
7. Aceptación: revisión cruzada y aprobación académica M3 antes de main/tag de entrega. Haber aprobado M2 no aprueba M3.

Las tareas #38, #48, #60, #81 y #85 se cerraron por los cambios aceptados; los pendientes ampliados no desaparecen por esos cierres. Se conserva el historial de planificación anterior y esta actualización lo sustituye como estado actual.
