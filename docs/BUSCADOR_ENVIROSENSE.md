# Buscador del mapa de EnviroSense

Avance de #62: se recupera la búsqueda de países, ciudades y lugares de `Frontend/src/components/MapaMonitoreo/GeocoderSearch.jsx`. Sigue usando Mapbox. El formulario queda visible al cerrar los filtros y mantiene los componentes y colores de Tabler.

Se busca al pulsar Buscar o Enter, sin una consulta por cada letra. Aparecen hasta seis opciones con su ubicación completa para distinguir nombres repetidos. Al elegir una, el mapa se centra, dibuja su marcador y abre el pronóstico. Limpiar o Escape elimina la búsqueda; una petición anterior no puede reemplazar resultados de un texto más reciente.

La búsqueda no restringe el resultado al país elegido en el historial. El pronóstico consultado es actual y se muestra en la hora del lugar; no depende del período del historial, ni modifica sus puntos o lecturas. El catálogo original de Sudamérica y los análisis de Santa Cruz conservan su cobertura.

## API para Flutter

`GET /api/v1/mapa/buscar?texto=Tokio`, cuenta activa, Sanctum con capacidad `cuenta` y permiso `datos.consultar`. La web usa sesión y `/mapa/buscar`. Se admiten entre 2 y 120 caracteres, hasta veinte palabras, sin punto y coma ni caracteres de control. Máximo doce búsquedas por minuto y cuenta.

Devuelve `fuente=Mapbox` y `datos` con nombre, descripción, latitud, longitud y límites opcionales. Son resultados temporales: no se almacenan en base de datos, caché, historial del navegador ni URL de la página. Las respuestas llevan `Cache-Control: no-store`. Tampoco se añaden a los registros ambientales.

Errores: 401/403 por acceso, 422 por consulta inválida, 429 por frecuencia y 503 si falta configuración o falla el proveedor. Un fallo se diferencia de una búsqueda correcta sin resultados; no se inventan coordenadas. El servidor fija proveedor, idioma y máximo; no envía datos de cuenta, ubicación del usuario ni parámetros adicionales del cliente.

## Proveedor y comprobación

La [documentación de Mapbox](https://docs.mapbox.com/api/search/geocoding/) conserva el formato de respuesta anterior mediante `format=v5` en Geocoding v6. Se usa ese endpoint con el token público ya configurado para los mapas; no se registra otra cuenta ni se habilita almacenamiento permanente. Los resultados de geocodificación temporal no se cachean. La atribución a Mapbox aparece junto al formulario y en el mapa.

Geocoding no cubre la búsqueda de establecimientos/POI de Search Box. Esa integración no se declara recuperada aquí. Las capas raster, comparación y línea temporal del referente siguen pendientes. No se modifica la referencia original para adaptar el buscador.

Se añadieron siete pruebas específicas: acceso, validación antes de red, coordenadas cero, respuestas malformadas, proveedor no disponible, ausencia de caché/escrituras y límite por cuenta. La comprobación real buscó Tokio, mostró opciones en Japón y otros países, centró Japón y abrió el pronóstico con su hora local. Evidencia en `evidencias/buscador62/`.
