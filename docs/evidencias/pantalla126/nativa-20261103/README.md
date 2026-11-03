# Pantalla completa nativa · 03/11/2026

Comprobé #126 en el navegador integrado, en segundo plano y con una copia aislada de GeoSur. El servidor 5791 y la cuenta demo no se modificaron. El código anterior era b64e626; las huellas de los dos archivos modificados están en [resultado.json](resultado.json).

## Qué corregí

La entrada y la salida por botón funcionaban. Al pulsar Escape desde el navegador de prueba, se cerraban los ajustes, pero el mapa seguía en `:fullscreen` durante la comprobación. Guardé la [reproducción anterior](antes-correccion.json) y su [captura](escape-antes.png). Esto no demuestra cómo responde el atajo físico de Chrome ni todos los navegadores.

Ahora Escape solicita salir si el mapa es quien ocupa la pantalla completa. Lo atiende antes que los paneles y conserva los ajustes abiertos. Mantiene una sola salida pendiente, muestra el estado ocupado y permite reintentar con el botón si el navegador rechaza la salida. Fuera del mapa ampliado conserva los atajos existentes y no cierra una pantalla completa ajena.

## Recorrido comprobado

1. Abrí un punto sintético de 25 °C con país Bolivia, origen simulado y su período, conservando el mismo enlace.
2. Publiqué en una carpeta privada dos PNG y sus recetas ya conservados en `docs/evidencias/raster66/campos/f003` y `f006`. Son temperatura GFS del 29/10, 09 y 12 UTC; no se descargaron datos nuevos ni se presentaron como actuales.
3. Abrí ajustes, activé las capas y la comparación. Elegí A a las 05:00 y B a las 08:00, hora de Bolivia.
4. Entré con el botón real. Se verificó `:fullscreen` y ausencia de la clase de respaldo CSS: [captura nativa](pantalla-completa.png).
5. Salí con Escape: [captura](salida-escape.png). Volví a entrar y salí con el botón: [captura](salida-boton.png). En ambos recorridos se conservaron todos los campos del formulario, las fechas A/B, la URL, la ficha del punto y los ajustes abiertos.

La comprobación usa la API nativa del navegador, sin sustituirla ni llamar a métodos de la aplicación desde la consola. Los datos, la cuenta y las imágenes están en una copia de prueba. El fondo cartográfico comercial no se configuró en esta copia; la verificación corresponde al mapa Leaflet y sus capas locales, no acredita otros motores ni dispositivos.

## Pruebas y límites

- [Regresión antes](regresion-antes.txt): los tres casos afectados fallaron con el control anterior; el caso de pantalla ajena ya pasaba.
- [Regresión después](regresion-despues.txt): 40 pruebas focalizadas correctas.
- [JavaScript completo](javascript.txt): 172 correctas, cero fallos y cero omitidas. Incluye cuatro regresiones nuevas, sin sumar las focalizadas otra vez.
- Un intento PHP de las clases del mapa se detuvo a los 25 segundos sin completar. El caso aislado del mapa principal, con almacenamiento/cachés temporales, pasó en 9,875 segundos. No se presenta como una suite PHP completa correcta ni se atribuye una causa no comprobada al límite anterior.
- El respaldo CSS y las tres vistas del 31/10 conservan sus capturas y pruebas por separado. Esta revisión no repite esos resultados ni demuestra todo navegador.
- Queda la revisión de Jaziel; #126 y M3 no se cierran solo con esta evidencia. No se modificó Flutter ni main.

Las salidas de Node conservan el contenido; solo se normalizan finales de línea y blancos finales para el formato del repositorio.
