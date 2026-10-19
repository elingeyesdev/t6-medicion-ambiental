# WEB-01 · Visor web de focos de calor

Issue #19 · M2 · Josué Padilla. Preparación y verificación: **16/10/2026**, Bolivia. Revisión de Jaziel pendiente. Rama `feature/19-web-focos-calor`.

## Resultado

La página `/modulos/focos-calor` implementa el recorrido de DOC-11 V2 y CU-01 a CU-04: elegir territorio/período, consultar, revisar mapa y listado, abrir la misma detección desde ambos y consultar su resumen/procedencia. El inicio distingue este módulo operativo de los otros cuatro, que conservan su estado pendiente.

El navegador consume el contrato común de #18, mediante su propio backend. No llama directamente a NASA ni recibe su MAP_KEY. La fuente y los atributos no se copian de los ejemplos ficticios del prototipo. Las fechas de filtros y presentación son de Bolivia (UTC−4); se envían instantes UTC con inicio incluido y final excluido, máximo 168 horas.

## Dónde está el código

- `web/app/Http/Controllers/InicioController.php`: elige la vista de cada módulo; no consulta NASA al entregar el HTML.
- `web/resources/views/focos-calor/index.blade.php`: formulario y estructura accesible del visor, indicadores, mapa, listado y ficha.
- `web/public/js/focos-calor/inicio.js`: prepara el catálogo, relaciona provincia/municipio y conecta los controles.
- `web/public/js/focos-calor/contrato.js`: fechas Bolivia/UTC, representación de números y lectura/validación de respuestas API.
- `web/public/js/focos-calor/consulta.js`: ciclo de consulta, cancelación, respuesta anterior, reintento, paginación y control de respuestas atrasadas.
- `web/public/js/focos-calor/vista.js`: presenta datos y una ficha común para lista/mapa; los valores externos se insertan como texto.
- `web/public/js/focos-calor/mapa.js`: representación Leaflet y selección de puntos. No recalcula pertenencia territorial, confianza ni indicadores.
- `web/public/js/focos-calor/periodo.js`: límites recíprocos de los calendarios; conserva el máximo de 168 horas y permite limpiar/restaurar fechas.
- `web/public/js/focos-calor/representacion.js`: etiquetas comprensibles y agrupación visual de los registros próximos de la página, sin modificar coordenadas.
- `web/public/css/focos-calor.css`: estilos del módulo y adaptación a pantalla pequeña.
- `web/app/Services/FocosCalor/` y `Territorios/`: backend heredado de #18/#16; se conserva su contrato candidato.

## Filtros, páginas y estados

Cambiar provincia elimina un municipio incompatible; seleccionar un municipio sin provincia obtiene su padre del catálogo. Deshacer cambios restaura los filtros aplicados, sin volver a descargar el conjunto. Los campos modificados no alteran el ámbito o fechas de los resultados visibles: se rotulan **Resultados anteriores · conservados**. Una nueva consulta cancela la anterior y solo la generación más reciente puede publicar resultados.

Mapa y lista muestran **la misma página de hasta 25 detecciones**, con alcance visible. No se presenta esa página como todo el mapa departamental. Total, confianza alta, FRP máxima y faltantes vienen del **conjunto completo filtrado**. Siguiente/Anterior conservan filtros y `versionConjunto`; al vencer se ofrece reiniciar desde la primera página, sin mezclar resultados. La URL conserva los filtros aplicados para compartirlos, pero no promete que el conjunto NRT o la página persistan al volver a abrirla.

Se diferencian carga, respuesta válida, vacío, parcial, falta de cobertura comprobada, cobertura desconocida y fallo. Un error conserva la respuesta anterior con sus fechas y filtros; no aparecen simulaciones. Si falla el límite o el mapa, se mantiene el listado y su detalle. El mapa base puede perder teselas sin convertir los datos ambientales en un fallo o una ausencia. Los valores nulos se muestran como **Sin dato**; FRP 0 conocida se conserva como 0 MW.

La ficha conserva ID completo, coordenadas, adquisición UTC/Bolivia, fuente/producto, naturaleza, sensor, versión, confianza original/normalizada, FRP en MW y faltantes. Centrar territorio modifica la vista del mapa y no filtra los resultados. Colores = confianza; no gravedad, superficie quemada ni incendio confirmado.

## Pruebas y evidencia

