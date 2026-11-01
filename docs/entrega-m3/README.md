# GeoSur - estado de M3 y espera de aceptación, 01/11/2026

Actualización del traspaso: **01/11/2026, America/La_Paz**. Jaziel, dejo separado lo que ya tiene evidencia, los PR que faltan revisar y lo que todavía impide aceptar M3. Josué prepara la web y revisa el móvil; Jaziel desarrolla Flutter y revisa la web. EnviroSense sigue como sistema principal, adaptado a Laravel/Blade, API compartida y Flutter. El chatbot está fuera del alcance.

**M3 y #41 siguen abiertos**, igual que #39 y #40. Hay un candidato local histórico y avances posteriores en PR separados; todavía no hay un corte único revisado y aceptado con todo lo citado aquí. Main remoto conserva M2, `v0.2.0`, commit `851a79f5c699249c284001114f76f994c9ce4335`, verificado directamente contra la referencia remota al preparar este corte. Pasar M3 a main requiere la aprobación del ingeniero; la aprobación de M2 no la sustituye. **No hay fecha académica M3 confirmada en este registro.**

La [captura de metadata](metadata-20261031.json) conserva estado nativo (`state`/`closed_at`), Draft, HEAD, base y commit de cada revisión. Se consultaron solo issues, pulls y reviews mediante GitHub REST core, con `gh api GET`; no se consultaron ejecuciones ni se modificó GitHub. El intervalo UTC del 01/11/2026, 00:18-00:19, corresponde al 31/10 por la noche en Bolivia. El `due_on` histórico del milestone no acredita una fecha académica nueva. **CI: sin verificación en este registro REST** (`ci: no_verificado`). El seguimiento `follow_up_129` conserva aparte el SHA posterior y su QA; `follow_up_20261101` actualiza los PR abiertos y #39/#41 sin reescribir la captura inicial. La evidencia de interfaz se conserva en `ui_evidence`, separada de la consulta REST.

## Sequías y Android: distinguir #88 de #96

