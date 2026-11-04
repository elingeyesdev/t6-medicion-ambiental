# GeoSur - estado de M3 y espera de aceptación, 03/11/2026

Actualización del traspaso: **03/11/2026, America/La_Paz**. Jaziel, dejo separado lo que ya tiene evidencia, los PR que faltan revisar y lo que todavía impide aceptar M3. Josué prepara la web y revisa el móvil; Jaziel desarrolla Flutter y revisa la web. EnviroSense sigue como sistema principal, adaptado a Laravel/Blade, API compartida y Flutter. El chatbot está fuera del alcance.

**M3 y #41 siguen abiertos**, igual que #40. **#39 ya se cerró** después de revisar e integrar el PR #88 en develop. Hay un candidato local histórico y avances posteriores en PR separados; todavía no hay un corte único revisado y aceptado con todo lo citado aquí. Main remoto conserva M2, `v0.2.0`, commit `851a79f5c699249c284001114f76f994c9ce4335`, verificado directamente contra la referencia remota al preparar este corte. Pasar M3 a main requiere la aprobación del ingeniero; la aprobación de M2 no la sustituye. **No hay fecha académica M3 confirmada en este registro.**

La [captura de metadata](metadata-20261031.json) conserva estado nativo (`state`/`closed_at`), Draft, HEAD, base y commit de cada revisión. La captura inicial consultó solo issues, pulls y reviews mediante GitHub REST core, con `gh api GET`; no se consultaron ejecuciones ni se modificó GitHub. El intervalo UTC del 01/11/2026, 00:18-00:19, corresponde al 31/10 por la noche en Bolivia. El `due_on` histórico del milestone no acredita una fecha académica nueva. **CI: sin verificación en este registro REST** (`ci: no_verificado`). El seguimiento `follow_up_129` conserva aparte el SHA posterior y su QA; `follow_up_20261101` actualiza los PR abiertos y #39/#41 sin reescribir la captura inicial. El seguimiento follow_up_night_20261101 registra #133–#135 y la comprobación final de main/#39/#41. La evidencia de interfaz se conserva en `ui_evidence`, separada de la consulta REST.

## Recorrido web y guía de demostración · 03/11

Completé un ensayo en una copia aislada que reúne los PR publicados, sin integrarlos en remoto. El [guion de 8 a 10 minutos](recorrido-web-20261103/guion.md) y la [guía visual de seis páginas](../../output/pdf/GeoSur-M3-recorrido-web-20261103.pdf) permiten preparar la demostración. Esta guía complementa el PDF histórico; no presenta M3 como aceptado.

- **20 comprobaciones manuales:** acceso y roles, mapa A/B y pantalla completa, simulaciones, mediciones, reportes y sus descargas, sequías, focos, preferencias y avisos. [Registro](recorrido-web-20261103/recorrido-ui.json), [capturas y resultados](recorrido-web-20261103/resultado.json). Plantillas/Alertas se abrieron con sus filtros y estados vacíos; no se crearon plantillas ni se reconocieron alertas manualmente.
- **Copia 92615f6:** 509 casos PHP elegibles / 6273 aserciones, Pint en 255 archivos y 181 JavaScript correctas. Se excluyeron 14 casos de concurrencia/MQTT que necesitan abrir procesos. No se cuentan como aprobados ni se vuelven a atribuir estos resultados a otro commit. Los casos conservados y la selección están adjuntos.
- **Corrección encontrada, #136 / [PR #137](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/137):** el acceso sin permiso conserva el 403, explica el motivo en español y permite volver. Se repitió en navegador y pasaron 4 regresiones / 38 aserciones en la copia 7163a0e. El PR conserva su validación independiente de 4 / 39 y tiene **7/7 comprobaciones GitHub en 98af91f**, vistas en navegador; [captura](recorrido-web-20261103/pr137-comprobaciones.png). La revisión de Jaziel sigue solicitada.
- **Datos reales, con sus límites:** sequías de septiembre de 2024 devuelve 114,5 mm y SPI-3 -0,6414 en una muestra, con cobertura 1/56 y celda en Porongo. La consulta FIRMS de las últimas 24 horas devolvió cero detecciones; no demuestra ausencia de incendios. La ficha del tiempo respondió con su pronóstico. El reporte conserva 50 lecturas simuladas y sus exportaciones PDF/Excel, sin presentarlas como observadas.