- **35 pruebas PHP, 263 aserciones**, incluyendo el backend y las nuevas comprobaciones del HTML, separación de módulos, ausencia de credenciales en la vista e integridad de Leaflet público. [Registro](evidencias/web01/pruebas_php.txt).
- **14 pruebas JavaScript sin red**, con respuestas controladas: fechas/offset, faltantes, transporte, paginación, respuestas fuera de orden, edición durante carga, fallo con datos anteriores, reintento, expiración 409, límite inaccesible y estados vacío/parcial/sin cobertura. [Registro](evidencias/web01/pruebas_js.txt). No acreditan nuevas consultas NASA.
- Chrome real contra Laravel y NASA: consulta del **10/10/2026 UTC**, **60 detecciones**, 1 de confianza alta y máximo **6.31 MW**; primera y segunda página con 25 puntos y 25 filas, manteniendo total/resumen/recuperación. Filtro de confianza alta: 1 punto y 1 fila, máximo **5.23 MW**. Son resultados de una muestra histórica, sujetos a correcciones de NRT.
- Seleccionar el mismo registro en la lista y el mapa abre el mismo ID completo. Se comprobó el descarte de municipio incompatible, la conservación de resultados al editar y el rechazo del período invertido.
- Pantalla pequeña de Chrome **390 × 844**, con selección de detalle, sin desborde horizontal del documento. Es una verificación de web adaptable, no una prueba Flutter o de teléfono físico.
- [Recorrido observado en navegador](evidencias/web01/recorrido_navegador.json). [Escritorio](evidencias/web01/visor_escritorio.png). [Pantalla pequeña](evidencias/web01/pantalla_pequena.png).

## Probar por cuenta propia

En esta PC, abrir PowerShell:

```powershell
cd C:\Users\cjosu\Desktop\GeoCruz-oficial\tmp\web-focos
.\iniciar-web.ps1
```

Abrir **http://127.0.0.1:8003/modulos/focos-calor**. En una instalación nueva, ejecutar antes `preparar.ps1` y configurar la propia `NASA_FIRMS_CLAVE` en `web/.env`. No requiere compilación de frontend ni instalar paquetes npm para usar el visor; PHP/Composer se preparan como en #15. Node 22+ solo para ejecutar `npm test` desde `web/`.

Para repetir la muestra, abrir http://127.0.0.1:8003/modulos/focos-calor?desde=2026-10-10T00:00:00Z&hasta=2026-10-11T00:00:00Z . Los controles muestran el período equivalente en Bolivia: 09/10 20:00 → 10/10 20:00. El número puede cambiar si NASA corrige el producto o cambia su disponibilidad.

1. Revisar que total/resumen corresponden al conjunto y que mapa/lista indican el alcance de página.
2. Abrir una fila y su punto: comprobar mismo ID, FRP, confianza y procedencia. Cerrar detalle conserva la consulta.
3. Pasar a la página siguiente: cambian sus filas/puntos y se conservan total/resumen/fecha de recuperación.
4. Elegir confianza Alta y consultar: todos los resultados y el resumen se ajustan al filtro.
5. Cambiar provincia/municipio: se marca la respuesta anterior. Deshacer conserva la consulta anterior; Consultar aplica el nuevo territorio.
6. Probar Desde posterior a Hasta: se explica el error y no se sustituyen los datos anteriores. Revisar Fuente, cobertura y límites de interpretación.

Las pruebas controladas se ejecutan con `npm test` y `../herramientas/php.ps1 artisan test --compact` desde `web/`. Las pruebas de navegador y la consulta externa son evidencias separadas.

## Dependencias y revisión

Esta rama parte de #18/PR #25 e incluye las bases #15/PR #23 y #16/PR #24 aún en revisión. No se fusionaron en `develop` ni se acredita aprobación de Jaziel. El PR de #19 se revisa con esas dependencias visibles y debe sincronizarse después de su aceptación.

Siguen pendientes la adopción del catálogo/reglas candidatas y la revisión del compañero. Flutter e integración conjunta pertenecen a #17, #20 y #21. Este visor no añade exportaciones, cuentas, alertas, reportes ni otros módulos ambientales implementados.

## Cartografía y librería

