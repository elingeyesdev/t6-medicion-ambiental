# API de GeoCruz · M2 / #18

Contrato implementado **`1.0-m2-candidata`**, basado en DOC-12. API y catálogo revisados e integrados en `develop` mediante PR #25 y #24; Flutter consume esta API mediante PR #32 y la comparación web/móvil se integró en PR #33. Los identificadores de versión conservan sus nombres publicados; la aceptación técnica no certifica límites administrativos ni sustituye la aprobación académica. Especificación para clientes: [OpenAPI 3.1](openapi.json).

Para conectar Flutter al backend existente, seguir [Desarrollo compartido](../DESARROLLO.md). La referencia React/Express de EnviroSense no cambia estas rutas ni exige rehacer el cliente móvil.

## Operaciones de lectura

- `GET /api/v1/territorios`: `tipo` (departamento/provincia/municipio), `padre`, `pagina`, `tamanioPagina`. Los tres primeros filtros son opcionales; sin filtros devuelve el catálogo. Una página fuera del rango devuelve `datos=[]` conservando el total.
- `GET /api/v1/territorios/{identificador}/limite`: Feature GeoJSON, versión, licencia y procedencia. Ejemplo `BO070101`. No acepta query. ID desconocido: 404; recurso inaccesible: 503.
- `GET /api/v1/focos-calor`: `desde` y `hasta` obligatorios, `provincia`, `municipio`, `confianza`, `modo`, `pagina`, `tamanioPagina`, `versionConjunto` opcionales.

Las consultas son públicas, sin sesión ni clave NASA en el cliente. Se limita a 30 solicitudes/minuto por IP. Errores y respuestas JSON llevan `Cache-Control: no-store`; la caché de backend es independiente. La prueba de salud `/api/salud` mantiene el contrato de #15.

Las rutas de inundaciones, deforestación y calidad del aire responden 503 `MODULO_NO_DISPONIBLE`. Las rutas `/api/incendios`, `dias` y `fuente` de la demo no se trasladan ni se adaptan silenciosamente. Focos conserva detalle/resumen dentro de su misma respuesta.

M3 añade la [API de sequías y su OpenAPI separado](sequias/README.md): consulta, detalle y resumen con SPI-3 del método #35, aprobado por Jaziel en #42 el 25/10/2026. La implementación #43 queda para revisión, con publicaciones preparadas fuera de HTTP y cobertura parcial explícita. La [propuesta original](sequias-propuesta/README.md) se conserva como referencia; el OpenAPI M2 de focos permanece compatible.

## Filtros efectivos

Fechas ISO 8601 con segundos y zona explícita, normalizadas a UTC. Ejemplo válido: `2026-10-10T00:00:00-04:00`; los signos `+` deben codificarse como `%2B`. Intervalo positivo de **máximo 168 horas**, inicio incluido y final excluido. NASA recibe días de calendario UTC en bloques de máximo cinco días; el backend aplica después los instantes exactos. No se amplía silenciosamente el período efectivo.

Solo producto **VIIRS_SNPP_NRT**. Se consulta la disponibilidad publicada antes de pedir CSV de área. Intersección parcial: cobertura `parcial`; rango completamente exterior: `sin_cobertura` con referencia a la disponibilidad; de lo contrario `desconocida`, porque la disponibilidad temporal no prueba observación completa del territorio. Una colección vacía no significa ausencia de incendios. El final publicado por NASA se convierte al inicio exclusivo del día siguiente.

Sin provincia/municipio: BO07 completo. Solo municipio: obtiene provincia desde el catálogo. Ambos deben ser compatibles. Los PCODE son OCHA/COD-AB, no códigos oficiales INE. Filtro por polígono Polygon/MultiPolygon con huecos, no por la caja enviada a NASA. Se incluyen puntos de borde, una sola vez por identificador en cada consulta. Un punto compartido por varios municipios conserva municipio nulo y `motivoAsignacion=frontera_compartida`; no se asigna por orden o proximidad. Consultas independientes de municipios vecinos pueden compartir esos puntos, por lo que sus totales no se suman sin deduplicar. Tolerancia numérica 1e-10 grados; sin buffer cartográfico.

Confianza: `alta`, `nominal`, `baja`, `desconocida`. Cualquier parámetro desconocido, repetido, vacío, arreglo, fecha imposible, nivel territorial equivocado o combinación incompatible produce 422. `modo=externo` es el valor por defecto. `modo=demostracion` produce 503 explícito: no se prepararon datos ficticios operativos.

## Páginas y coherencia

`pagina`: entero >=1; `tamanioPagina`: 1..500, defecto 100. Orden: observadoDesde descendente, identificador ascendente. Resumen y total siempre abarcan **todo el conjunto filtrado**. `representacionMapa.alcance=pagina`: el cliente debe informar que solo tiene esa página o recuperar las demás.

