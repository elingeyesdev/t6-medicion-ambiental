# Evidencia real de Reportes — #103

Captura del **30/10/2026 a las 00:43 de Bolivia** (04:43 UTC), dentro de una base SQLite nueva en memoria. La cuenta de comprobación y sus reportes desaparecen al terminar el proceso; no se copió una cuenta real ni se alteró la demo.

- `proveedor-tiempo.json` y `proveedor-aire.json`: respuestas HTTP públicas originales de Open-Meteo y CAMS global, sin transformarlas.
- `recibo.json`: parámetros públicos, fechas, HTTP 200, SHA-256 y cero peticiones adicionales al exportar. Registra también la reexportación del PDF desde el mismo corte después de corregir su presentación.
- `reporte.json`: corte del reporte. Contiene nueve valores, unidades, origen estimado, tres resúmenes y metadatos de producto/celda. El ID pertenece solo a la base temporal.
- `auditoria.json`: contraste independiente de valores/unidades con proveedor y Excel, resúmenes, hashes y numeración del PDF.
- `pdf-pagina-1.png` a `pdf-pagina-4.png`: todas las páginas de la exportación final, revisadas visualmente. Resumen y cabeceras se conservan juntos, las tablas continúan con cabecera y cada página tiene su número.
- `excel-lecturas.png` y `excel-resumen.png`: vistas de las dos hojas generadas por la aplicación, revisadas sin modificar el libro.

Exportaciones finales del mismo corte: [PDF](../../../output/pdf/GeoSur-Reporte-Atmosferico-2026-10-30.pdf) y [Excel](../../../output/xlsx/GeoSur-Reporte-Atmosferico-2026-10-30.xlsx).

La temperatura es 20,9 / 20,5 / 20,3 °C; el aire estadounidense es 53 / 54 / 54 USAQI; el europeo, 44 / 41 / 34 EAQI. Las tres horas son 00:00 / 01:00 / 02:00 de Bolivia. Son pronósticos distintos de las observaciones de estaciones; los índices no se reinterpretan como una sola escala.

## Validación

- Suite completa: **501 pruebas PHP, 6057 comprobaciones**, correctas antes del ajuste final de paginación.
- Después del ajuste del PDF: **29 pruebas de Reportes, 238 comprobaciones**, correctas; incluye el corte antiguo y las dos exportaciones.
- JavaScript: **110 pruebas correctas**; no hubo cambios en sus scripts.
- Pint, `git diff --check` y auditoría independiente correctos.
- Cinco pruebas nuevas verifican las dos escalas, metadatos, hash, lugar/zona/unidad/fechas incorrectos, condiciones separadas, conservación del contenido antiguo y control de tamaño/clave.

No se envió correo o mensajería. No se probó Flutter ni se modificó su código. Las pruebas controladas no representan estaciones reales; las dos respuestas públicas anteriores sí son una captura externa fechada. El proveedor puede devolver otros valores al repetirla.

Guía y reproducción: [REPORTES_ATMOSFERICOS_VERIFICADOS.md](../../REPORTES_ATMOSFERICOS_VERIFICADOS.md). Fuente: [Open-Meteo](https://open-meteo.com/en/docs), [CAMS y escalas de aire](https://open-meteo.com/en/docs/air-quality-api).
