# Comprobación de reportes #64

Se generan PDF y Excel desde peticiones autenticadas de pruebas, con SQLite aislado, simulación explícita y cuentas descartables. No son observaciones del territorio.

- `comprobacion-exportaciones.json`: lectura independiente de PDF y Excel; 100 lecturas, varias páginas, ceros conservados y ningún nodo de fórmula.
- `pdf-pagina-1.png`, `pdf-pagina-4.png`, `pdf-pagina-8.png`: páginas renderizadas con Poppler e inspeccionadas visualmente; encabezados repetidos y filas completas.
- Pruebas de servidor: acceso entre cuentas, roles, plantillas y condiciones, archivo/restauración, corte inmutable, sin datos, simulación, fechas, límites, PDF y Excel reales y texto con marcado literal.

Reproducir desde `web`: establecer `GEOSUR_EVIDENCIA_REPORTES=1` y ejecutar `../herramientas/php.ps1 artisan test --compact --filter=ReportesTest`. Los binarios temporales quedan en `tmp/reporte64-*.pdf` y `.xlsx`, excluidos de Git.

Falta la comprobación del recorrido completo en Flutter y la revisión de Jaziel. La cuenta demo conserva su rol Visualizador; no se amplió su permiso para tomar capturas.

Comprobación adicional en el navegador: con una cuenta local temporal Analista se creó una plantilla con temperatura mayor a 30 °C y un reporte de dos zonas simuladas. Se obtuvieron cuatro lecturas válidas de diez; el selector cambia de gráfico y ambas descargas PDF/Excel terminaron correctamente. `reporte-filtrado-navegador.png` muestra el resultado. La cuenta demo no cambió de rol y la temporal se desactiva al cerrar la revisión.
