# GeoSur: recuperación completa de EnviroSense

Decisión de Josué del 27/10/2026, seguimiento #61. **GeoSur conserva todas las funciones de EnviroSense** y añade las funciones ambientales trabajadas por la pareja. Esto reemplaza la selección anterior de solo cinco funciones; no se continúa construyendo un producto independiente del referente.

La tecnología sigue siendo Laravel/Blade, API común y Flutter. Tabler **1.7.1** es la plantilla web real, guardada con su licencia en `web/public/vendor/tabler/`; las adaptaciones viven en vistas, componentes y estilos propios. Flutter conserva Material 3. Se mantiene la referencia de origen sin modificar para comparar cada recorrido.

## Fechas y entregas

Josué aclaró que **el jueves 29/10 corresponde a M2**. La fecha de M3 todavía no está confirmada. Se retiró del milestone la fecha anterior del 29/10 como entrega M3; las fechas de los documentos anteriores son antecedentes, no un nuevo compromiso de conversión completa.

M2 está publicada mediante PR #59 en `main` (`851a79f`), con el mismo contenido que el corte aprobado `e9a591c313f988d1649d3ccaa8086795491298f7`. La versión es `v0.2.0`; pasaron los once controles del PR. Los cambios de GeoSur, diseño y sequías no se adelantaron a esa entrega. La publicación de código no equivale a desplegar un servidor ni a tener un APK firmado.

