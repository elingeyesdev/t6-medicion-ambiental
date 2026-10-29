# Sequías: contrato candidato MET-01 / #35

**Referencia histórica de la propuesta aprobada por Jaziel en #42 el 25/10/2026.** Sus esquemas/ejemplos conservan el estado original para reproducir la evidencia offline. La implementación #36 publica una [versión HTTP separada](../sequias/README.md), sujeta a revisión en #43. [Método, evidencia y reproducción](../../../docs/MET01_METODO_SEQUIAS.md). El contrato M2 de focos continúa vigente.

## Solicitud propuesta para #36

`GET /api/v1/sequias?mesFinal=2024-09&municipio=BO070101&muestra=scz-piloto-01`

[Esquema de filtros](consulta.schema.json), [ejemplo](consulta.ejemplo.json). Obligatorio `mesFinal` en `AAAA-MM`; política candidata: desde enero de 2021 hasta el último mes UTC completo que realmente entregue el proveedor. Se excluye la base de calibración del rango inicial de consulta. Una consulta de enero acumula noviembre/diciembre anteriores. No hay `desde`, `hasta`, rango arbitrario, confianza NASA, ni límite de 168 horas. La ventana de tres meses y la base son fijas por versión del método.

`provincia` y `municipio` son PCODE existentes del catálogo; el municipio determina su provincia, y si se envían ambos deben ser compatibles. Sin filtros territoriales se solicitan las muestras registradas de BO07, no un SPI medio departamental. `muestra` limita a un identificador registrado dentro del territorio efectivo; no admite coordenadas/URL arbitrarias del cliente. El servidor rechaza parámetros desconocidos, duplicados, vacíos, arreglos, mes actual/futuro, jerarquías incompatibles o meses anteriores a 2021. El JSON Schema verifica estructura; fechas disponibles, pertenencia y duplicados HTTP son validación semántica pendiente de #36.

## Respuesta candidata

[Esquema JSON](respuesta.schema.json), [ejemplo reproducible](respuesta.ejemplo.json). El ejemplo se **generó desde la captura externa** con `herramientas/met35/crear_ejemplo.py`; `obtencion.tipo=reproduccion_captura`, `esRespuestaHttp=false`. No es una llamada a Laravel ni una fixture inventada.

- `versionContrato`: `sequias-0.1-propuesta`; adopción posterior exige publicar su versión operativa.
- `datos[]`: muestra estable, territorio de contexto, `municipioDelCentroCelda` (nullable si el centro cae fuera del catálogo o en frontera ambigua), coordenada solicitada y de celda `[longitud,latitud]`, fuente, naturaleza derivada, método, precipitación en mm y `spi`. El municipio del centro no reemplaza al del punto solicitado. `spi` incluye valor adimensional, categoría sin redondear, motivo nullable, años válidos, parámetros Gamma y probabilidad ajustada. `null` lleva motivo explícito. No es una estación ni evento de incendio.
- `consulta`: mes final, fechas inclusivas efectivas, zona UTC, filtros resueltos, revisión pendiente y obtención. En operación habrá `consulta_proveedor` o `cache`, con recuperación original conservada; un ejemplo offline no se presentará como consulta en vivo.
- `fuentes[]`: proveedor, producto, variable, unidad, resolución nominal, atribución/licencia, recuperación y procedencia. `fechaActualizacion=null` si el proveedor no publica ese instante para la serie. El manifiesto del ejemplo añade URLs públicas y hashes para auditarlo.
- `resumen`: total de muestras, con índice, sin índice, con SPI ≤ -1 y mínimo SPI; mínimo null sin índices válidos. `totalMuestras=conIndice+sinIndice`. Resumen siempre del conjunto filtrado, sin porcentajes de superficie ni promedio de SPI.
- `cobertura`: municipios en filtro, con muestra y con índice, celdas distintas y `superficieEvaluadaKm2=null`. `estadoEspacial=muestras_puntuales` aun con todas las muestras válidas. Tener índice en una muestra no acredita cobertura de todo su municipio.
- `advertencias[]`: falta de representatividad, muestras sin serie/base, celdas repetidas, carácter experimental y cualquier limitación. No usar un `[]` de datos para ocultar fallos del proveedor.
- `paginacion.aplica=false`, máximo una muestra por cada una de las 56 unidades; ordenar por ID de muestra. Se entrega el conjunto acotado completo o se informa error, sin truncar silenciosamente.
- `representacionMapa`: Point, conjunto completo, `rellenarPoligonos=false`. El marcador se dibuja en la coordenada de celda del clima; el contexto municipal y coordenada solicitada se conservan en el detalle.

