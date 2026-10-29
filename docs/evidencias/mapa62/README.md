# Mapa principal de EnviroSense en GeoSur

Comprobación local del 28/10/2026, tareas #61 y #62. Laravel/Blade, JavaScript y Tabler 1.7.1; API compartida para Flutter, como está registrado en el Planner. El original conservado sigue sin modificaciones.

## Qué comprobé

- `/mapa` abre monitoreo ambiental y deja de abrir directamente Focos de calor. Inicio y menú priorizan el sistema ambiental y las mediciones; focos/sequías siguen dentro, con su cobertura de Santa Cruz.
- La consulta inicial presenta los 61 puntos de referencia recuperados. La base local tiene 360 lecturas simuladas y dos estimaciones de modelo recuperadas en el avance anterior: temperatura y humedad de Santa Cruz, 28/10 a las 08:45 de Bolivia. No hay observaciones de estaciones acreditadas. Por eso la consulta predeterminada de una variable muestra un punto con datos.
- Santa Cruz muestra 17,3 °C y 95 % de humedad al cambiar la variable; la selección se conserva y advierte que son estimaciones. En Argentina, al elegir expresamente simulados y humedad, Buenos Aires muestra 64,8 % simulado. No se capturaron datos nuevos para estas pruebas.
- Al seleccionar, el mapa centra el punto y marca su elección; se puede cerrar la ficha, ver la ubicación histórica de la lectura y abrir su historial. Las fuentes y orígenes se conservan separados.
- Cambiar de país encuadra sus puntos y completa el mapa. Se corrigió un fallo de animación de Leaflet que dejaba el encuadre anterior o parte del mapa vacía al cambiar de país.
- En 390 × 844, los filtros empiezan cerrados, el mapa conserva sus controles y los accesos a análisis siguen en el menú general. La revisión de escritorio se hizo en 1440 × 1000. Se restauró el tamaño normal al terminar.
- Los enlaces antiguos de focos con `modo`, `confianza` o `territorio` llegan a su análisis correspondiente.

## Verificación automática

Laravel: **307 pruebas, 2259 comprobaciones**, todas correctas. Incluyen doce pruebas específicas del mapa con 101 comprobaciones: sesión/token, permisos, filtros, fechas y límites, paginación, coordenadas históricas, selección de la última lectura por procedencia, simulados explícitos y ausencia de escrituras o llamadas externas.

JavaScript: **53 pruebas** correctas. Incluyen cinco para conversión de fecha de Bolivia a UTC, semana exacta, rechazo de futuro/rangos inválidos y selección explícita de origen. También pasaron sintaxis de JavaScript, Pint, validación estricta de Composer e integridad de los 352 archivos de referencia (350 idénticos y dos sustituciones declaradas).

## Capturas

- [Inicio](inicio.png): base ambiental antes de los análisis incorporados.
- [Mapa principal](principal.png): puntos y consulta por país, variable, origen y período.
- [Punto seleccionado](punto-seleccionado.png): lectura estimada con fecha y procedencia.
- [Argentina con simulados elegidos](argentina-simulados.png): cambio de país y datos separados.
- [Vista móvil](movil.png): filtros cerrados, controles y leyenda legibles.

## Límite del avance

Este mapa de puntos es una recuperación parcial. No acredita recuperar todas las regiones, geometrías, capas meteorológicas, línea de tiempo, comparaciones y buscador del original. Tampoco recupera por sí solo simulaciones, reportes, alertas, asistente o integraciones conectadas. El seguimiento completo está en `docs/ENVIROSENSE_COMO_BASE.md`; estas funciones permanecen como trabajo principal pendiente.

## Ampliación de regiones

Recuperadas 241 regiones en trece países/territorios. Tres pruebas adicionales verifican todas las geometrías y sus huellas, el filtro espacial, la relación país/región, códigos inválidos y regiones sin puntos. La extracción conserva polígonos, huecos y multipolígonos sin simplificar. Siguen pendientes las capas meteorológicas y los demás controles originales.

Captura de la ampliación: [Región de Santa Cruz](regiones-santa-cruz.png).