Por decisión de Josué se trabaja **en local**. Railway queda pospuesto (#49). M3 y la recuperación posterior requieren revisión propia y PR hacia la integración; la aprobación de M2 no los cubre.

## Lo que tiene el referente y cómo lo recuperaremos

La comprobación se hizo sobre el código conservado; no acredita una nueva ejecución de todos sus servicios externos.

1. **Inicio y acceso**: `Frontend/src/App.jsx`, `pages/LandingPage.jsx`, `pages/auth/` y `Backend/Src/modules/auth/`. Inicio, registro, sesión y salida básicos están adaptados en la rama candidata #51/#55; recuperación de contraseña, roles, estado y preferencias faltan en #63.
2. **Mapa y territorio**: `pages/MapaMonitoreo/`, `components/MapaMonitoreo/ControlPanel.jsx`, `hooks/useFronteras.js`, `modules/geografia/` y los seeds geográficos. #62 recupera países/regiones y las capas. Los seeds contienen **doce países y Guyana Francesa**; esta última se conserva como territorio, no como un país independiente. La caja continental de `HistoricalWebGL.js` no prueba que todas las capas tengan datos en toda Sudamérica.
3. **Capas y datos**: `modules/sensores/`, `modules/radar/`, `modules/historial/`, `ETL/`, `data-worker/` y MQTT. #62/#66 recuperan sensores, consultas por punto, unidades, histórico, cargas y visualizaciones meteorológicas/ambientales. Se conservan temperatura, aire, agua, ruido, humedad y las otras variables existentes, sin confundir estimaciones o simulación con mediciones.
4. **Simulaciones**: `modules/simulacion/`, `modules/simulacion-zona/`, `SimulacionContext`, `ZonaSimContext`, `ModalSimulacion` y `PanelSimulacion`. #52 recupera sus variables, escenarios, zonas y controles, con acceso propio visible cuando el recorrido funcione. La restricción anterior de excluir agua y ruido queda reemplazada. No se convierten reglas aleatorias del referente en un método científico de predicción.
5. **Reportes y plantillas**: `pages/Reportes/`, `modules/reportes/` y `modules/plantillas/`. #64 recupera filtros, gráficos, exportación y plantillas por usuario; todavía no están adaptados en Laravel.
6. **Administración**: `pages/Usuarios/` y `modules/usuarios/`. #63 recupera permisos y estados en el servidor, perfil y preferencias. No se copian cuentas o bases privadas del original ni se permite obtener un rol al registrarse.
7. **Alertas y avisos**: `pages/Alertas/`, `pages/Notificaciones/`, `modules/alertas/`, `modules/umbrales/`, `modules/notificaciones/` y transportes de correo, Telegram, WhatsApp y push. #65 conserva todo el recorrido y prepara pruebas locales antes de activar destinatarios o canales externos.
8. **Asistente**: `modules/ia/`, interfaz global y sugerencias de reportes. #67 recupera contexto, límites, configuración privada y errores; proveedor/costo pendientes. No se anuncia una respuesta inventada como análisis real.
9. **Móvil**: las rutas originales `/mobile`, `/mobile/map`, `/mobile/alerts` y `/mobile/profile` se adaptan a Flutter en #48, coordinadas con cada servicio. Focos ya está integrado en M2; sequías #38 y comparación #39 siguen pendientes. Guardar la PWA de React no completa esta adaptación.

El código original se mantiene íntegro como referencia, pero **todavía no están migradas todas sus funciones**. Las rutas sin recorrido no se muestran con tarjetas de “en preparación”. Las capas y pantallas se habilitan cuando estén conectadas y probadas.

## Avance concreto del catálogo

`GET /api/v1/paises` recupera el listado del origen, con filtro opcional `codigo`, procedencia y huellas de los dos seeds. La extracción se reproduce con `herramientas/recuperar-paises-envirosense.py`; salida compartida: `compartido/geografia/paises-envirosense.json`. No ejecuta SQL, descarga geometrías ni importa datos ambientales o cuentas. Las consultas ambientales activas conservan su cobertura de Santa Cruz. Regiones, geometrías y capas continentales siguen en #62.

## Orden de trabajo y Planner

- Mantener M2 y sus evidencias para la revisión del jueves.
- Cerrar revisión de #51 y #55; los PR candidatos no se aprueban por el mismo autor.
- Completar sequías Flutter #38 y comparación #39 con Jaziel, conservando el piloto y los faltantes reales.
- Revisar primero catálogo/contratos y persistencia (#62/#66); cuentas/permisos (#63) antes de las funciones que administren información de usuarios.
- Recuperar Simulaciones (#52), Reportes (#64), Alertas/avisos (#65) y Asistente (#67), cada uno con su prueba equivalente frente al referente y su cliente Flutter (#48).
- Estimar esfuerzo y capacidad de la pareja antes de repartir la recuperación completa entre entregas. El listado `planificacion/Backlog_GeoSur.csv` contiene las tareas vigentes sin inventar fechas o duraciones.

El archivo ProjectLibre anterior se conserva como línea base histórica. Este ajuste actualiza el alcance, las tareas de GitHub y el backlog; **el cronograma recalculado y su archivo ProjectLibre todavía necesitan estimaciones y revisión de la pareja**. No se renombra un `.pod` antiguo para aparentar esa replanificación.

## Diseño y fechas de consulta

El inicio mantiene la fotografía de Amboró; el ingreso usa Lomas de Arena de fondo con formulario más arriba. GeoSur tiene un símbolo vectorial nuevo de silueta continental, vegetación, agua y suelo, sin usarlo como límite cartográfico. Se conservan los recursos de GeoCruz para la historia. La marca es compartida por inicio, acceso y mapa; el selector del mapa y Centrar territorio ocupan una barra separada de los resultados, que se pueden cerrar y volver a abrir.

Las pantallas muestran lenguaje corriente y texto normal de al menos 16 px. Fechas de caché, recuperación, hashes, versiones e IDs internos permanecen en API/evidencias, no en fichas de cliente. Las fuentes, atribuciones y limitaciones que cambian la interpretación del dato sí se conservan.

#60 abre focos con los últimos siete días hasta el instante actual, en hora de Bolivia. Hay accesos a 24 horas y siete días; los calendarios y Laravel rechazan el futuro y conservan el máximo de 168 horas. Los enlaces inválidos tampoco se consultan al proveedor. Flutter debe adaptar su selector y probar el error del servidor; no se da ese cambio móvil por terminado al modificar web.
