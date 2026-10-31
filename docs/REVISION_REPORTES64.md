# Revisión #64 / #61: detalle de faltantes en PDF

Trabajo realizado en `tmp/m4-revision-reportes`, rama `fix/64-revisar-reportes`,
basada en `feature/107-energia-superficie` (`1d6fa34`). Solo WEB.

## Fallo reproducido

Un reporte atmosférico conserva `contenido.faltantes` y permite consultar cada
ausencia en la pantalla y en la hoja Excel **No disponibles**. El PDF solo
imprimía el número total de ausencias: perdía sus localidades, fechas, variables,
unidades y procedencia. Con una serie enteramente ausente ni siquiera permitía
identificar la variable o las horas afectadas.

Reproducción controlada, sin consultar proveedores reales:

1. Cuenta analista; reloj fijado en `2026-10-31T10:00:00Z`.
2. Crear un pronóstico de Santa Cruz, variable lluvia, tres horas.
3. Responder al transporte HTTP con `rain = [0, null, 2]`, unidad `mm`.
4. Abrir el reporte y descargar PDF y Excel. La pantalla y Excel identifican
   `31/10/2026 07:00` de Bolivia como ausente; el PDF anterior no lo hacía.
5. Repetir con `[null, null, null]`: faltan las `06:00`, `07:00` y `08:00`.
   El PDF anterior solo indicaba «3 valores no disponibles» y ninguna lectura.

Las dos regresiones fallaron antes de corregir la vista: **0 filas frente a 1 y
3 esperadas**, respectivamente (2 pruebas, 26 aserciones, 2 fallos).

## Corrección y archivos

- `web/resources/views/reportes/pdf.blade.php`: añade el detalle de faltantes
  del corte guardado, con zona, fecha boliviana, variable, unidad, origen, fuente
  y estado **Sin dato**. Reutiliza los estilos de tablas del PDF y el escape de
  Blade. No transforma ausencias en ceros ni las incluye en promedios.
- `web/tests/Feature/ReportesAtmosferaTest.php`: dos casos de regresión con
  descarga HTTP real dentro de Laravel y comprobación del HTML usado por
  Dompdf. Verifican fechas, unidad y procedencia, conservación del corte y
  ausencia de nuevas consultas al proveedor al abrir o exportar.
- `docs/REVISION_REPORTES64.md`: reproducción, validación y límites.

## Validación ejecutada

Dependencias copiadas físicamente con `robocopy /E /XJ` desde
`tmp/panel-envirosense/web/vendor`; ambos `composer.lock` tienen el mismo SHA-256.
Se regeneró el autoload con el `composer.phar` local y
`dump-autoload --no-scripts --no-plugins --no-interaction`.
La resolución de `ExportarReporte` apunta a este worktree.

PHP 8.3.30 de Laragon; configuración local `tmp/php-reportes.ini` con las
extensiones existentes `mbstring`, `pdo_sqlite`, `sqlite3`, `gd`, `curl` y
`openssl`. `.env.testing` propio, ignorado por Git; `phpunit.xml` fuerza
`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` y sesiones/caché en memoria.

Desde `web`, comandos ejecutados:

```powershell
$phpReportes = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe'
& $phpReportes -c ../tmp/php-reportes.ini vendor/phpunit/phpunit/phpunit --filter test_pdf_conserva_el_detalle_de_faltantes --testdox
& $phpReportes -c ../tmp/php-reportes.ini vendor/phpunit/phpunit/phpunit --filter Reportes --testdox
node --test tests/JavaScript/reportes-grafico.test.cjs
& $phpReportes -c ../tmp/php-reportes.ini vendor/bin/pint --test tests/Feature/ReportesAtmosferaTest.php
git diff --check
```

Resultados después de la corrección:

- Regresiones: **2 pruebas, 36 aserciones**, correctas.
- Suite Reportes: **31 pruebas, 272 aserciones**, correctas. Incluye propiedad
  de reportes/plantillas/descargas, permisos, fechas inválidas, unidades,
  conservación de ceros, cortes anteriores y reportes de varias páginas.
- JavaScript: **3 pruebas**, correctas. Pint y `git diff --check`: correctos.
- Archivos realmente descargados: extracción de texto con `pypdf`, lectura
  del ZIP/XML de Excel con Python estándar y renderizado con Poppler.
  Se confirmó que el PDF pasa de 0 a 1/3 filas de faltantes; Excel conserva
  exactamente el mismo contenido de celdas antes y después. El caso `[0, null,
  2]` mantiene dos lecturas y promedio 1 mm. Revisión visual correcta del PDF
  enteramente ausente y de la página con la lectura cero y el faltante.

Para regenerar los archivos de inspección se puede establecer
`$env:GEOSUR_EVIDENCIA_REPORTES64 = '1'` antes de ejecutar las regresiones.
Se guardan en `tmp/reportes64/`, ignorado por Git. Allí se conservaron también
los archivos anteriores en `antes/` y las páginas PNG inspeccionadas.

## Comprobación final y revisión pendiente

Las pruebas usan transporte HTTP controlado; no se verificó disponibilidad
real de Open-Meteo ni un recorrido en navegador. Después de la revisión acotada también se ejecutó la suite web completa: **505 pruebas y 6103 aserciones**, correctas, con SQLite en memoria y transportes controlados. Las verificaciones de Excel leen el archivo
exportado; no implican una prueba en Microsoft Excel de escritorio.

No se modificaron permisos, frontend global, móvil, datos o configuración del
servidor 5791. La corrección se publica en su PR dependiente y requiere revisión de Jaziel. Los tres archivos quedan listos para revisión de Jaziel; dependencias, entorno y evidencias
temporales quedan fuera del cambio versionable.

Captura de la exportación con tres ausencias controladas: [detalle PDF](evidencias/reportes64/faltantes-controlados.png). No representa una consulta ambiental real.