Ningún índice, categoría o resumen lo recalcula Flutter/JS. Mostrar SPI a dos decimales **después** de recibir su categoría: `-0,9999` es normal aunque al redondear se vea `-1,00`. El usuario debe poder ver el valor con mayor precisión y sus umbrales en el detalle para evitar ambigüedad.

## Faltantes y errores propuestos

Datos/servicio válidos con una muestra sin cálculo: HTTP 200, `spi.valor/categoria=null`, motivo `BASE_INCOMPLETA`, `PERIODO_INCOMPLETO`, `BASE_NO_AJUSTABLE`, `AJUSTE_FALLIDO` o `PROBABILIDAD_EXTREMA`. Las demás muestras siguen visibles y el resumen cuenta las no calculables. Un cero conocido de lluvia se conserva. La falta de índice nunca se etiqueta "normal" ni "sin sequía".

Error de solicitud/proveedor: reutilizar el sobre M2 `error={codigo,mensaje,identificadorConsulta,campos[]}`. 422 `FILTROS_INVALIDOS` / `FILTROS_INCOMPATIBLES`; 404 territorio o muestra inexistente; 429 límite local (30/min/IP, Retry-After); 503 `FUENTE_NO_DISPONIBLE`, `PERIODO_NO_DISPONIBLE`, `MUESTRAS_NO_DISPONIBLES`, `DATOS_INVALIDOS` o `MODULO_NO_DISPONIBLE`; 500 inesperado sin trazas. No inventar un 200 vacío ni cambiar a simulación ante HTTP 429/5xx/timeout/HTML externo. Con cero muestras registradas para el filtro, 503 `MUESTRAS_NO_DISPONIBLES`; con algunas, respuesta parcial y conteos explícitos.

## Política operativa candidata para #36

Solo backend consulta el host fijo `archive-api.open-meteo.com` por HTTPS, sin URL libre ni clave NASA. Comprobar calendario, unidades, celda consistente entre base y objetivo, límites de tamaño y respuesta antes de calcular. Captura científica usa 60 s/8 MiB; la implementación debe separar cargas históricas del tiempo de respuesta al usuario.

Preparar base por celda **fuera de la petición interactiva**, almacenar respuestas y hash; precalcular parámetros de los doce meses con 30 ventanas completas por mes. Reutilizar una celda para muestras coincidentes. Versión de caché incluye método, muestra/celda, parámetros del proveedor, hash de base y captura objetivo; no solo municipio/fecha. Una nueva revisión de base invalida sus parámetros/derivados juntos. No descargar 30 años por visita ni lanzar 56 consultas históricas simultáneas.

Política propuesta: base persistida e inmutable por versión, renovación explícita y auditable; objetivo histórico en caché 24 h con instante original. No entregar caché vencida como fresca si falla el proveedor. Si la base no está preparada, 503 con instrucción de preparar datos, no una petición web larga. Sin paginación, no se reutiliza el snapshot de 15 minutos de focos. Los límites globales del proveedor son independientes del límite local y requieren limitar/consolidar cargas en backend.

## Qué revisar antes de adoptar

Jaziel: filtros mensuales, códigos de faltantes/errores, unidad, procedencia, mapa puntual y forma de consumirlo sin copiar cálculo. Josué: portabilidad numérica a PHP (tolerancia 1e-6), calendario completo, parámetros y estado de caché. Revisión explícita del método en #35 antes de implementar #36 y ampliar datos más allá del piloto.
