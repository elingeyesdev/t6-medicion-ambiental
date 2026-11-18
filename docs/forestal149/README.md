# Revisión forestal privada

Preparación de #149 sobre el lector de #148. Usa el layout actual de GeoSur y Tabler. No añade una entrada pública ni modifica Flutter.

## Abrir la revisión

En un entorno local con una cuenta activa autorizada para consultar datos y mapa:

~~~dotenv
FORESTAL_REVISION_LOCAL=true
FORESTAL_REVISION_DIR=/ruta/privada/revisiones-forestales
~~~

El directorio por defecto es storage/app/private/forestal/revisiones. Debe contener los paquetes generados con la [receta de #147](../forestal147/evidencias/paquete-20261109/README.md). Nunca se sirve directamente desde public. Tras cambiar configuración cacheada hay que regenerarla en ese entorno.

Abrir /revision/forestal con id, desde y hasta en la query. Para el paquete de la receta, usar su identidad 78a62654465d330e4aa0f241d74e237bfa65de709dee827099dc8594d4e88730 y años 2024 y 2025. El formulario consulta al pulsar Consultar; no carga ejemplos automáticamente.

La vista y /revision/forestal/consulta exigen sesión, cuenta activa, permisos existentes y límites de peticiones. Están cerradas por defecto. En producción devuelven 404 incluso con el flag activo, también para invitados. Las respuestas no se cachean. El middleware conserva este cierre antes de autenticación aunque Laravel reordene los middleware.

## Qué permite comprobar

El lector verifica identidad, archivos y ventana, y filtra la serie y los polígonos al mismo intervalo. La pantalla conserva la advertencia de método pendiente. Muestra el área cartografiada, años y alcance de la ventana técnica, sin asignarla a un municipio.

Seleccionar un polígono desde el mapa o la lista lo centra y resalta. La rueda acerca el mapa. Cambiar los filtros o recibir un error retira los resultados anteriores; las respuestas tardías se descartan. Un año con cero no se convierte en dato ausente. Las áreas se redondean únicamente para lectura, sin modificar los valores del JSON.

La fuente y los límites están en un detalle desplegable. Se conservan versión, crédito y licencia del paquete. Esto no certifica el método ni publica un resultado aprobado.

## Pruebas y evidencia

Las 11 pruebas HTTP y sus 89 aserciones verifican cierre local/producción, sesión, cuenta, permisos, parámetros, errores y límites de consulta. Las 12 pruebas JavaScript comprueban selección, períodos, cero, seguridad de texto y retirada de resultados anteriores. Se comprobó el formato con Pint.

El [recorrido del paquete real](evidencias/20261109/recorrido.json) se hizo a 1400×900 y 390×844: 26 celdas para 2024–2025, una para 2025 y ninguna para 2009. Se verificaron selección, centrado, zoom del mapa, ausencia de desbordamiento y limpieza ante 503, sin errores JavaScript. Las capturas están en esa carpeta.

El recorrido usó HTML y respuestas generadas por Laravel aislado, con el lector real y una cuenta de prueba en memoria. El navegador recibió esas respuestas capturadas; no acredita una sesión de usuario real ni un proveedor cartográfico externo. El mapa base no estaba disponible en la prueba; las geometrías sí se mostraron. Las pruebas HTTP verifican por separado la protección de las rutas.

No hay Vite ni instalación nueva de dependencias de frontend. Blade sirve los módulos ES y CSS de public, usando Leaflet y Tabler ya presentes. La publicación forestal y el contraste científico siguen pendientes.
