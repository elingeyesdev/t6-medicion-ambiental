# EnviroSense es el sistema principal

**Corrección visual del 29/10:** se ejecutó y recorrió el original, y se trasladaron su portada, acceso, paleta, fuente, navegación, mapa flotante y presentación interna a las vistas Laravel. Simulaciones tiene una entrada propia en Monitoreo. La comparación y las limitaciones están en [evidencias/frente47/README.md](evidencias/frente47/README.md). Esta revisión reemplaza la interfaz verde anterior; no acredita por sí sola toda la migración operativa.

**Estado vigente del 29/10:** Josué pidió preparar M3 hoy y terminar la recuperación web, dejando Flutter a Jaziel. El candidato reúne mapa/capas, cargas, simulación continua, reportes atmosféricos, avisos y cuentas; el índice y sus límites están en [entrega-m3/README.md](entrega-m3/README.md). Se probaron ocho capas GFS reales de NOAA con dos fechas. Faltan revisión, AQI y conexiones/insumos identificados; no se declara conversión operativa completa ni se cambia main. Los pendientes descritos más abajo son el registro del 28/10 y deben contrastarse con ese índice actual.

Corrección confirmada por Josué el 28/10/2026. Seguimiento #61 y #62.

**GeoSur es la adaptación del software completo de EnviroSense.** Sus cuentas, mapa ambiental, datos, sensores, histórico, simulaciones, reportes, plantillas, alertas y notificaciones son la base del producto. Focos de calor, sequías y los otros análisis de la pareja se incorporan dentro de ese sistema. No sustituyen su mapa ni convierten las funciones originales en extras opcionales.

## Qué salió mal

El código original se conservó en una carpeta, pero guardar esa copia no equivale a migrar el software. En la implementación activa, `/mapa` devolvía la pantalla de Focos de calor. Portada y navegación daban prioridad a esos análisis y todavía faltan funciones principales del origen. Por eso la aplicación se veía y funcionaba como un producto más pequeño e independiente.

La documentación ya decía que se conservaría todo; la implementación todavía no cumple ese objetivo. No se da por terminada la migración por cambiar el nombre, agregar un catálogo o mostrar un mapa continental. M2 sigue siendo un corte académico aprobado; no acredita que EnviroSense esté completamente convertido.

## Fuente y geografía comprobadas

Se contrastó `elingeyesdev/equipo11` con GitHub el 28/10: su `main` sigue en `ab4f93a48c2b8a082909feb3310db35296f6cbaa`, el mismo commit que conserva la referencia. La instalación local de escritorio tiene ajustes de arranque; no se importa su configuración privada ni se altera la referencia.

El alcance del original no es solamente Santa Cruz:

- Los seeds geográficos contienen doce países de Sudamérica y Guyana Francesa como territorio. `database/seeds/generate_geojson_seeds.js` identifica expresamente Sudamérica.
- `Frontend/src/hooks/useFronteras.js` puede ampliar su lista con países de un proveedor mundial; `utils/globalCities.js` incluye ciudades de otros continentes.
- `layers/historical/HistoricalWebGL.js` tiene un área de Sudamérica y proyección global. La extensión gráfica no demuestra que cada capa tenga datos para todos esos lugares.
- Los 61 puntos recuperados de `localidades.data.js` son referencias de simulación, no una red acreditada de estaciones.

Sudamérica sí está contemplada. No se deben recortar los servicios globales que ya existían sin revisar su función. A su vez, los análisis nuevos de focos y sequías siguen teniendo cobertura probada de Santa Cruz; no se anuncia cobertura continental para esos análisis.

## Corrección funcional de esta rama

`/mapa` tiene un recorrido propio de monitoreo: países/territorios y 241 regiones con las geometrías originales recuperadas, temperatura, humedad, aire, agua y ruido; puntos, selección, fecha de lectura, origen y enlace al historial. Focos conserva `/modulos/focos-calor` y sequías `/modulos/sequias`, con su cobertura visible. La portada y el menú presentan primero el monitoreo y las mediciones.

El mapa consulta almacenamiento local mediante `PanoramaAmbiental`. Elige la última lectura dentro del período para cada punto, variable, origen y fuente; conserva sus coordenadas históricas y no promedia orígenes. Excluye simulados hasta elegirlos expresamente. No genera mediciones al abrirse ni llama a proveedores. Un punto sin lecturas se muestra como tal.

Rutas: `/mapa/puntos` usa la sesión web; `/api/v1/mapa/puntos` usa autenticación Sanctum para Flutter. Admiten `pais`, `region`, `metrica`, `origen`, `desde`, `hasta` y `pagina`. Desde incluido, hasta excluido, siete días como máximo, sin futuro; sin origen se excluye simulación, `todos` la incluye explícitamente. Devuelven 250 puntos por página y últimas lecturas separadas por procedencia. Los accesos requieren cuenta activa y permisos; las respuestas no se guardan en caché compartida.

Las fechas de la web son de Bolivia, convertidas a UTC en la consulta. Los enlaces antiguos de focos con `modo`, `confianza` o `territorio` van a su pantalla. El mapa puede navegar fuera del continente; el catálogo recuperado no se presenta como una recuperación completa de las regiones o del buscador mundial original.

## Tecnología confirmada en el Planner

