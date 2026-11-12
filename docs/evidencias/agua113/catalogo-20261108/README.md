# Recorrido controlado del visor de agua

Se generó el HTML y las respuestas de catálogo/consulta con Laravel, una base SQLite en memoria y publicaciones sintéticas en un directorio temporal exclusivo. Un servidor estático temporal sirvió esas respuestas al navegador de prueba. Las rutas, permisos, sesión y almacenamiento se verifican por separado con las pruebas Feature.

Las figuras de estas capturas son rectángulos sintéticos del contrato. No son escenas de Sentinel ni observaciones ambientales. Los productos de prueba no se instalaron en la base o storage de la web local del usuario ni se incluyen como productos operativos en Git.

El fondo Mapbox no estaba configurado en esta prueba; por eso se ve neutro y el panel muestra el aviso correspondiente. Se comprobaron los polígonos, su selección y el recorrido del panel, no la calidad de un mapa base externo.

[Escritorio, 1400 × 900](escritorio-controlado.png) y [pantalla angosta, 390 × 844](pantalla-angosta-controlada.png).

En ambos tamaños se comprobó que cambiar de lugar mueve el mapa, que cada ubicación centra su propia figura, que cerrar y reabrir conserva el panel y el foco, y que la rueda cambia el zoom del mapa. No hubo desborde horizontal ni errores JavaScript.

Un error 503 controlado eliminó el resultado anterior. Un catálogo vacío controlado dejó la consulta desactivada y no creó resultados. La [salida de la comprobación](recorrido-controlado.json) conserva estos resultados y declara el uso de datos sintéticos.

Falta repetir el recorrido con el producto real de #111, sus fechas, cobertura y referencia independiente, y contrastarlo con Flutter en #115.