Leaflet **1.9.4** se distribuye localmente con licencia BSD-2-Clause, procedencia e integridad verificadas en `web/public/vendor/leaflet/`. El mapa base usa teselas HTTPS de OpenStreetMap con atribución visible y caché normal del navegador; no hay descarga masiva ni modo offline. Los puntos, límites, fuente ambiental y fechas no se deducen del mapa base. Referencias: [Leaflet estable y descargas](https://leafletjs.com/download.html), [política de teselas OSM](https://operations.osmfoundation.org/policies/tiles/).

## Correcciones de revisión · 19/10/2026

Se atienden las cuatro [observaciones de Jaziel en #19](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/19#issuecomment-6256265997):

1. **Una sola selección:** el visor administra una etiqueta y un anillo en las coordenadas reales. Cambiar de detección retira inmediatamente los anteriores; cerrar detalle, cambiar de página o aplicar otra respuesta los elimina. Se desactivó el desvanecimiento de Leaflet para que no queden etiquetas anteriores visibles durante su retirada.
2. **Calendarios de hasta siete días:** `min`/`max` se calculan en ambos sentidos, con diferencia positiva y máximo 168 horas. La validación de consulta y la del backend se conservan. `Limpiar fechas` permite elegir un período lejano sin quedar atrapado por los límites del anterior; `Deshacer cambios` recupera los filtros aplicados. No se corrige una fecha inválida cambiándola silenciosamente.
3. **Conteos de puntos próximos:** se agrupan registros conectados a 28 píxeles o menos al zoom actual. Los números cuentan registros de la **página**, no zonas administrativas ni todo el conjunto. El símbolo de grupo se ancla en una coordenada existente; los registros no se desplazan ni se separan artificialmente. Pulsar o activar con teclado un grupo acerca el mapa y abre su lista de detecciones. Incluso cuando comparten coordenadas exactas pueden elegirse desde esa lista; al acercar se recalculan los grupos.
4. **Etiquetas comprensibles:** fecha/hora Bolivia, municipio, confianza y FRP con su unidad o `Sin dato`. El ID completo se conserva en el detalle y en la asociación mapa/lista. La etiqueta tiene ancho legible y se desplaza la vista del mapa para evitar recortarla, sin cambiar la ubicación del registro.

**Validación:** 35 pruebas PHP / 263 aserciones y **21 pruebas JavaScript**, incluidas siete regresiones nuevas de calendarios, edición manual, grupos, coordenadas coincidentes, contenido de etiquetas y reemplazo/cierre de selección. Las pruebas controladas no consultan NASA.

El recorrido real en Chrome consultó del **12/10/2026 00:00 UTC al 19/10/2026 00:00 UTC**: 690 detecciones, 16 altas y FRP máxima 448,04 MW en esa ejecución. La primera página mostró 25: grupos de 10, 4, 7 y 3, más un punto individual, sin alterar el total. Son resultados de esa consulta externa, no cifras fijas del producto. Cambiar de selección produjo una etiqueta, luego una etiqueta distinta, y cerrar dejó cero; el mismo ID se comprobó desde punto/lista/grupo. El calendario rechazó 168 horas más un segundo y conservó los resultados aplicados. Cambiar a la segunda página conservó el total y limpió selección/grupo. Pantalla pequeña de **390 × 844** sin desborde horizontal; etiqueta completa dentro del mapa.

Evidencia nueva, sin sobrescribir el recorrido anterior: [escritorio con grupo y selección](evidencias/web01/revision_20261019/grupos_y_seleccion_final.png), [pantalla pequeña](evidencias/web01/revision_20261019/pantalla_pequena_verificada.png), [recorrido observado](evidencias/web01/revision_20261019/recorrido_navegador.json), [pruebas PHP](evidencias/web01/revision_20261019/pruebas_php.txt) y [pruebas JavaScript](evidencias/web01/revision_20261019/pruebas_js.txt).

Para repetir: arrancar `iniciar-web.ps1` y abrir http://127.0.0.1:8003/modulos/focos-calor?desde=2026-10-12T00:00:00Z&hasta=2026-10-19T00:00:00Z . Elegir dos detecciones y cerrar detalle; abrir un grupo numerado; limpiar/restaurar fechas y comprobar el máximo de siete días. La disponibilidad NRT puede cambiar. Las correcciones quedan para nueva revisión de Jaziel; no se registra aprobación de #19 ni ejecución Flutter por estas pruebas. #18 ya consta cerrado y aceptado en su comentario del 18/10, lo cual no equivale a haber fusionado su PR.
