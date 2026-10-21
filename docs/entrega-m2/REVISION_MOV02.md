# Revisión de MOV-02 / #20

Actualización posterior del 21/10/2026: QA-01 quedó revisado e integrado por PR #33; [resultado actual](../qa21/README.md). Las menciones a QA pendiente en el corte de revisión de MOV-02 que sigue describen aquel momento. ENT-02 continúa pendiente de revisión final de Jaziel.

Corte: 21/10/2026. Responsable de implementación: Jaziel Díaz. Revisión y correcciones puntuales: Josué Padilla. [PR #32](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/32), aprobado e integrado en `develop` mediante `fd23678dd1a95f7464907f185f8cfb1e9aafbb1b`. Versión móvil verificada: `13535fd0154831b182b0c2fcf28b7e8a52ce1444`.

## Decisión y correcciones

Se acepta MOV-02 para integración. El PR estaba dirigido a `main` y se corrigió su destino a `develop`. `main` permanece intacto a la espera de la revisión académica de las propuestas correspondientes.

Durante la revisión se corrigieron dos defectos concretos:

- Al cerrar la pantalla con catálogo o consulta pendientes, descartar el resultado tardío y evitar notificaciones después de liberar el controlador.
- Con cero registros y cobertura ausente/parcial, priorizar el estado de cobertura y su fundamento. No presentar ese caso como prueba de ausencia de detecciones.

Las correcciones tienen cuatro regresiones automatizadas. Se conservó la autoría de los commits originales de Jaziel; el commit de corrección corresponde a Josué.

## Verificación observada

[CI de la versión final](https://github.com/elingeyesdev/t6-medicion-ambiental/actions/runs/39551102830): análisis Flutter sin incidencias, **17 pruebas aprobadas y APK debug compilado**. [APK de prueba](https://github.com/elingeyesdev/t6-medicion-ambiental/actions/runs/39551102830/artifacts/12323169361), sujeto a la retención del servidor de CI. Las respuestas de las pruebas automatizadas son controladas; no prueban una descarga NASA en vivo.

Se inspeccionaron las capturas de Jaziel en [su rama de QA, commit 2e56d6b](https://github.com/elingeyesdev/t6-medicion-ambiental/tree/2e56d6b/docs): consulta de Warnes del 14/10/2026 00:00 al 21/10/2026 00:00 Bolivia, todas las confianzas, un registro, confianza nominal y FRP 3,43 MW. Los detalles web/móvil muestran el mismo identificador (abreviado aquí como `firms-d3b5fec7…c844ead4d`; completo en ambas capturas), fecha observada 20/10/2026 13:32 Bolivia y proveedor/producto NASA FIRMS / VIIRS_SNPP_NRT. Esa es evidencia aportada por Jaziel, no una nueva ejecución del emulador por el revisor.

Comprobación local adicional del revisor: la API de límites respondió HTTP 200 para `BO0702`, con Polygon y versión `scz-codab-v02-1.0.0-propuesta`, compatible con el contrato. En una captura móvil se muestra degradación del límite; la lista y el detalle permanecen disponibles. No se afirma haber reproducido ese fallo ni haber realizado un recorrido final de emulador.

## Lo que falta de QA-01 / #21

Las cinco capturas son evidencia de una consulta válida. Falta el PR actualizado de la tarea y la matriz reproducible de parámetros inválidos, vacío, cobertura, fallo/reintento, resultados anteriores y expiración/paginación, con esperado, observado, versión y evidencia. [Seguimiento concreto publicado](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/21#issuecomment-6309121734).

Las nuevas regresiones de MOV-02 pueden enlazarse como pruebas controladas. No se convierten en una comparación completa de ambas interfaces ni en aceptación automática de #21. ENT-02 / #22 y M2 permanecen abiertos hasta incorporar esa evidencia y la revisión final de Jaziel.

## Cómo probar

Con Laravel preparado en el repositorio oficial, desde la raíz: `./iniciar.ps1 -Direccion 0.0.0.0 -Puerto 8002`. La clave NASA permanece solo en `web/.env`. El visor web está en `http://127.0.0.1:8002/modulos/focos-calor`.

En otra terminal, dentro de `movil/`: `flutter pub get` y `flutter run`. La base Android predeterminada es `http://10.0.2.2:8002/api/v1/`. Seleccionar los mismos filtros, revisar lista/mapa, abrir el mismo ID y comparar fecha, FRP/unidad y procedencia. Salir durante una consulta no debe producir errores del controlador; un período sin cobertura debe explicarse sin afirmar ausencia del fenómeno.
