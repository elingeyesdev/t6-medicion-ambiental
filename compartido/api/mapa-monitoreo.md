# Mapa principal de GeoSur

Avance de #62 sobre las cuentas y datos de #63/#66. Es el mapa ambiental del sistema principal; focos y sequías conservan sus propias consultas. El alcance completo de las capas originales sigue pendiente.

## Consultar desde Flutter

`GET /api/v1/mapa/puntos`, con `Authorization: Bearer <token>` de una cuenta activa con habilidad `cuenta` y permiso `datos.consultar`. La pantalla web usa la misma consulta en `/mapa/puntos`, con su sesión. Sin credenciales responde 401; una cuenta sin permiso recibe 403. No incluir tokens en URLs ni registros.

Parámetros opcionales, una vez cada uno:

- `pais`: código del catálogo `compartido/geografia/paises-envirosense.json`. Omitido: todos los puntos activos del catálogo.
- `region`: identificador de `compartido/geografia/regiones/indice.json`; requiere `pais` coincidente. Filtra por las coordenadas actuales del punto dentro del polígono original.
- `metrica`: `temperatura` (predeterminada), `humedad`, `aqi`, `ica` o `ruido`.
- `origen`: `observado`, `estimado`, `simulado` o `todos`. Omitido: observados y estimados; excluye simulados.
- `desde` y `hasta`: instantes ISO 8601 con zona horaria. Desde incluido, hasta excluido; máximo 168 horas, sin futuro. Por defecto, los últimos siete días.
- `momento`: opcional, con segundos y zona explícita. Debe estar dentro de `[desde, hasta)`. Incluye las lecturas de ese instante y excluye las posteriores, sin cambiar el período ni rellenar valores.
- `pagina`: entero positivo; 250 puntos por página, ordenados por código.

Ejemplo sin credenciales: `/api/v1/mapa/puntos?pais=BO&metrica=humedad&origen=estimado`.

## Respuesta

Devuelve `metrica`, `unidad`, `desde`/`hasta` en UTC, `momento` (UTC o null), `origen`, `datos`, `pagina`, `ultimaPagina`, `total` , `aviso` y `limite` (Feature GeoJSON de la región elegida, o null). El origen predeterminado se identifica como `sin-simulaciones`.

Cada punto contiene `codigo`, `nombre`, `pais`, `latitud`, `longitud`, `tipo` y `lecturas`. Las lecturas incluyen `valor`, `instante` UTC, `origen`, `fuente` y sus coordenadas históricas. Se devuelve la última dentro del período **para cada origen y fuente por separado**. No se promedian observaciones, estimaciones y simulaciones. Los puntos sin datos tienen `lecturas: []`; cero sigue siendo un valor válido. Los puntos archivados se excluyen.

Las coordenadas del punto pueden haber cambiado: para ubicar una lectura utilizar sus propias coordenadas. Un punto de referencia no acredita una estación física. Identificar en la interfaz qué es registrado, estimado o simulado y mostrar la fecha de la lectura; no presentar todos como observaciones actuales.

Filtros desconocidos, repetidos o inválidos: 422, `error.codigo=FILTROS_INVALIDOS`. Métodos distintos de GET/HEAD: 405. No se devuelven identificadores de cuentas ni autores de cargas. Las respuestas llevan `Cache-Control: no-store`. La consulta no crea datos ni contacta proveedores; no usar datos simulados como sustituto ante un error.

En esta entrega no se verificó la implementación Flutter de este recorrido; Jaziel debe adaptar su pantalla y comparar sus resultados con la web.

Para comparar puntos o momentos, conservar las dos respuestas y emparejar solo la misma variable, unidad, origen y fuente. Mostrar ambas fechas y coordenadas originales. Si falta una lectura, no calcular una diferencia como si fuera cero. Guía y prueba de la adaptación web: `docs/HISTORIAL_MAPA_ENVIROSENSE.md`.
