# Segunda entrega · GeoCruz

## Estado actualizado · 21/10/2026, después de MOV-02

- **Consulta móvil integrada:** #20 / [PR #32](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/32), revisado y fusionado en `develop` mediante `fd23678d`. Análisis, 17 pruebas y APK debug correctos. [Decisión, correcciones, evidencia y cómo probar](REVISION_MOV02.md).
- **QA-01 / #21:** se revisaron cinco capturas de una consulta válida. Su PR y la matriz de casos restantes siguen pendientes; no se declara aceptada la prueba conjunta.
- **ENT-02 / #22:** índice sincronizado con la consulta móvil y actualizado con sus límites reales. PR #27 permanece en borrador hasta completar #21 y la revisión final del paquete.
- **Referencia EnviroSense:** #29, PR #30 a `main` en borrador y #31 a `develop` pendientes de revisión. No forman parte de la aceptación de MOV-02 y no se fusionan por esta revisión.
- [Registro del corte](integracion_20261021.json). Los cortes históricos siguientes mantienen sus fechas y no describen el estado actual.

## Estado actualizado · 19/10/2026, después de integrar las bases

- **Integrados en `develop`:** [PR #23](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/23), base Laravel/API, y [PR #24](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/24), catálogo territorial, con aprobación formal de Jaziel. [PR #25](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/25), API, integrado tras la [aceptación de Jaziel en #18](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/18#issuecomment-6255891786). Su sincronización no cambió archivos y volvió a pasar los siete controles de CI.
- **Visor integrado:** #19 / PR #26, aprobado por Jaziel y fusionado mediante `9bebd53`. Sus cuatro verificaciones de CI pasaron; #19 está cerrado.
- **Base Flutter integrada:** #17 / [PR #28](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/28), revisada y fusionada en `develop` mediante `202df8f`. Análisis sin incidencias, nueve pruebas y APK debug comprobados en CI; las capturas del emulador fueron aportadas por Jaziel. [Revisión, límites y cómo probar](REVISION_MOV01.md). El consumo real en móvil pertenece a #20.
- **Pendientes de M2:** consulta móvil #20, comparación web/móvil #21 y consolidación/revisión final #22. PR #27 permanece en borrador; el milestone permanece abierto con fecha prevista 22/10/2026.
- [Registro de esta integración](integracion_20261019.json): commits de merge y estados consultados. Los cortes y evidencias de las secciones siguientes conservan sus fechas; sus menciones a PR abiertos describen aquel momento.

Para trabajar desde la base compartida: `git fetch origin`, cambiar a la rama propia y ejecutar `git merge origin/develop`. Resolver y probar cualquier conflicto antes de publicar; no reemplazar el trabajo local ni copiar archivos de la repo demo. El visor corregido sigue disponible en la copia y puerto indicados en la sección 3.

**Preparación parcial de ENT-02 / #22. Corte: 16/10/2026. Entrega prevista: 22/10/2026.**

Este es el índice de la demostración y sus evidencias. La base web, el catálogo, la API y el visor están preparados en PR abiertos. Flutter (#17/#20), la comparación conjunta (#21) y las revisiones siguen pendientes. **No es una entrega final aprobada.**

## 1. Qué abrir

- [Milestone M2](https://github.com/elingeyesdev/t6-medicion-ambiental/milestone/2): objetivo, fecha y criterios de aceptación.
- [Tablero oficial](https://github.com/users/elingeyesdev/projects/13/views/1): avance observado, separado de la aceptación.
- [Visor web local](http://127.0.0.1:8003/modulos/focos-calor?desde=2026-10-10T00:00:00Z&hasta=2026-10-11T00:00:00Z): muestra histórica reproducible mientras el proveedor la conserve.
- [Registro de versiones y evidencias](estado.json): commits completos, PR, verificaciones y huellas SHA256 de los archivos originales en Git.

El registro es una fotografía del corte; consultar los PR para saber su estado actual. La versión ejecutada para las evidencias web es `1540f43c5567d360b6bf66dc7dc57549cc41931e`. Esta rama documental contiene esa base para enlazar todos los recursos, sin fusionar ni aprobar sus PR.

[Verificación de este paquete](verificacion.json): enlaces locales, integridad de los originales y salud HTTP local. No es una nueva ejecución de las suites ni una prueba móvil.

## 2. Implementación preparada

**Josué, revisión de Jaziel pendiente:**

- #15 / [PR #23](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/23): Laravel/Blade, salud de API, arranque y pruebas iniciales. [Informe](../SET01_BASE_WEB_API.md).
- #16 / [PR #24](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/24): catálogo candidato, 1 departamento, 15 provincias, 56 unidades municipales, 72 geometrías; acuerdo territorial compartido pendiente. [Informe](../GEO01_CATALOGO_TERRITORIAL.md).
- #18 / [PR #25](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/25): consulta externa NASA, normalización y contrato `1.0-m2-candidata`. [Informe](../API01_FOCOS_CALOR.md) y [contrato para clientes](../../compartido/api/README.md).
- #19 / [PR #26](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/26): mapa/lista, filtros, detalle y resumen del mismo conjunto. [Informe, código y pruebas](../WEB01_VISOR_FOCOS_CALOR.md).

No sumar las pruebas de #15, #18 y #19 como ejecuciones independientes del producto final: las suites posteriores contienen las anteriores. La suite web registrada más reciente tiene **35 pruebas PHP / 263 aserciones y 14 pruebas JavaScript**. Los ocho controles de CI de PR #26 estaban aprobados al corte. Sus enlaces y resultados están en `estado.json`.

## 3. Arranque en esta PC

La copia ya preparada y probada está en `C:\Users\cjosu\Desktop\GeoCruz-oficial\tmp\web-focos`. Mantener el visor/API en la misma copia y puerto para evitar mezclar versiones.

```powershell
cd C:\Users\cjosu\Desktop\GeoCruz-oficial\tmp\web-focos
.\iniciar-web.ps1
```

Abrir http://127.0.0.1:8003/modulos/focos-calor. Si el puerto ya responde, usar el servidor existente. Para detenerlo: Ctrl+C en su terminal. No volver a ejecutar la preparación ni regenerar claves cuando la instalación ya funciona.

En una instalación nueva de esa versión: ejecutar `preparar.ps1`, configurar la propia `NASA_FIRMS_CLAVE` únicamente en `web/.env` y arrancar `iniciar-web.ps1`. PHP 8.2+, Composer 2 y extensiones según [web/README](../../web/README.md). Node 22+ solo para las pruebas JavaScript. `.env` y dependencias no forman parte del paquete público. La copia documental `tmp/entrega-m2` no trae dependencias ni configuración privada preparadas.

La salud http://127.0.0.1:8003/api/salud comprueba la aplicación; no certifica NASA. Para la demostración externa usar el visor o `/api/v1/focos-calor`, con fechas explícitas.

## 4. Recorrido web de cinco minutos

1. Abrir la [muestra del 10/10 UTC](http://127.0.0.1:8003/modulos/focos-calor?desde=2026-10-10T00:00:00Z&hasta=2026-10-11T00:00:00Z). En Bolivia equivale a **09/10 20:00 → 10/10 20:00**, con final excluido. Explicar fuente, fecha de recuperación y cobertura antes del total.
2. Mostrar resumen y alcance de página. En la ejecución registrada hubo **60 detecciones**, 1 de confianza alta y FRP máxima **6.31 MW**; mapa/lista mostraron 25 de 60. Es una referencia histórica, no una cifra garantizada para futuras consultas.
3. Abrir una fila y el mismo punto: comprobar ID completo, adquisición, FRP, confianza y procedencia idénticos. Cerrar la ficha conserva los filtros.
4. Pasar a la segunda página: comprobar 25 puntos/filas y el mismo total/resumen. El token `versionConjunto` conserva la instantánea; al expirar se debe reiniciar la consulta.
5. Filtrar confianza Alta y consultar. La ejecución registrada devolvió 1 detección y máximo 5.23 MW. Después elegir provincia/municipio y modificar provincia: un municipio incompatible se elimina y los resultados anteriores conservan su ámbito/fecha hasta consultar.
6. Probar Desde posterior a Hasta: debe aparecer validación conservando los resultados anteriores. Deshacer cambios permite recuperar los filtros aplicados. En pantalla pequeña comprobar que formulario, lista y ficha siguen utilizables.

El total puede cambiar por correcciones o disponibilidad del producto NRT. Si falla NASA, mostrar el estado real y, por separado, abrir las evidencias guardadas. No presentar un archivo histórico como respuesta en vivo. Un foco no confirma incendio; MW no mide área quemada y confianza no significa gravedad.

## 5. Evidencia mínima para mostrar

- [Respuesta externa completa](../evidencias/api01/consulta_real.json), recuperada **16/10/2026 05:30:26 UTC**, con parámetros y procedencia; no contiene la MAP_KEY.
- [Escritorio](../evidencias/web01/visor_escritorio.png), [pantalla pequeña](../evidencias/web01/pantalla_pequena.png) y [recorrido observado](../evidencias/web01/recorrido_navegador.json). La pantalla pequeña es web adaptable, no Flutter.
- [Pruebas PHP](../evidencias/web01/pruebas_php.txt) y [JavaScript](../evidencias/web01/pruebas_js.txt): usan respuestas controladas sin red. No sustituyen la consulta externa.
- [Validación geométrica](../evidencias/geo01/validacion_geometrica.json): comprobación técnica de la propuesta, no certificación administrativa ni aprobación de Jaziel.

No hace falta volver a adjuntar cada Word/PDF o duplicar imágenes: este índice enlaza las evidencias versionadas. Para identificar exactamente lo ejecutado, consultar el commit de cada evidencia en `estado.json`.

## 6. Repetir pruebas sin NASA

Desde la copia `tmp/web-focos`:

```powershell
cd web
..\herramientas\php.ps1 artisan test --compact
npm test
```

Separar la fecha de una nueva ejecución de los registros originales. No sustituir sus archivos históricos por una captura posterior sin registrar versión y entorno.

## 7. Qué queda para Jaziel y la revisión conjunta

**Actualización del 19/10:** la base móvil de PR #28 ya fue revisada, probada en CI e integrada en `develop`. Los párrafos del corte original que siguen describen lo que faltaba el 16/10, no niegan ese código nuevo. El [recorrido de QA-01](PRUEBA_CONJUNTA.md) deja listos los casos y la evidencia mínima para ejecutar #21 después de #20; todos permanecen pendientes de ejecución conjunta.

**#17 y #20:** publicar la aplicación Flutter, sus instrucciones reales de arranque, URL base configurable y ejecución en emulador/dispositivo. En este corte `movil/` solo tiene instrucciones; no hay una app ejecutable ni se puede dar un comando `flutter run` verificado.

Para preparar la conexión futura: usar la API del mismo backend. En un emulador Android estándar, el origen del PC es `http://10.0.2.2:8003`, base API `http://10.0.2.2:8003/api`; los recursos ambientales añaden `/v1`. Comprobar primero `/api/salud`. Es configuración propuesta, no prueba móvil. Otras redes/dispositivos requieren su dirección adecuada; ver [desarrollo compartido](../../compartido/DESARROLLO.md). El cliente nunca recibe la clave NASA.

**#21:** ejecutar en ambas interfaces el mismo territorio, período y confianza, usando la misma `versionConjunto` mientras sea válida. Guardar versión de Flutter/backend, fecha, IDs de página, valores, unidades, faltantes, totales, fuente y cobertura. Si las consultas independientes recuperan versiones diferentes, no afirmar equivalencia solo porque coincidan los totales. Añadir casos inválidos, vacío, cobertura, fallo y respuesta antigua; los casos controlados deben identificarse como fixtures.

**#22:** incorporar esas evidencias y defectos/correcciones, registrar revisión del compañero y confirmar la demostración de ambas interfaces. Recién entonces evaluar el cierre de M2. Si no se llega al 22/10, registrar impacto y acuerdo en el seguimiento; no cambiar la línea base silenciosamente.

## 8. Seguimiento y aceptación

- C / EDT 3.1: base web/catálogo preparados; revisión y acuerdo pendientes.
- D / EDT 3.2: base móvil sin evidencia de implementación al corte.
- E / EDT 4.1: API y web preparadas; Flutter y equivalencia conjunta pendientes.
- ENT-02: índice y evidencia web consolidados; paquete final y revisión pendientes de #21.

Este seguimiento no modifica el archivo Planner, las duraciones PERT/CPM ni las fechas aprobadas en M1. Las tareas distribuyen el trabajo de C/D/E sin añadir jornadas. [Decisiones y restricciones actuales](../DECISIONES_M2.md).

Jaziel revisa los PR de Josué; Josué revisa los de Jaziel. La falta de comentarios o las pruebas verdes no sustituyen la aprobación. Se propone revisar las bases #23/#24 antes de #25 y después #26, sincronizando cada rama con lo aceptado en `develop`. Las evidencias previas conservan su SHA; volver a comprobar los cambios resultantes antes de integrar.

## Anexo de revisión · 18/10/2026

Jaziel dejó una revisión preliminar en #18 y pidió explicar la consulta real antes de aprobar. Se agregó la [demostración sencilla de NASA](../API01_DEMOSTRACION_NASA.md), con comando PowerShell y nueva evidencia externa del 18/10: para el 17/10 UTC, 4 detecciones nominales y máximo 5,26 MW; el filtro Alta devolvió cero y potencia máxima desconocida. Son consultas nuevas, separadas de las muestras del corte 16/10 y sus huellas en `estado.json`.

El comando `demostrar-api.ps1` y la guía se incorporaron a las ramas de API, visor y entrega. La implementación y el contrato ambiental no cambiaron. La revisión final, la presentación conjunta y Flutter siguen pendientes; no se registra aprobación por haber preparado esta explicación.

## Revisión del visor · 19/10/2026

#18 fue aceptado y cerrado por Jaziel el 18/10 después de la demostración. Para #19 pidió cuatro ajustes: cerrar la etiqueta anterior, limitar los calendarios, contar puntos próximos y usar etiquetas comprensibles. Las correcciones están en [PR #26](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/26), commit `05bdd8e`, y se incorporan a esta preparación sin fusionar PR en `develop`.

[Guía y evidencia de las correcciones](../WEB01_VISOR_FOCOS_CALOR.md#correcciones-de-revisión--19102026): 21 pruebas JavaScript y 35 PHP / 263 aserciones; recorrido real y pantalla pequeña. Probar el mismo visor y puerto de la sección 3, consultando del 12 al 19/10 UTC, abrir un grupo numerado y cambiar/cerrar selecciones. Los grupos cuentan solo registros de la página y no desplazan coordenadas.

Las evidencias antiguas del corte 16/10 mantienen sus commits y huellas. La nueva revisión de #19 y las tareas móviles/conjuntas #17/#20/#21 siguen pendientes; #22 permanece parcial. Jaziel dejó además conformidad para integrar la base en el comentario del PR #23; su integración es un paso distinto del cierre de los Issues.