- [PR #88](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/88), HEAD `b176111`: abierto, sin fusionar. **El conflicto Android ya se corrigió en este corte**. La [revisión con cambios solicitados](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/88#pullrequestreview-5721392681) corresponde a ese mismo SHA y deja tres casos UX para Jaziel: mostrar la cobertura parcial de focos, avisar cuando una consulta falla sin presentar resultados anteriores como actuales y conservar los filtros de sequías después de un error. Falta corregir y repetir ese recorrido móvil; no se cierra #39.
- [PR #96](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/96), HEAD `2516241`: abierto y **Draft**, con revisión solicitada a Jazielle. El fallo Android comunicado para este candidato corresponde a que el ajuste de #88 **todavía no se incorporó aquí**. Los manifiestos de ambos commits conservan esa diferencia. No se está pidiendo rehacer el arreglo en #88 ni se ha compilado Flutter en esta actualización documental.

La integración y las comprobaciones de #96 tienen que revisarse sobre su siguiente SHA efectivo. La documentación de este worktree parte de `b994b64`; eso no cambia el HEAD de #96 ni integra automáticamente los PR siguientes.

## Continuaciones web del 31/10, cada una en su corte

Estos resultados fueron comunicados para los SHA indicados y conservan sus alcances. **No se suman entre sí ni se trasladan a otro HEAD.** No se ejecutaron pruebas en esta actualización.

- [PR #121, reportes](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/121), `fb3a42b`, hacia `feature/107-energia-superficie`: abierto, con revisión solicitada a Jazielle y sin reviews registradas en la captura. Agrega al PDF de reportes el detalle de faltantes que ya estaba en pantalla y Excel. Referencia: **505 pruebas PHP / 6103 aserciones** y tres JavaScript de reportes. [Reproducción y evidencia existente](../REVISION_REPORTES64.md).
- [PR #125, últimas 24 horas](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/125), `6b7d0f8`, sobre la rama de #121: abierto, con revisión solicitada a Jazielle y sin reviews registradas. Referencia: **511 PHP / 6244 aserciones; 132 JavaScript**. Recupera estimaciones recientes en la ficha del tiempo, separadas del pronóstico; no sustituye la línea temporal raster ni el historial de sensores. [Captura, respuestas y límites de #124](../evidencias/tiempo124/README.md).
- [PR #128, condición del tiempo](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/128), `b994b64`, sobre #125: abierto, con revisión solicitada a Jazielle y sin reviews registradas. Referencia: **516 PHP / 6319 aserciones; 137 JavaScript**. Añade clasificación e iconos de condición actual/diaria, preservando cero, desconocidos y noche; no crea alertas ni nuevas magnitudes. [Evidencia de #127](../evidencias/condicion127/README.md) y [contrato compartido](../../compartido/api/tiempo-lugar.md).
- [PR #129, pantalla completa](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/129), nuevo corte `b64e626`: abierto y **Draft**, sin revisores solicitados. También apunta a la rama de #125; #128 y #129 no forman todavía un único corte integrado. Pasaron **168 JavaScript**, incluidas **39 focalizadas**, sin sumarlas de nuevo. El respaldo dentro de la página se comprobó en tres tamaños, con botón/Escape, ficha y ajustes abiertos, filtros y navegación adaptable. [Capturas y registro](https://github.com/elingeyesdev/t6-medicion-ambiental/tree/feature/126-pantalla-completa/docs/evidencias/pantalla126). **Falta la entrada nativa real**; sigue en borrador hasta comprobarla y pedir revisión. Las 20 PHP / 159 aserciones acotadas pertenecen al corte previo `dc01e83`.

### Evidencia previa comunicada por Josué, separada del estado actual

- #128: **11/11** comprobaciones vistas en Chrome para `b994b64`; la solicitud de revisión a Jazielle sigue pendiente.
- #129: **7/7** comprobaciones vistas en la UI pública para el corte base `dc01e83`. Ese resultado no verifica el nuevo HEAD `b64e626` con respaldo CSS. Su QA local está separado arriba; el 01/11 se verificó **7/7 correcto en `b64e626`** desde la interfaz pública, con [captura](evidencias/pr129-comprobaciones-20261101.png).

La comprobación anterior de #128 se conserva como antecedente; la de #129 se volvió a observar en la actualización del 01/11. La captura REST guarda `ci: no_verificado`; una lista de reviews vacía tampoco acredita aprobación técnica.

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

El registro anterior utilizó la demo de 5791, con rol Visualizador. Esta edición no inicia servidores, no cambia cuentas o sesiones y no incluye credenciales. Tabler 1.7.1 se conserva como base de componentes internos; el código del proveedor permanece separado de los componentes propios.

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

El [archivo de resultados](resultados.json) corresponde al 29/10 y no se reescribió. Su pendiente Android de #88 quedó superado en `b176111`; para el estado actual, usar la distinción #88/#96 de arriba. Las pruebas históricas con transportes controlados no acreditan nuevas conexiones externas. Esta edición solo cambia documentación y metadata; no ejecuta pruebas, proveedores, bases ni horarios automáticos.

## Orden de revisión y lo que falta

1. **Jaziel, #88/#39:** resolver los tres casos UX solicitados y dejar evidencia del recorrido sobre el siguiente SHA. El arreglo Android ya está en #88; su incorporación pendiente al candidato #96 es otro paso. Flutter, APK e instalación siguen a cargo de Jaziel; no se modifican aquí.
2. **Jaziel, web:** revisar #121 y después #125; a continuación #128. Comparar cada revisión con su HEAD y base capturados, sin adoptar una aprobación o un resultado de otro commit. El resto del inventario histórico mantiene sus revisiones propias.
3. **#129:** el respaldo y su QA ya están en `b64e626`. Falta comprobar la vía nativa real con un gesto normal, botón/Escape y conservación de capas/selección; después pedir revisión a Jaziel. Sigue Draft. El QA local del respaldo y las comprobaciones automáticas no sustituyen ese recorrido ni su aceptación.
4. **Candidato #96 y #41:** acordar qué cambios revisados entran, incorporar las dependencias aceptadas con sus responsables y fijar el nuevo SHA de integración. La integración local histórica no equivale a fusión de los PR abiertos. Falta el recorrido cruzado y la aceptación de M3; no se cierra #41 solo por actualizar este índice.
5. **Alcances aún parciales:** #40/#46 no acreditan un resultado de inundaciones. Dispositivos/broker, historial saneado real, AQI raster, UV raster y evaporación histórica ERA5 conservan sus faltantes; la energía de #107 no sustituye esos productos. Los canales externos necesitan su evidencia autorizada; no se activan aquí. Railway/publicación siguen pospuestos.
6. **Planificación y aceptación:** confirmar capacidad con Jaziel y conservar la línea base antes de recalcular. No se fija una fecha académica M3/M4 nueva. La promoción del corte M3 a main requiere la aceptación del ingeniero. El material de presentación puede prepararse antes, identificado con su candidato y pendientes.

## Preparaciones M4: revisión aparte

En la captura siguen abiertos, sin fusionar, con revisión solicitada a Jazielle y sin reviews registradas:

- [PR #119, forestal](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/119), `09cffb9`, hacia `develop`: propuesta de fuente/método y contrato compartido.
- [PR #120, inundaciones](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/120), `c2f6ed6`, hacia `feature/40-analisis-inundaciones`: preparación de escenas/caso; no producto de agua entregado.
- [PR #122, evidencias #118](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/122), `e069556`, hacia `develop`: preparación e índice de pendientes, no paquete final M4.
- [PR #123, Planner #109](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/123), `f11d165`, hacia `develop`: preparación del ajuste de planificación, pendiente de revisión/capacidad.

Estos PR no cierran M3 ni M4, no sustituyen las dependencias de #41 y no confirman fechas académicas. Sus documentos se revisan en su propio alcance.

## Plan manual de aceptación web - todo pendiente

Este plan se deja para la revisión; **no se ejecutó en esta actualización**. Reutiliza las respuestas, capturas y exportaciones existentes, conservando su SHA, lugar y fecha, sin presentar ensayos distintos como una misma medición.

- [ ] **Pendiente:** acordar el SHA del candidato y la lista de PR realmente incluidos. Anotar qué sigue fuera, incluido #129 mientras falte la prueba nativa y revisión.
- [ ] **Pendiente:** contrastar el recorrido del mapa con las [capturas del frente](../evidencias/frente47/README.md) y las [ocho capas](evidencias/mapa-ocho-capas-noaa.png): filtros, leyendas, fechas, comparación A/B, unidades y selección. Para sequías, usar la [respuesta y capturas de #39](../qa39/README.md), respetando cobertura 1/56, faltantes y procedencia de cada ensayo.
- [ ] **Pendiente:** revisar el [detalle de faltantes de reportes](../evidencias/reportes64/faltantes-controlados.png) con su exportación/evidencia guardada, y la [ficha de 24 horas](../evidencias/tiempo124/README.md) con sus propias respuestas. Confirmar ceros frente a faltantes, período, UTC/hora del lugar y separación de estimaciones recientes/pronóstico. Contrastar la [condición del tiempo](../evidencias/condicion127/README.md): cero despejado, noche, desconocidos y condición diaria más intensa; no alertas.
- [ ] **Pendiente de aceptación cruzada:** el respaldo de #129 tiene recorrido local y capturas en `b64e626`; Jaziel debe contrastarlo y completar la vía nativa real, salida por botón/Escape y conservación de capas, fondo, ficha y selección. Las comprobaciones automáticas no sustituyen ese recorrido.
- [ ] **Pendiente:** Jaziel registra SHA revisado, evidencia utilizada, observaciones y resultado de la revisión web. Después se presenta el corte al ingeniero para aceptación de M3. Mantener #41 abierta y main en M2 mientras esa aceptación no exista; el PDF enlazado continúa identificado como corte antiguo.
