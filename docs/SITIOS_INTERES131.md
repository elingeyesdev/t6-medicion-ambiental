# Sitios de interés del mapa · #131

Continúa #62 sobre el candidato de #128 (`b994b64`). Recupera la búsqueda de establecimientos dentro del buscador existente de EnviroSense, adaptado a GeoSur. Conserva Laravel/Blade, Tabler 1.7.1, los colores y las cuentas actuales; no añade otra pantalla ni cambia Flutter.

## Uso

En la lupa del mapa, **Qué buscas** permite elegir **Ciudad o dirección** o **Sitio de interés**. La primera opción conserva el proveedor de geocodificación actual. La segunda busca parques, instituciones o establecimientos mediante una petición puntual al pulsar Buscar. Conviene escribir el nombre y la ciudad. El centro visible del mapa orienta la búsqueda de sitios; no es un filtro ni una consulta de GPS.

Al elegir un resultado se centra el mapa, se muestra su marcador y se abre su ficha del tiempo. El panel de búsqueda se cierra para no tapar el punto y el foco vuelve al mapa. Un cambio de texto o tipo cancela la búsqueda anterior y evita que su respuesta vuelva a llenar la lista.

No crea puntos ambientales ni lecturas. No guarda resultados del proveedor en la base, caché, URL o historial de navegación. Una búsqueda fallida no se presenta como una búsqueda vacía.

## Proveedor y cobertura comprobada

El original usa Geocoding v5 y contempla iconos POI, pero Mapbox retiró los POI de ese producto. El modo actual de ciudades sigue en Geocoding v6; el modo nuevo usa la búsqueda puntual Search Box `/forward`, con `types=poi`, límite seis e idioma español. No utiliza autocompletado, sesiones de sugerencias, rutas o cálculo de tiempos de llegada. Usa el token público ya configurado y la atribución existente.

Fuentes oficiales: [Geocoding v5](https://docs.mapbox.com/api/search/geocoding-v5/), [Search Box](https://docs.mapbox.com/api/search/search-box/), [ampliación de cobertura anunciada por Mapbox](https://www.mapbox.com/blog/mapbox-search-box-api-expanded-poi-coverage-smarter-search). La sección geográfica de la referencia de API sigue enumerando Estados Unidos, Canadá y Europa, mientras el anuncio describe una expansión posterior. No se resuelve esa diferencia suponiendo cobertura mundial.

El 01/11/2026, la cuenta ya configurada respondió HTTP 200 a una consulta acotada de establecimientos en Santa Cruz, Bolivia. La web permitió seleccionar el resultado boliviano, mostrar el marcador y consultar su tiempo con datos estimados. Esta comprobación puntual **no acredita cobertura completa de Bolivia o Sudamérica**, actualidad de cada establecimiento ni precisión certificada. No se almacenan respuestas Mapbox como un catálogo.

## Contrato y resguardos

[Contrato para web y Flutter](../compartido/api/buscador.md). Se conservan cuenta activa, Sanctum con capacidad cuenta para API, permiso datos.consultar y doce búsquedas por minuto por cuenta, compartidas entre ambos tipos. No se amplía el límite al cambiar de opción. La posición opcional debe venir completa y dentro de rangos; nombres, tipos y coordenadas inválidos se rechazan antes de salir a la red.

El servidor fija el host, endpoint, idioma y límite; no sigue redirecciones ni acepta URL del cliente. Valida tipos, tamaño de respuesta, geometría y coordenadas, incluidos ceros válidos. No devuelve la clave ni el texto privado de excepciones.

## Validación

- Laravel: **523 pruebas / 6394 aserciones**, suite completa de este corte. Dentro de ellas, 14 pruebas / 138 aserciones de ambas búsquedas. No se suman de nuevo.
- JavaScript: **141 pruebas**, incluidas cuatro nuevas de selección, centro cero, compatibilidad y cancelación/respuesta tardía.
- Pint correcto para los tres PHP editados.
- Prueba en navegador interno oculto, copia aislada en 5794 y cuenta sintética. Marcador y ficha visibles; panel cerrado y foco en mapa. Se conservan demo y servidor 5791.

[Evidencia](evidencias/sitios131/README.md). Faltan la revisión de Jaziel y la aceptación del candidato. Main conserva el corte académico aprobado; esta tarea no cierra toda la migración.
