# Reportes y plantillas de EnviroSense

Candidato de #64, sobre Simulaciones #52. Usa Laravel/Blade, la misma base de lecturas del mapa y Tabler 1.7.1. No modifica el código de referencia.

## Recorrido recuperado

- `/reportes`: crear un corte propio de mediciones guardadas o de una simulación propia, consultar los reportes anteriores y descargarlos.
- `/plantillas`: crear, editar, archivar y restaurar plantillas propias. Se conservan los tipos Personalizada, Logística y Salud del original, columnas, gráfico y condiciones `>`, `<` o `=`.
- PDF de varias páginas y Excel con hojas Lecturas/Resumen. Se preservan ceros, unidades, fechas de Bolivia y procedencia. El gráfico web permite elegir cada serie por zona, variable, origen y fuente.
- Los datos y la plantilla se copian al generar el reporte: editar una lectura o plantilla después no cambia un reporte ya guardado.

Los tipos Logística y Salud son categorías organizativas, como en el origen: allí se guardan con configuración vacía y no hay reglas estándar implementadas. No se inventan criterios sanitarios o logísticos. Las condiciones ahora se aplican: todas deben coincidir en el mismo punto, instante, origen y fuente. Una variable ausente no se trata como cero. Las variables disponibles son las cinco recuperadas del almacenamiento; las meteorológicas adicionales se incorporan al recuperar esas capas en #62.

## API para Flutter

Todas las rutas requieren `auth:sanctum`, capacidad `cuenta` y cuenta activa. Visualizador consulta sus reportes; Analista/Administrador los generan y gestionan sus propias plantillas.

- `GET /api/v1/reportes`: listado propio paginado (20).
- `POST /api/v1/reportes`: `{titulo, base: "mediciones", pais?, punto?, metrica?, origen?, desde?, hasta?, plantilla?}`. Fechas ISO UTC; desde incluido, hasta excluido. Por defecto últimos siete días; máximo 366 días, sin futuro. Sin origen se excluyen simulados; `todos` los incluye expresamente.
- Para escenarios: `{titulo, base: "simulacion", simulacion: "ULID", plantilla?}`. Usa la sesión propia calculada, no introduce observaciones en el historial.
- `GET /api/v1/reportes/{id}`: instantánea completa propia.
- `GET /api/v1/reportes/{id}/descargar/pdf` o `/xlsx`: archivo binario, con nombre y tipo de contenido.
- `GET /api/v1/plantillas?archivadas=1`: paginado (30), archivadas opcionales.
- `POST /api/v1/plantillas` y `PATCH /api/v1/plantillas/{id}`: `{nombre, columnas: ["fecha","valor"], grafico: true, tipo?: "PERSONALIZADA", condiciones?: [{variable:"temperatura", operador:">", valor:30}]}`. Máximo diez columnas sin repetir y diez condiciones. Tipos alternativos: `ESTANDAR_LOGISTICA`, `ESTANDAR_SALUD`.
- `DELETE /api/v1/plantillas/{id}` archiva; con `{restaurar:true}` restaura. Las instantáneas previas siguen disponibles.

No se aceptan datos de lecturas ni identificadores de otro usuario enviados por el cliente. Acceso a reportes ajenos: 404. Cuenta sin permiso: 403. Filtros incorrectos o más de mil lecturas candidatas: 422, sin recortar silenciosamente el reporte.

## Exportación y revisión

Dompdf 3.1.6 genera el PDF sin PHP, JavaScript ni recursos remotos. SimpleXLSXGen 1.5.17 genera Excel; el texto se escribe como texto literal para que una celda no ejecute fórmulas ni marcado. No se requieren extensiones GD/Zip en la instalación local actual. Composer fija las dependencias y su auditoría no encontró avisos al instalar.

Pruebas: `../herramientas/php.ps1 artisan test --compact --filter=ReportesTest`, desde `web`. La evidencia se genera en la base aislada de pruebas con `GEOSUR_EVIDENCIA_REPORTES=1`; no usa cuentas ni mediciones del usuario. Ver [evidencias/reportes64](evidencias/reportes64/README.md).

Pendiente: integración de las variables del pronóstico atmosférico, capas meteorológicas adicionales y pantallas/descargas equivalentes en Flutter #48. El chatbot fue retirado por decisión del usuario en #67 y no se activa análisis mediante un proveedor de IA. Esta recuperación no declara terminada toda la migración ni la tarea móvil. No se promovió a main.
