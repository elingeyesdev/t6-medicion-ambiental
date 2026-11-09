# Agua dentro del mapa de GeoSur

Avance de #113 sobre el PR #158 de #112. Conserva el frente recuperado de EnviroSense, su mapa, menú y componentes. Todavía falta el producto ambiental real y revisado de #111 y su contraste conjunto; este avance no da M4 por terminado.

## Productos guardados

La entrada Agua e inundaciones aparece cuando hay productos registrados, coherentes y accesibles para la cuenta. Abre /mapa/agua. Sin productos, la entrada permanece oculta y la ruta no presenta una pantalla pendiente. Un catálogo alterado no se trata como un catálogo vacío.

Lugar y Comparación ofrecen únicamente las opciones publicadas. Elegir otra opción carga la comparación y centra sus figuras. Seleccionar una ubicación centra esa figura y resalta su botón. El panel se puede cerrar y volver a abrir; la rueda acerca el mapa.

El catálogo y el resultado se consultan por /mapa/agua/catalogo y /mapa/agua/consulta, con la sesión web y los mismos servicios de la API. No se incorpora un token al navegador. La API de Flutter conserva sus rutas /api/v1/inundaciones y su autenticación; no se modifica móvil.

La respuesta debe coincidir con el lugar, fechas y huella elegidos. Si se publicó otra versión entretanto, se pide actualizar la lista. Cambiar de selección cancela la consulta anterior. Un error elimina las figuras previas, sin usar ejemplos como respaldo. Cero, sin cambios y falta de datos conservan su significado.

En pantalla angosta, después de consultar se recogen los filtros para dejar visible el resumen. Cambiar lugar o fechas vuelve a abrirlos. El encabezado y su botón de cerrar permanecen accesibles al desplazar el panel. La fuente y las limitaciones relevantes quedan en el detalle. Si falla el fondo cartográfico, se avisa brevemente sin atribuir ese fallo al resultado del análisis.

## Revisión local con ejemplos

/revision/agua sigue siendo una revisión separada. Requiere APP_ENV=local o testing, cuenta activa, permisos y AGUA_REVISION_LOCAL=true. En producción devuelve 404 aunque la opción esté activa. Sus figuras son sintéticas y el aviso Datos de prueba permanece visible.

La consulta de productos guardados no acepta prueba_controlada. Ningún ejemplo del repositorio se registra ni se publica automáticamente. Registrar un producto requiere el recorrido de #112 y la revisión ambiental de #110 y #111.

## Pruebas y capturas

Las pruebas PHP verifican menú condicional, rutas, cuenta activa, sesión web, productos guardados, errores sin rutas/trazas y bloqueo de la revisión local en producción. Las de JavaScript verifican contrato, identidad, versiones, catálogo, cancelación y falta de cobertura.

Desde web se pueden repetir las pruebas de Inundaciones y BaseWebTest. La batería JavaScript se ejecuta con npm test en CI. Para ejecutar pruebas sin consolas secundarias en esta PC, se usa node --test --test-isolation=none por archivo.

La [comprobación visual controlada](../evidencias/agua113/catalogo-20261108/README.md) recorre escritorio y 390 × 844. Es una prueba del visor con figuras sintéticas y respuestas renderizadas por Laravel. No acredita una inundación observada ni el recorrido final con el producto real.