La primera página devuelve `paginacion.versionConjunto` (UUID). **Conservarlo en la query** para páginas siguientes, junto con los mismos filtros. Esta es la concreción de la estrategia pendiente de DOC-12, a revisar por Jaziel: instantánea inmutable retenida 15 minutos, reutilización durante 5 minutos para nuevas consultas equivalentes. Se permite cambiar tamaño de página conservando filtros, pero el cliente debe reiniciar su recorrido para evitar saltos. Una página >1 sin token, un token expirado o con filtros diferentes produce 409 `CONJUNTO_CAMBIADO`; el servidor no descarga otra versión para completar ese recorrido. No hay snapshot indefinido ni base histórica.

`consulta.obtencion.tipo` diferencia `consulta_proveedor` y `cache`. La recuperación original no cambia al leer caché; se informan antigüedad en segundos y vencimiento. `fechaActualizacion=null`: NASA no suministra ese instante en el CSV. Se conserva cada adquisición por registro. El servidor no entrega una caché vencida como respuesta a una nueva consulta fallida. Una instantánea explícita todavía válida puede seguir leyéndose aunque NASA no responda.

## Datos, faltantes y procedencia

La respuesta conserva los nueve campos de DOC-12: versionContrato, datos, consulta, fuentes, resumen, cobertura, advertencias, paginacion y representacionMapa. Todos los objetos/campos están descritos en OpenAPI. `datos[]` contiene el detalle: territorio, Point `[longitud,latitud]` WGS84, fechas, naturaleza, fuentesRef, metodo, atributos y faltantes. Fuente externa NASA, naturaleza `observacion`; no implica incendio confirmado.

FRP se conserva en **MW**, incluido 0 conocido. Vacío, no numérico, negativo o no finito: null y motivo; no inventar cero. Confianza original se conserva; `h/n/l` se normalizan y otro valor queda `desconocida`. Satélite e instrumento ausentes quedan null. Fechas/coordenadas inválidas se descartan y se cuenta `FILAS_INVALIDAS`; no son datos vacíos silenciosos. Versión original por fila, versión común solo cuando es única y conocida. La resolución nominal de 375 m no es superficie quemada ni precisión del punto.

ID estable SHA256 de producto, satélite, instrumento, instante UTC y coordenadas. Duplicados se eliminan; versiones conflictivas del mismo ID se eligen por orden lexicográfico reproducible de atributos y se advierten, antes de filtrar confianza. El ID no se basa en la fila o página. No se mezclan sensores ni productos diferentes en el conjunto.

Indicadores: totalFocos, focosConfianzaAlta y potenciaMaxima. Cada uno declara unidad, registros válidos, método y naturaleza. Máximo null sin potencias conocidas; los registros con FRP ausente todavía pueden contar como detecciones válidas. No se calcula área, causa o gravedad.

## Errores y límites operativos

En error solo se devuelve `error={codigo,mensaje,identificadorConsulta,campos[]}`. 422 filtros inválidos/incompatibles; 404 territorio/ruta; 405 método; 409 conjunto; 429 exceso de consultas (con Retry-After); 503 proveedor, módulo o catálogo no disponible; 500 inesperado. Sin trazas, URL privada ni MAP_KEY.

NASA usa HTTPS y destino fijo; sin redirecciones, 5 s de conexión y 20 s por solicitud, sin reintentos automáticos. Límite 8 MiB por respuesta y 50 000 filas totales: si se exceden, 503 con indicación de reducir período/área, nunca truncamiento exitoso. CSV vacío con cabecera válida es un resultado vacío; HTML o columnas esenciales ausentes son fallo. Ningún error cambia a datos simulados.

## Probar desde esta copia de trabajo

Raíz de esta rama: `C:\Users\cjosu\Desktop\GeoCruz-oficial\tmp\api-focos`. Preparar con `preparar.ps1` en una instalación nueva. Configurar **solo localmente** `NASA_FIRMS_CLAVE` en `web/.env`. Arrancar `./iniciar.ps1 -Puerto 8002`. Pruebas automáticas: desde `web/`, `../herramientas/php.ps1 artisan test --compact`; usan respuestas controladas, no la clave ni Internet.

Abrir en navegador:

- `http://127.0.0.1:8002/api/v1/territorios?tipo=provincia`: 15 provincias.
- `http://127.0.0.1:8002/api/v1/focos-calor?desde=2026-10-10T00:00:00Z&hasta=2026-10-11T00:00:00Z`: respuesta real externa, número de registros sujeto a actualización del proveedor/caché.
- Añadir `&confianza=alta`: datos y resumen solo con esa confianza.
- Añadir `&provincia=BO0702&municipio=BO070101`: 422 FILTROS_INCOMPATIBLES, sin llamada NASA.

El navegador muestra JSON; el mapa y la pantalla funcional corresponden a #19. Flutter consume estas mismas rutas en #20; no se acredita una prueba móvil en #18.

## Referencias verificadas

- [DOC-12 y revisión registrada](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/12).
- [NASA Area API: bloques de 1..5 días, caja y fechas](https://firms.modaps.eosdis.nasa.gov/api/area/).
- [NASA disponibilidad por producto](https://firms.modaps.eosdis.nasa.gov/api/data_availability/).
- [Atributos oficiales VIIRS: UTC, confianza y FRP](https://firms.modaps.eosdis.nasa.gov/content/descriptions/FIRMS_VIIRS_Firehotspots.html).
- [Procedencia, fechas y licencia del catálogo](../territorios/README.md).
