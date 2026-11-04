# GeoSur: recuperación completa de EnviroSense

**Actualización vigente del 29/10:** Josué pidió entregar hoy el candidato M3 y recuperar la web completa, con agentes en paralelo y sin desarrollar móvil. Flutter queda con Jaziel en #89. El trabajo concreto, capturas, pruebas y límites actuales se reúnen en [entrega-m3/README.md](entrega-m3/README.md). Este pedido reemplaza la ausencia de fecha de M3 indicada en el registro anterior. La aceptación académica de M3, los servicios externos y la promoción a main siguen separados de preparar este candidato.

**Aclaración del 28/10:** EnviroSense completo es el sistema principal y nuestros análisis se incorporan dentro de él. La migración sigue incompleta: conservar sus archivos no equivale a convertirlo. Se corrigió `/mapa`, que abría Focos de calor, y se documentaron las funciones originales que aún faltan en [ENVIROSENSE_COMO_BASE.md](ENVIROSENSE_COMO_BASE.md). Se comprobó la tecnología registrada en el Planner y confirmada por Josué: Laravel/Blade, API compartida y Flutter. Se adaptan las funciones originales a esa tecnología.

Decisión de Josué del 27/10/2026, seguimiento #61. **GeoSur conserva las funciones de EnviroSense, con la excepción del chatbot retirada por Josué el 28/10** y añade las funciones ambientales trabajadas por la pareja. Esto reemplaza la selección anterior de solo cinco funciones; no se continúa construyendo un producto independiente del referente.

La tecnología sigue siendo Laravel/Blade, API común y Flutter. Tabler **1.7.1** es la plantilla web real, guardada con su licencia en `web/public/vendor/tabler/`; las adaptaciones viven en vistas, componentes y estilos propios. Flutter conserva Material 3. Se mantiene la referencia de origen sin modificar para comparar cada recorrido.

## Fechas y entregas

Josué aclaró que **el jueves 29/10 corresponde a M2**. La fecha de M3 todavía no está confirmada. Se retiró del milestone la fecha anterior del 29/10 como entrega M3; las fechas de los documentos anteriores son antecedentes, no un nuevo compromiso de conversión completa.

M2 está publicada mediante PR #59 en `main` (`851a79f`), con el mismo contenido que el corte aprobado `e9a591c313f988d1649d3ccaa8086795491298f7`. La versión es `v0.2.0`; pasaron los once controles del PR. Los cambios de GeoSur, diseño y sequías no se adelantaron a esa entrega. La publicación de código no equivale a desplegar un servidor ni a tener un APK firmado.

