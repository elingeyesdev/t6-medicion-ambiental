# Adaptación de EnviroSense en M3

Actualización del 26/10/2026, después de la defensa de M1 y M2. Seguimiento: #29, #47, #48, #49 y #50. Josué confirmó que el ingeniero aceptó las dos entregas y pidió continuar modificando EnviroSense con el stack acordado: Laravel/Blade, API común y Flutter.

## Qué cambiamos del enfoque anterior

La copia de `referencias/envirosense/codigo/` conserva el origen para comparar los cambios. No basta con guardarla y construir otro producto sin relación funcional: la implementación activa es la adaptación de ese sistema, seleccionando y mejorando sus funciones según el Planner. El panel se identifica como **EnviroSense / GeoCruz**. No se vuelve a crear una aplicación Laravel ni se desechan API, geometrías, pruebas o consultas ya aceptadas.

El ingeniero no exigió conservar todas las funciones originales. El alcance sigue siendo los cinco módulos ambientales; los componentes ajenos a ese plan no se incorporan al producto activo. Las entregas documentales anteriores conservan sus versiones originales; este ajuste registra la decisión posterior.

## Revisión del sistema de origen

Las rutas siguientes pertenecen a `referencias/envirosense/codigo/`. La lectura compara el código conservado; no representa una ejecución nueva de todos los servicios originales.

| Elemento original | Decisión para la adaptación | Resultado o seguimiento |
| --- | --- | --- |
| `Frontend/src/components/Layout/Layout.jsx`, Sidebar y Navbar | Conservar el panel y la navegación lateral; adaptar el menú a las consultas del Planner. Sustituir la presentación por Tabler en Blade y componentes compartidos. | #47: menú, cabecera, pie e iconos separados. |
| `Frontend/src/pages/MapaMonitoreo/MapaMonitoreo.jsx`, ControlPanel y `hooks/useFronteras.js` | Conservar navegación espacial, filtros, mapa y selección. Mejorar coherencia entre mapa/lista/ficha y usar el catálogo común de Santa Cruz. | Focos y sequías mantienen sus consultas y límites. |
| `AtmosphericDatePicker.jsx`, `utils/unidades.js` | Conservar selección temporal y unidades; adaptar los períodos al producto real. | Ventana de focos y meses de SPI-3; sin reutilizar períodos meteorológicos como si fueran equivalentes. |
| `Backend/Src/modules/sensores/sensores.service.js`, `utils/response.js`, cliente HTTP | Adaptar acceso, normalización y errores a servicios Laravel y a la API compartida. Mejorar procedencia y faltantes. | Los clientes conservan fuente, período, null y cobertura; no hay reemplazo aleatorio ante fallos. |
| `Frontend/src/pages/mobile/` | Adaptar objetivos y navegación a Flutter, usando componentes de la plataforma. | #48 y #38: el trabajo móvil no queda terminado por cambiar Blade. |
| Asistente global, avisos externos, MQTT, simulaciones, reportes, roles y servicios de agua/ruido | Mantener fuera del producto activo mientras no formen parte del Planner. No migrar cuentas ni bases locales del referente. | No se cargan en el arranque del panel adaptado. La referencia histórica permite auditar la selección. |
| Tarjetas y pantallas de módulos sin implementación | Retirar del inicio y del menú. Conservar el alcance en planificación, sin presentar pantallas de relleno al usuario. | #47: rutas web pendientes responden 404. Las operaciones de API conservan su error explícito; no se simula una consulta exitosa. |

Esta selección es funcional: no se afirma una conversión automática de JSX a PHP ni que los sistemas mantengan las mismas dependencias. No se ejecutan React/Express o los servicios de demostración del referente para arrancar esta adaptación.

## Trabajo añadido al Planner

- **#29 MIG-01:** agrupa la integración de GeoCruz en EnviroSense y su comparación con el origen.
- **#47 REF-WEB, Josué / revisión de Jaziel:** Tabler, navegación, pantallas visibles, organización y recorrido de web. Usa #37 / PR #45; su aceptación sigue siendo necesaria.
- **#48 REF-MOV, Jaziel / revisión de Josué:** Material 3, menú disponible y componentes comunes de Flutter, coordinados con sequías #38.
- **#49 DEP-01, Josué / revisión de Jaziel:** PostgreSQL, preparación de Railway, persistencia, CI y verificación de URL. Cuenta y publicación real pendientes.
- **#50 GIT-01, Josué / revisión de Jaziel:** convenciones del curso, plantillas, corte M2, versiones y APK release; permisos administrativos a cargo del propietario.

Estimaciones iniciales de trabajo adicional, para revisar con el equipo: #47 O/M/P=4/6/10 h (TE 6,33); #48=3/4/6 h (TE 4,17); #49=3/5/9 h (TE 5,33); #50=1/2/4 h (TE 2,17). Total esperado de preparación: **18 horas-persona**, sin esperas administrativas y sin duplicar el trabajo de los módulos ya estimados. No se presupone que ese esfuerzo quepa gratuitamente dentro de F/G.

Se conserva el corte interno M3 del 29/10 y la fecha objetivo final del 30/11. F tenía tres jornadas (23-27/10) y G cuatro (28/10-02/11). Estos cambios añaden carga de interfaz/publicación: hace falta revisar la disponibilidad de ambos, priorizar el recorrido funcional y registrar cualquier desplazamiento real de F/G. Hasta hacerlo, las fechas originales siguen como línea base y no como nuevo pronóstico garantizado. No se rebajan pruebas ni se declara G completo el 29/10.

GitHub Projects y ProjectLibre continúan como herramientas aceptadas por Josué. Se comprobó que las cuatro tareas nuevas aparecen en Project 13 y se ordenó su estado: #47 en progreso durante la implementación, #48/#49 listas y #50 en progreso por la revisión del flujo. #37 y su PR #45 se movieron a revisión. El archivo ProjectLibre conserva su calendario original; la revisión de fechas/capacidad permanece pendiente, sin sustituir el cronograma de M1 en silencio.

## Organización de la plantilla y el código

Ver [ORGANIZACION_CODIGO.md](ORGANIZACION_CODIGO.md). Tabler 1.7.1 y seis SVG de Tabler Icons se guardan localmente con licencia y manifiesto. Las vistas no dependen de una descarga de CDN para dibujar el panel. Los cambios propios no se escriben en el CSS minificado del proveedor.

## Entregas y revisión

El trabajo nuevo va por PR a develop y necesita revisión del compañero. Main recibe solo la entrega aprobada por el ingeniero. La aprobación comunicada de **M2** no se extiende a método/API de sequías, el rediseño u otros cambios de **M3** que ya existan en develop. El corte técnico de M2 es `e9a591c313f988d1649d3ccaa8086795491298f7`; cualquier publicación debe identificar con precisión su contenido y seguir el flujo acordado con el propietario. No se mueve develop hacia atrás ni se fuerza main.