Josué confirmó que se debe seguir la tecnología registrada en el Planner. Se comprobó en `11_PLANIFICACION_PERT_CPM_PLANNER.md` y en las notas y actividades C/D de `Cronograma_GeoCruz_ProjectLibre_v2.xml`, conservados en la documentación local: **Laravel y Blade para la web, API compartida y Flutter para móvil**. Los issues documentales [#1](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/1) y [#2](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/2) también registran esa elección. Su selección antigua de funciones fue reemplazada; la tecnología se mantiene.

El original usa React y Node/Express. Sus pantallas y recorridos se adaptan a Laravel/Blade y JavaScript, conservando las funciones; no se cambia la web a React. Tabler **1.7.1** sigue siendo la plantilla de la interfaz y Flutter utiliza Material 3. El mapa de puntos de esta rama es un avance parcial: no reemplaza la recuperación de las capas y controles completos del original.

## Criterio de migración terminada

Antes de declarar el software convertido se deben probar los recorridos originales completos, con los roles correspondientes, y comparar sus resultados con EnviroSense. Siguen pendientes:

- Mapa original: capas meteorológicas, unidades, línea de tiempo, comparaciones y controles; #62. Los límites y el buscador geográfico mundial ya tienen candidatos; la búsqueda de establecimientos/POI sigue pendiente.
- Sensores conectados, MQTT, radar, cargas ETL y datos históricos originales saneados; #66.
- Simulaciones: el recorrido por punto/región y sus escenarios ya tiene candidato Laravel/Blade y API en #52; faltan modo continuo global/sockets, Flutter y revisión. Detalle en [SIMULACIONES_ENVIROSENSE.md](SIMULACIONES_ENVIROSENSE.md).
- Reportes, gráficos, PDF/Excel y plantillas con condiciones tienen candidato en #64; faltan las variables meteorológicas adicionales, Flutter y revisión. Detalle en [REPORTES_ENVIROSENSE.md](REPORTES_ENVIROSENSE.md).
- Alertas, 29 niveles, reconocimiento e historial y avisos internos tienen candidato en #65; faltan los canales externos, tiempo real, Flutter y revisión. Ver [ALERTAS_ENVIROSENSE.md](ALERTAS_ENVIROSENSE.md).
- Chatbot: retirado del alcance por Josué el 28/10; #67 cancelada, no implementada. No se requiere para dar por terminada la migración.
- Revisión de cuentas/permisos de #63 e integración equivalente en Flutter; #48. La PWA original tampoco equivale a la aplicación Flutter.

Estos puntos son trabajo principal pendiente. El orden es completar la base de EnviroSense e incorporar los análisis nuevos dentro de ella. No se borran los avances útiles ni se reemplaza `main`: allí permanece M2. M3 y la migración completa necesitan revisión propia; M3 sigue sin fecha confirmada.

## Regiones recuperadas

Se extrajeron las 241 geometrías de los trece seeds conservados, sin ejecutar SQL, descargar datos ni simplificar los polígonos. `herramientas/datos/recuperar-regiones.py --comprobar` reproduce sus bytes. El índice conserva país, nivel, nombre, caja, fuente, licencia declarada y huellas. Los identificadores dependen del país y nombre originales. La procedencia de Guyana Francesa es GADM 4.1 ADM0; su seed no declara licencia, y no se atribuye una licencia de geoBoundaries a ese archivo. Las regiones son referencias del origen, no certificación administrativa actual.

Al elegir país y región, el mapa dibuja el límite y filtra puntos por su posición actual en el polígono, incluidos sus huecos y partes. No reasigna las coordenadas históricas de las lecturas. Una región sin puntos conserva su límite y muestra que no hay puntos activos. Se rechazan regiones de otro país y códigos ajenos al catálogo.

## Consulta meteorológica recuperada

El servicio de pronóstico del original se adaptó a Laravel/API y a la ficha del mapa, con datos actuales, por hora y por día de Open-Meteo. Conserva estimaciones y faltantes; no los transforma en observaciones. Detalle y pendientes del mapa completo en [TIEMPO_ENVIROSENSE.md](TIEMPO_ENVIROSENSE.md).

El buscador de Mapbox también tiene candidato Laravel/API y formulario en el mapa. Permite distinguir lugares con el mismo nombre y navegar fuera de Sudamérica, sin crear estaciones ni guardar respuestas temporales. Se comprobó Tokio y selección con teclado en Quito. Ver [BUSCADOR_ENVIROSENSE.md](BUSCADOR_ENVIROSENSE.md).

El recorrido de lecturas por momento y la comparación de puntos/fechas tienen candidato en `HISTORIAL_MAPA_ENVIROSENSE.md`. Usan registros guardados y mantienen separadas fuentes y procedencias. La reproducción y comparación de las capas raster del referente siguen pendientes.

También se recupera el selector de unidades de temperatura y viento del original (#85), con preferencia por cuenta, mapa, diferencias, pronóstico, Mediciones y resultados de Simulaciones. No cambia lecturas ni archivos guardados. El contrato queda listo para Flutter; ver [UNIDADES_ENVIROSENSE.md](UNIDADES_ENVIROSENSE.md). Ese recorrido móvil sigue pendiente de adaptación y prueba.