Por decisión de Josué se trabaja **en local**. Railway queda pospuesto (#49). M3 y la recuperación posterior requieren revisión propia y PR hacia la integración; la aprobación de M2 no los cubre.

## Lo que tiene el referente y cómo lo recuperaremos

La comprobación se hizo sobre el código conservado; no acredita una nueva ejecución de todos sus servicios externos.

1. **Inicio y acceso**: `Frontend/src/App.jsx`, `pages/LandingPage.jsx`, `pages/auth/` y `Backend/Src/modules/auth/`. Inicio, registro, sesión y salida básicos están adaptados en #51/#55. #63 añade recuperación, roles, estado y preferencias en web/API, en rama propia pendiente de revisión e integración móvil; detalle en `CUENTAS_GEOSUR.md`.
2. **Mapa y territorio**: `pages/MapaMonitoreo/`, `components/MapaMonitoreo/ControlPanel.jsx`, `hooks/useFronteras.js`, `modules/geografia/` y los seeds geográficos. #62 recupera países/regiones y las capas. Los seeds contienen **doce países y Guyana Francesa**; esta última se conserva como territorio, no como un país independiente. La caja continental de `HistoricalWebGL.js` no prueba que todas las capas tengan datos en toda Sudamérica.
3. **Capas y datos**: `modules/sensores/`, `modules/radar/`, `modules/historial/`, `ETL/`, `data-worker/` y MQTT. #66 tiene candidato local web/API con 61 puntos de referencia, cinco variables, historial, gráfico, captura de temperatura/humedad e importación CSV; detalle en `DATOS_AMBIENTALES.md`. Se distinguen registros, estimaciones y simulaciones. Faltan revisión, sensores conectados, radar, cargas masivas y móvil; preparar puntos no acredita estaciones físicas ni observaciones.
4. **Simulaciones**: `modules/simulacion/`, `modules/simulacion-zona/`, `SimulacionContext`, `ZonaSimContext`, `ModalSimulacion` y `PanelSimulacion`. #52 recupera sus variables, escenarios, zonas y controles, con acceso propio visible cuando el recorrido funcione. La restricción anterior de excluir agua y ruido queda reemplazada. No se convierten reglas aleatorias del referente en un método científico de predicción.
5. **Reportes y plantillas**: `pages/Reportes/`, `modules/reportes/` y `modules/plantillas/`. #64 tiene candidato en #74: reportes por usuario, gráficos, PDF/Excel y plantillas con condiciones. Faltan los datos atmosféricos adicionales, Flutter y revisión; ver `REPORTES_ENVIROSENSE.md`.
6. **Administración**: `pages/Usuarios/` y `modules/usuarios/`. #63 tiene candidato web/API de permisos, estados, perfil, ubicación y recuperación. Faltan revisión y Flutter. No se copian cuentas o bases privadas del original ni se obtiene un rol al registrarse; los canales de avisos siguen en #65.
7. **Alertas y avisos**: `pages/Alertas/`, `pages/Notificaciones/`, `modules/alertas/`, `modules/umbrales/`, `modules/notificaciones/` y transportes de correo, Telegram, WhatsApp y push. #65 tiene candidato en #75: niveles originales, alertas, reconocimiento e historial y avisos internos con preferencias. Faltan tiempo real, canales externos y Flutter; ver `ALERTAS_ENVIROSENSE.md`.
8. **Chatbot retirado**: Josué confirmó el 28/10 que no quiere chatbot. #67 se cancela como fuera del alcance, sin implementarla. No se solicitan claves ni se activa un proveedor de IA.
9. **Móvil**: las rutas originales `/mobile`, `/mobile/map`, `/mobile/alerts` y `/mobile/profile` se adaptan a Flutter en #48, coordinadas con cada servicio. Focos ya está integrado en M2; sequías #38 y comparación #39 siguen pendientes. Guardar la PWA de React no completa esta adaptación.

El código original se mantiene íntegro como referencia, pero **todavía no están migradas todas sus funciones**. Las rutas sin recorrido no se muestran con tarjetas de “en preparación”. Las capas y pantallas se habilitan cuando estén conectadas y probadas.

## Avance concreto del catálogo

`GET /api/v1/paises` recupera el listado del origen, con filtro opcional `codigo`, procedencia y huellas de los dos seeds. La extracción se reproduce con `herramientas/recuperar-paises-envirosense.py`; salida compartida: `compartido/geografia/paises-envirosense.json`. No ejecuta SQL, descarga geometrías ni importa datos ambientales o cuentas. Las consultas ambientales activas conservan su cobertura de Santa Cruz. Las 241 regiones originales ya están recuperadas en #72. Siguen pendientes las capas completas, buscador y comparaciones de #62.

## Orden de trabajo y Planner

- Mantener M2 y sus evidencias para la revisión del jueves.
- #51 y #55 ya fueron aprobados e integrados por Jaziel en develop el 28/10. Continuar la revisión de #68 y sus ramas dependientes; los candidatos no se aprueban por el mismo autor.
- Completar sequías Flutter #38 y comparación #39 con Jaziel, conservando el piloto y los faltantes reales.
- Revisar primero catálogo/contratos y persistencia (#62/#66); cuentas/permisos (#63) antes de las funciones que administren información de usuarios.
- Recuperar Simulaciones (#52), Reportes (#64), Alertas/avisos (#65) (sin chatbot; #67 cancelada), cada uno con su prueba equivalente frente al referente y su cliente Flutter (#48).
- Estimar esfuerzo y capacidad de la pareja antes de repartir la recuperación completa entre entregas. El listado `planificacion/Backlog_GeoSur.csv` contiene las tareas vigentes sin inventar fechas o duraciones.

El archivo ProjectLibre anterior se conserva como línea base histórica. Este ajuste actualiza el alcance, las tareas de GitHub y el backlog; **el cronograma recalculado y su archivo ProjectLibre todavía necesitan estimaciones y revisión de la pareja**. No se renombra un `.pod` antiguo para aparentar esa replanificación.

## Diseño y fechas de consulta

El inicio mantiene la fotografía de Amboró; el ingreso usa Lomas de Arena de fondo con formulario más arriba. El 28/10 Josué eligió la tercera propuesta de #69/#70, **Trama ambiental**: monograma GS con verde, azul y terracota. Su adaptación vectorial reemplaza el símbolo continental anterior en los componentes compartidos; detalle en `diseno/LOGOTIPO_ELEGIDO.md`. Se conservan los recursos anteriores para la historia. La marca es compartida por inicio, acceso y mapa; el selector del mapa y Centrar territorio ocupan una barra separada de los resultados, que se pueden cerrar y volver a abrir.

Las pantallas muestran lenguaje corriente y texto normal de al menos 16 px. Fechas de caché, recuperación, hashes, versiones e IDs internos permanecen en API/evidencias, no en fichas de cliente. Las fuentes, atribuciones y limitaciones que cambian la interpretación del dato sí se conservan.

#60 abre focos con los últimos siete días hasta el instante actual, en hora de Bolivia. Hay accesos a 24 horas y siete días; los calendarios y Laravel rechazan el futuro y conservan el máximo de 168 horas. Los enlaces inválidos tampoco se consultan al proveedor. Flutter debe adaptar su selector y probar el error del servidor; no se da ese cambio móvil por terminado al modificar web.

## Pronóstico recuperado y revisión actual

El servicio meteorológico del original ya tiene candidato Laravel/API y ficha dentro del mapa: datos actuales, 168 horas y siete días de Open-Meteo cuando están disponibles. No se almacenan como observaciones. Ver `TIEMPO_ENVIROSENSE.md`.

Simulaciones #73, Reportes #74 y Alertas #75 pasaron las comprobaciones de GitHub. #77 corrige la dependencia de correo; su auditoría no encuentra avisos conocidos. El pronóstico está en #78 y el buscador geográfico tiene candidato en `BUSCADOR_ENVIROSENSE.md`. Las ramas siguen pendientes de revisión propia después de #51 y #55, ya integrados.

Jaziel ya integró #55, #68, #71 y #72 en develop. La siguiente revisión de la cadena es #73. El historial por momento y comparación de puntos/fechas está en #83, explicado en `HISTORIAL_MAPA_ENVIROSENSE.md`; las capas raster y su reproducción siguen abiertas.

#85 recupera las unidades de temperatura y viento, su elección por cuenta y aplicación a las consultas de la web. Detalle en `UNIDADES_ENVIROSENSE.md`. El PR #84 de Jaziel prepara parte del recorrido Flutter; no completa por sí solo todos los servicios y pantallas pendientes de #48.