La copia local combina #133, #134, #135, #129, #96 y #94; luego aplica la corrección fuente de #136. Los SHA y la composición están en el resultado. El móvil, la cuenta demo y main no se modificaron. Se detuvo el servidor de ensayo; no quedan procesos de esta comprobación. Falta revisar e integrar los PR, fijar el SHA aceptado y repetir el recorrido conjunto con Jaziel. M3 y #41 continúan abiertos.

## Candidato actualizado y cambios aprobados · 03/11

- [PR #88](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/88), HEAD revisado 339d6e6: aprobado e integrado en develop el 02/11, merge 1313e04. Sus tres ajustes móviles y la corrección Android se conservan; #39 está cerrada. [Aprobación](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/88#pullrequestreview-5746453636) y [evidencia anterior](evidencias/pr88-integrado-20261102.png). No se recompiló ni modificó Flutter localmente.
- Jaziel aprobó e integró #98 (frente original), #100 (actualización de capas) y #102 (aire del lugar) en la rama del candidato #96. Verifiqué las aprobaciones sobre sus respectivos HEAD. Las tareas #99 y #101 quedaron cerradas el 03/11; las tres ramas remotas ya están eliminadas. #47 mantiene pendientes las otras partes de la recuperación.
- [PR #96](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/96), nuevo HEAD **b7ed148**: incorporé develop 1313e04 y resolví los conflictos de la guía raster, su fixture PHP y el manejo de errores. Conserva exactamente los árboles de web/herramientas de 7925c3b, incluyendo los tres PR recién aprobados, junto con los cambios de #88. **107 JavaScript correctas** y **13/13 comprobaciones GitHub**, observadas en la interfaz pública para b7ed148. [Detalle y salida de pruebas](https://github.com/elingeyesdev/t6-medicion-ambiental/blob/b7ed148d6162b2f1608f1a26266753c4a5df4d1a/docs/ACTUALIZACION_BASE_M3_03NOV.md). Sin PHPUnit ni recompilación Flutter local en este corte. Sigue **Draft**, pendiente de Jaziel y del recorrido final.
- [PR #94](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/94), HEAD 329dbe7: conflicto resuelto con ambos manejadores y permisos conservados. **7/7 comprobaciones GitHub** verificadas en la interfaz pública el 03/11. Su revisión sigue pendiente.
- #104 se combina sin conflictos con b7ed148 en una comprobación de Git; eso no integra ni aprueba el PR. #120 conserva c2f6ed6 sobre #46 y tampoco presenta conflictos, pero su método necesita revisión.

El [seguimiento de este día](evidencias/seguimiento-20261103.json) distingue lecturas REST core, resultados locales y comprobaciones vistas en navegador. Ninguna aprobación de estas tareas acepta todo M3 ni promueve el corte a main. Los demás PR posteriores siguen separados de #96; la base documental de esta rama continúa siendo #128 y no convierte el índice en un nuevo candidato integrado.

## Continuaciones web del 31/10, cada una en su corte

Estos resultados fueron comunicados para los SHA indicados y conservan sus alcances. **No se suman entre sí ni se trasladan a otro HEAD.** Estos resultados no se repitieron durante la actualización documental inicial; el ensayo nuevo está separado arriba.

- [PR #121, reportes](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/121), `fb3a42b`, hacia `feature/107-energia-superficie`: abierto, con revisión solicitada a Jazielle y sin reviews registradas en la captura. Agrega al PDF de reportes el detalle de faltantes que ya estaba en pantalla y Excel. Referencia: **505 pruebas PHP / 6103 aserciones** y tres JavaScript de reportes. [Reproducción y evidencia existente](../REVISION_REPORTES64.md).
- [PR #125, últimas 24 horas](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/125), `6b7d0f8`, sobre la rama de #121: abierto, con revisión solicitada a Jazielle y sin reviews registradas. Referencia: **511 PHP / 6244 aserciones; 132 JavaScript**. Recupera estimaciones recientes en la ficha del tiempo, separadas del pronóstico; no sustituye la línea temporal raster ni el historial de sensores. [Captura, respuestas y límites de #124](../evidencias/tiempo124/README.md).
- [PR #128, condición del tiempo](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/128), `b994b64`, sobre #125: abierto, con revisión solicitada a Jazielle y sin reviews registradas. Referencia: **516 PHP / 6319 aserciones; 137 JavaScript**. Añade clasificación e iconos de condición actual/diaria, preservando cero, desconocidos y noche; no crea alertas ni nuevas magnitudes. [Evidencia de #127](../evidencias/condicion127/README.md) y [contrato compartido](../../compartido/api/tiempo-lugar.md).
- [PR #129, pantalla completa](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/129), nuevo corte **637e846** del 03/11: abierto y **Draft**, ahora con revisión solicitada a Jazielle. Se comprobó la entrada nativa y la salida por botón/Escape en el navegador integrado, con punto simulado, capas A/B y ajustes abiertos. Se corrigió Escape para salir sin cerrar esos paneles; conservó filtros, selección, fechas y URL. **172 JavaScript**, incluidas **40 focalizadas**. [Capturas, reproducción y límites del nuevo corte](https://github.com/elingeyesdev/t6-medicion-ambiental/blob/637e846305ed39a84f563a982e7afc7f20897483/docs/evidencias/pantalla126/nativa-20261103/README.md). No prueba todos los navegadores ni el atajo físico de Chrome. El respaldo en tres tamaños y sus resultados anteriores siguen identificados como históricos; el intento PHP completo se detuvo a los 25 s y solo se confirmó el caso aislado del mapa principal. Las **7/7 comprobaciones de GitHub** se verificaron en la interfaz para 637e846. La revisión de Jaziel y la integración siguen pendientes.

### Evidencia previa comunicada por Josué, separada del estado actual

- #128: **11/11** comprobaciones vistas en Chrome para `b994b64`; la solicitud de revisión a Jazielle sigue pendiente.
- #129: **7/7** comprobaciones vistas en la UI pública para el corte base `dc01e83`. Ese resultado no verifica el nuevo HEAD `b64e626` con respaldo CSS. Su QA local está separado arriba; el 01/11 se verificó **7/7 correcto en `b64e626`** desde la interfaz pública, con [captura](evidencias/pr129-comprobaciones-20261101.png).

La comprobación anterior de #128 se conserva como antecedente; la de #129 se volvió a observar en la actualización del 01/11. La captura REST guarda `ci: no_verificado`; una lista de reviews vacía tampoco acredita aprobación técnica.

## Avances web del 01/11: buscador, MQTT y correo

Los tres PR siguientes parten de #128 y son ramas hermanas. No se incorporan unos a otros ni al candidato #96 por estar publicados. La revisión se pidió a Jazielle y los PR se colocaron en In review; no equivalen a una aprobación de M3.

- [PR #133, búsqueda de sitios de interés](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/133), 08aa6c4, tarea #131: conserva ciudad/dirección y recupera sitios de interés dentro del buscador original. Al elegir uno, cierra el panel, centra el mapa y abre el tiempo del lugar. **523 PHP / 6394 comprobaciones y 141 JavaScript**. Se comprobó una búsqueda pequeña en Bolivia y la selección en una copia local; no acredita cobertura completa de todos los países. [Capturas y límites](https://github.com/elingeyesdev/t6-medicion-ambiental/blob/feature/131-buscar-sitios/docs/SITIOS_INTERES131.md). GitHub: **12/12**, [captura del mismo corte](evidencias/pr133-comprobaciones-20261102.png).
- [PR #134, integración MQTT local](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/134), d59cd75, parte de #66: broker Aedes y cliente MQTT reales con mensajes sintéticos y SQLite aislado. Pasaron **19 escenarios de protocolo** y la regresión específica de **16 pruebas PHP / 92 comprobaciones**. GitHub ejecuta la integración en Linux y Windows; se habilitó fileinfo, que faltaba en el entorno Windows. El último ajuste adjunta el informe nuevo de cada arranque, con fecha y origen comprobados, en lugar de la evidencia histórica. **8/8** en el HEAD indicado, [captura](evidencias/pr134-comprobaciones-20261102.png). [Reproducción y límites](https://github.com/elingeyesdev/t6-medicion-ambiental/blob/test/66-mqtt-local/docs/MQTT_LOCAL66.md). Falta el dispositivo y broker externos autorizados; #66 sigue abierta.
- [PR #135, rechazo de correo](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/135), 6c0df1c, tarea #132 y parte de #65: distingue rechazo definitivo de envío sin confirmar, sin reenviar automáticamente. Un error al reconectar después de aceptar el mensaje sigue incierto. Servidor SMTP local real: **8 pruebas / 345 comprobaciones**; regresión de **17 PHP / 109 comprobaciones y 7 JavaScript**, Pint correcto. GitHub: **7/7**, [captura](evidencias/pr135-comprobaciones-20261101.png). [Reproducción y límites](https://github.com/elingeyesdev/t6-medicion-ambiental/blob/fix/132-rechazo-smtp/docs/SMTP_LOCAL65.md). Es protocolo local, no llegada a un buzón externo; #65 sigue abierta.

Las herramientas de MQTT y SMTP cierran sus puertos y sesiones, usan cuentas sintéticas y no habilitan proveedores, horarios o envíos en la demo. Las guardas PHP evitan procesos secundarios de consola durante esas pruebas. Los conteos pertenecen a sus respectivos cortes y no se suman. Las capturas de GitHub verifican comprobaciones automáticas; la aceptación de Jaziel y del ingeniero continúan pendientes.

### Comprobación conjunta local del 01/11

Se reunieron #133, #134 en 635b0f8 y #135 en una copia aislada desde #128, sin tocar develop, main ni la demo. El [registro del corte local](evidencias/conjunta-web-20261101.json) identifica el commit y las fuentes. Pasaron **509 pruebas PHP / 6273 comprobaciones**, **141 JavaScript**, **19 escenarios MQTT** y **8 SMTP / 345 comprobaciones**. Ambos servicios locales cerraron sus puertos y sesiones.

La primera ejecución completa devolvió 14 errores porque dos fixtures antiguas necesitan proc_open, deshabilitado para impedir ventanas secundarias. La segunda ejecución excluyó expresamente esos 14 casos; **no se declara una suite completa de 523 casos correcta en este corte conjunto**. [Primer intento](evidencias/conjunta-php-restriccion-procesos-20261101.txt), [PHP acotado](evidencias/conjunta-php-20261101.txt), [JavaScript](evidencias/conjunta-js-20261101.txt), [MQTT](evidencias/conjunta-mqtt-20261101.txt) y [SMTP](evidencias/conjunta-smtp-20261101.txt). Las comprobaciones GitHub de cada PR se mantienen separadas y no sustituyen esa limitación local.

El ajuste posterior del informe CI de #134 no cambia la aplicación y conserva su validación propia. Esta copia conjunta no está publicada ni sustituye la integración revisada del candidato #96.

## Inventario del paquete y recorrido histórico

Los enlaces siguientes conservan los antecedentes del candidato del 30/10. Son evidencia de sus propios cortes, no una declaración de que todos esos PR estén fusionados o aprobados hoy.

- [Radiación y evaporación potencial](../ENERGIA_ENVIROSENSE.md): #107 recupera DSWRF promedio y PEVPR instantáneo del operativo original en W/m². Dos capas nuevas, captura real, perfiles de actualización separados y conservación del archivo. No equivalen a índice UV ni evaporación acumulada en mm.

- [Viento animado del mapa](../VIENTO_ENVIROSENSE.md): continuación #105 sobre #104. Trazos según la capa de viento, control de activación, comparación A/B y pausa. No representa trayectorias de humo ni modifica el tiempo de la capa.

- [Reportes atmosféricos comprobados](../REPORTES_ATMOSFERICOS_VERIFICADOS.md): continuación #103 sobre #102. Dos escalas de aire identificadas, verificación de lugar/zona/unidades, captura externa real y PDF/Excel contrastados en una base temporal.

- [Calidad del aire del lugar](../CALIDAD_AIRE_ENVIROSENSE.md): continuación #101 sobre #100. Consulta actual y 24 horas del índice europeo original, con CAMS global/Open-Meteo, partículas y procedencia. No se mezcla con AQI de otra escala ni completa la capa raster pendiente.

- [Actualización automática local de capas](../ACTUALIZACION_CAPAS.md): continuación #99 sobre el frente recuperado, con una nueva captura real, conservación del archivo y horario Windows comprobado. El candidato original conserva sus pruebas como registro; la corrección visual y esta actualización se revisan en sus propios PR.

- [Corrección del frente de EnviroSense](../evidencias/frente47/README.md): recorrido del original, portada/acceso, mapa y pantallas internas, con capturas comparables y pruebas. Propuesta sobre este candidato, pendiente de revisión.

- [PDF de presentación del corte antiguo M3, anterior a estas continuaciones](../../output/pdf/GeoSur-M3.pdf): el registro del 30/10 describe diez páginas revisadas visualmente. No presenta el estado actual de #121, #125, #128 ni la ampliación de #129. El PDF se actualizará al fijar el candidato que se vaya a presentar; este archivo se conserva como corte anterior.
- [Base y alcance de EnviroSense](../ENVIROSENSE_COMO_BASE.md). El catálogo principal comprende doce países sudamericanos y Guyana Francesa como territorio. Focos y sequías mantienen cobertura probada de Santa Cruz.
- [Comparación de sequías](../qa39/README.md): respuesta, lluvia de las treinta ventanas, contraste SciPy, capturas web y evidencia móvil de Jaziel. PR #88, tarea #39.
- [Avance parcial de agua/inundaciones](../ANA01_INUNDACIONES.md): propuesta, catálogo y adquisición reproducible. PR #46, tarea #40. No hay resultado de inundación ni análisis de píxeles.
- [Cargas e historial](../ENTREGA_DATOS66.md): permisos, CSV/JSON saneado, worker, MQTT y pruebas. PR #91.
- [Capas del mapa](../CAPAS_MAPA_62.md): codificaciones, fechas, reproducción y comparación. PR #93. [Productores GFS/ERA5](../RASTER_ETL66.md): #95 depende de #93 y #97 amplía #95 a ocho capas ambientales.
- [Simulaciones y reportes](../INTEGRACION_M3_SIMULACIONES_REPORTES.md): estado compartido, cortes, gráficos, PDF/Excel y faltantes. PR #92. El candidato M3 añade la capa de simulación continua al mapa; no crea lecturas al dibujarla.
- [Avisos y cuentas](../M3_AVISOS_CUENTAS_WEB.md): bandeja, consentimiento, cola y estados de canales externos. PR #94.
- [Guía del curso](../GUIA_CURSO_ESTADO.md): 61 páginas contrastadas, formularios de tareas/errores y pendientes de permisos. PR #90.

El registro anterior utilizó la demo de 5791, con rol Visualizador. El índice inicial no inició servidores ni cambió cuentas o sesiones. El ensayo del 03/11, registrado arriba, usa una base y cuentas sintéticas propias; no incluye credenciales. Tabler 1.7.1 se conserva como base de componentes internos; el código del proveedor permanece separado de los componentes propios.

## Integración local registrada en el corte histórico

Se reunieron los candidatos de las tareas en `feature/41-evidencias-entrega-m3`, sin fusionar los PR propios sin revisión. Se conectaron rutas de capas, simulación, avisos y cuenta; se conservaron los middleware y se resolvieron los conflictos de rutas/bootstrap manteniendo ambos cambios.

Se guardó un respaldo SQLite consistente antes de aplicar las migraciones `003000`, `004001` y `004002`. No se ejecutó `migrate:fresh` ni se cambió el rol o contraseña de la demo. La base, el respaldo, el entorno y los logs permanecen fuera de Git. Las sesiones y los originales privados de EnviroSense no se importan.

Se publicaron inicialmente 17 PNG GFS reales en almacenamiento privado: temperatura de las 06 UTC y ocho capas de las 09 y 12 UTC del 29/10. Incluyen temperatura, humedad, viento, presión, visibilidad, nieve equivalente de agua, precipitación acumulada e intensidad de precipitación. Se comprobaron sus huellas y el navegador los recibe por rutas autenticadas. El mapa distingue registros, estimaciones y simulación continua. La continuación #99 añade actualización automática local, con 16 imágenes nuevas del ciclo 18 UTC sin eliminar las anteriores; la ejecución periódica necesita el equipo disponible y su revisión propia.

## Validación del 30/10 conservada como registro

**Corte del 30/10 (#107):** 503 pruebas PHP / 6069 comprobaciones, 121 JavaScript y 34 del productor/orquestación, Pint correcto. Captura real y lectura de las dos capas de energía; publicación de cuatro imágenes de tarde y otras cuatro del nuevo ciclo. El registro describe 57 cuadros activos, con las 33 imágenes anteriores conservadas. Estado y evidencia en la guía de energía. Estos números no se actualizan por sumar las continuaciones del 31/10. Los resultados siguientes pertenecen a la integración base anterior y también se mantienen separados.

- Integración PHP: **477 pruebas, 5717 comprobaciones correctas**. Vistas compiladas en un directorio de pruebas separado. Bases y transportes controlados.
- JavaScript integrado: **101 pruebas correctas**, incluidos filtro territorial, capa continua y calma exacta del viento.
- Productor raster: **22 pruebas correctas**, incluyendo GRIB/NetCDF controlados, máscaras, unidades, geografía, intervalos de precipitación, duplicados, fallos HTTP y publicación PHP real de ocho capas. El registro anterior anotaba CI Linux para el contrato de 11 pruebas; **sin verificación en este registro**. La ampliación se documentó en #97.
- Normalizador: **5 pruebas Python**. SQLite concurrente: procesos independientes, con lotes repetidos y conflictos, modos DELETE y WAL.
- PostgreSQL **16.11**: **476 pruebas, 5707 comprobaciones correctas**, incluyendo cuatro importaciones simultáneas y rollback del lote en conflicto. [Entorno aislado y reproducción](../POSTGRESQL_LOCAL_M3.md). El registro dejó el clúster detenido y la demo en SQLite; aquí no se inspeccionaron bases.
- Pint completo correcto para aquel corte, con correcciones de formato en los PR #92/#93 y en la salida de sesión de #94. No se atribuye ese resultado a los HEAD posteriores.
- Sequías real: 114,5 mm; SPI Laravel `-0.6413769540120462`; SciPy `-0.6413769540120433`; diferencia `2.886579864025407e-15`. Cobertura parcial: 1 de 56 municipios. Una celda no acredita la condición de todo el municipio.
- Navegador: demo, mapa continental, comparación de temperaturas, viento y precipitación reales y consulta de sequías con sesión. [Ocho capas disponibles y comparación](evidencias/mapa-ocho-capas-noaa.png), [temperatura](evidencias/mapa-temperatura-noaa.png) y [detalle de sequías](../qa39/evidencias/web-sequias-detalle-2024-09.png). En el centro de la captura nueva se leen 8 km/h y 0 mm, con fechas y período del acumulado visibles.

El [archivo de resultados](resultados.json) corresponde al 29/10 y no se reescribió. Su pendiente Android de #88 quedó superado en `b176111`; para el estado actual, usar la distinción #88/#96 de arriba. Las pruebas históricas con transportes controlados no acreditan nuevas conexiones externas. El registro histórico se conserva sin atribuir sus pruebas o conexiones al ensayo nuevo. La actualización de este PR cambia documentación y evidencias; no activa horarios en la demo.

## Orden de revisión y lo que falta

1. **#88/#39 y actualización de #96 completados:** develop 1313e04 ya está incorporado en b7ed148. #98/#100/#102 están aprobados e integrados en el candidato; #99/#101 cerrados. Flutter, APK e instalación siguen a cargo de Jaziel.
2. **Jaziel, web:** revisar #94 hacia develop y continuar con #104 → #106 → #108 → #121 → #125 → #128 en el candidato. Comparar cada revisión con su HEAD y base capturados, sin adoptar una aprobación o un resultado de otro commit. Después de #128, revisar #133, #134 y #135 por separado; comparten base. El resto del inventario histórico mantiene sus revisiones propias.
3. **#129:** la vía nativa, las dos salidas y la conservación del mapa ya se comprobaron en 637e846; la solicitud de revisión a Jaziel está enviada. Sigue Draft y pendiente de su aceptación; no se da por integrado por tener evidencia.
4. **Candidato #96 y #41:** acordar qué cambios revisados entran, incorporar las dependencias aceptadas con sus responsables y fijar el nuevo SHA de integración. La integración local histórica no equivale a fusión de los PR abiertos. Falta el recorrido cruzado y la aceptación de M3; no se cierra #41 solo por actualizar este índice.
5. **Alcances aún parciales:** #40/#46 no acreditan un resultado de inundaciones. El protocolo MQTT local ya tiene evidencia en #134 y el SMTP local en #135. Dispositivos/broker externos, historial saneado real, AQI raster, UV raster y evaporación histórica ERA5 conservan sus faltantes; la energía de #107 no sustituye esos productos. Los canales externos necesitan su evidencia autorizada; no se activan aquí. Railway/publicación siguen pospuestos.
6. **Planificación y aceptación:** confirmar capacidad con Jaziel y conservar la línea base antes de recalcular. No se fija una fecha académica M3/M4 nueva. La promoción del corte M3 a main requiere la aceptación del ingeniero. El material de presentación puede prepararse antes, identificado con su candidato y pendientes.

## Preparaciones M4: revisión aparte

En la captura siguen abiertos, sin fusionar, con revisión solicitada a Jazielle y sin reviews registradas:

- [PR #119, forestal](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/119), `09cffb9`, hacia `develop`: propuesta de fuente/método y contrato compartido.
- [PR #120, inundaciones](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/120), `c2f6ed6`, hacia `feature/40-analisis-inundaciones`: preparación de escenas/caso; no producto de agua entregado.
- [PR #122, evidencias #118](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/122), `e069556`, hacia `develop`: preparación e índice de pendientes, no paquete final M4.
- [PR #123, Planner #109](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/123), `f11d165`, hacia `develop`: preparación del ajuste de planificación, pendiente de revisión/capacidad.

Estos PR no cierran M3 ni M4, no sustituyen las dependencias de #41 y no confirman fechas académicas. Sus documentos se revisan en su propio alcance.

## Aceptación web: ensayo terminado, revisión cruzada pendiente

- [x] Reunir los PR en una copia local de ensayo e identificar su composición y SHA, diferenciándola del candidato remoto.
- [x] Ejecutar el recorrido web de acceso, mapa, comparación, simulaciones, mediciones, reportes, sequías, focos, preferencias y avisos; guardar capturas y límites.
- [x] Probar pantalla completa nativa y Escape conservando punto, ajustes, comparación, fechas y URL. La revisión de Jaziel de #129 sigue pendiente.
- [x] Abrir el PDF y Excel descargados del reporte, revisar sus datos y las cinco páginas del PDF; revisar visualmente las seis páginas de la guía.
- [x] Registrar el defecto #136, publicar la corrección #137 y repetir el acceso sin permiso sin ampliar roles.
- [ ] Acordar los PR que se aceptarán y fijar el SHA remoto de integración revisado.
- [ ] Jaziel registra SHA, evidencia y resultado de su revisión web; ambos repiten el recorrido con Flutter y sus alcances actuales.
- [ ] Completar la revisión del avance de inundaciones y del paquete #41. Presentar el corte al ingeniero y registrar su aceptación antes de promover M3 a main.

El ensayo propio no marca aprobados los PR ni cierra el milestone. [Preparación y pendientes para defender](recorrido-web-20261103/guion.md).
