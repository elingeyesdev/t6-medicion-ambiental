# Productos locales de agua para #112

La API puede leer un catálogo privado de productos registrados, conservar su versión por huella y responder la comparación exacta. No se incluye ningún producto ambiental real ni se registra automáticamente un ejemplo. Continúan pendientes el método de #110, el procesamiento y contraste de #111 y la revisión de Jaziel.

## Recorrido

El procesador de #111 debe producir el JSON del contrato después de comprobar escenas, máscara, geometría, superficies y procedencia. El registro local comprueba estructura, coherencia e integridad; no certifica por sí mismo la exactitud del análisis ni adopta un método.

Desde web, con el mismo usuario del sistema que ejecuta la aplicación:

    php artisan agua:registrar RUTA_PRODUCTO_REVISADO.json

El comando rechaza prueba_controlada; no cambia ese campo para aceptar un ejemplo. Un resultado registrado debe declarar resultado_validado y pasar el contrato compartido, las reglas de cobertura y el catálogo territorial. La entrada es un archivo local, no una URL. No se descargan ni procesan imágenes en este comando.

Repetir el mismo producto devuelve la misma huella y no duplica el catálogo. Un contenido distinto para el mismo lugar y par de fechas exige revisar el cambio y ejecutar el comando con --reemplazar. El índice solo cambia al terminar; los archivos de versiones anteriores se conservan. Un error de validación, integridad o escritura no se convierte en un resultado vacío.

## Archivos y recuperación

El directorio predeterminado es storage/app/private/inundaciones/publicaciones, configurable internamente en config/productos_agua.php. Cada resultado se guarda como SHA256.json; indice.json contiene version: agua-publicaciones-1 y una lista productos de huellas. No se guarda una ruta arbitraria en el índice ni se aceptan enlaces simbólicos.

Los archivos son privados y se incluyen en el volumen de storage preparado en #49. No se deben editar manualmente ni subir a Git como si fueran resultados reales. Para recuperar versiones hacen falta el índice y los archivos correspondientes del mismo respaldo. No se borran versiones automáticamente.

El registro usa un bloqueo compartido y sustituye el índice mediante un archivo temporal del mismo directorio. Debe ejecutarse con los permisos del servicio web. Los límites son 20 productos activos, 2 MB por resultado y 16 MB en total; son límites técnicos de esta preparación, no criterios científicos de calidad.

## Consulta y catálogo

Las rutas conservan cuenta activa, habilidad cuenta, permiso datos.consultar y el límite compartido de consultas.

GET /api/v1/inundaciones/catalogo devuelve los identificadores de versión, lugar, fechas y estado de los productos activos. Sin productos devuelve sin_productos_validados y una lista vacía. Un catálogo alterado falla; no se presenta como un catálogo vacío.

GET /api/v1/inundaciones/consulta recibe territorio, referencia y comparacion. Responde solamente el producto del mismo identificador territorial y las mismas dos fechas, con X-Producto-SHA256. No extiende un recorte municipal al departamento, no mezcla pares ni calcula áreas al consultar. Recorte parcial continúa siendo recorte parcial.

Si existe catálogo para otros lugares, devuelve 404 TERRITORIO_SIN_COBERTURA. Si falta el par solicitado o todavía no hay productos, devuelve 503 PRODUCTO_NO_DISPONIBLE. Un índice, archivo o producto incoherente devuelve 503 PRODUCTO_INVALIDO. No se exponen rutas internas ni se usan ejemplos como respaldo.

Los tres estados del resultado conservan su significado: cambio de agua, sin cambios y sin datos comparables. Cero sigue siendo un dato válido y los faltantes siguen siendo null. No se transforma un cambio de agua en inundación confirmada. La validación estructural no sustituye el control topológico y de doble conteo de #111.

## Comprobación

Desde web:

    php artisan test --compact --filter=Inundaciones

Las pruebas usan productos sintéticos y directorios temporales exclusivos. Cubren lectura, huellas, límites, filtros, cobertura exacta, JSON alterado, rechazo de ejemplos, publicación repetida, reemplazo expreso y conservación de la versión anterior. La prueba de enlaces simbólicos se omite si Windows no permite crearlos y se ejecuta en Linux.

No se habilita una entrada pública pendiente ni se modifica Flutter. El ejemplo real compartido y la prueba de punta a punta siguen esperando el producto de #111.
