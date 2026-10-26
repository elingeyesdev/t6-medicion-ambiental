# API de sequías — sequias-1.0-m3-candidata

Método #35 aprobado y fusionado por Jaziel en [PR #42](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/42), el 25/10/2026. Implementación del #36 / PR #43 sujeta a revisión cruzada. El identificador científico `spi3-era5-gamma-mle-1991-2020-v0.1-propuesta` se conserva para identificar exactamente las fórmulas acordadas; la versión HTTP operativa es distinta. No equivale a aceptación académica ni autorización de `main`.

[OpenAPI 3.1](openapi.json), [filtros](consulta.schema.json), [respuesta de consulta/detalle](respuesta.schema.json), [resumen](resumen.schema.json). El contrato de focos `../openapi.json` se conserva.

## Consultas GET

- `/api/v1/sequias?mesFinal=2024-09&municipio=BO070101&muestra=scz-piloto-01`: conjunto preparado, con datos, detalle, resumen y procedencia.
- `/api/v1/sequias/scz-piloto-01?mesFinal=2024-09`: detalle de una muestra; admite provincia/municipio compatibles. Si también se envía `muestra`, debe coincidir con la ruta.
- `/api/v1/sequias/resumen?mesFinal=2024-09&provincia=BO0701`: mismo cálculo del conjunto; omite `datos`, `paginacion` y `representacionMapa`.

Filtros: `mesFinal` obligatorio AAAA-MM, desde 2021-01 y anterior al mes UTC actual; `provincia`, `municipio` y `muestra` opcionales. Sin filtro territorial selecciona los 56 puntos registrados de BO07. No acepta `desde`, `hasta`, fechas diarias, coordenadas libres, `modo`, parámetros desconocidos/repetidos, arrays o vacíos. Cada consulta exige resultados preparados para el período; una visita no descarga datos ni ajusta Gamma.

## Datos y representación

Los nueve campos de la propuesta #35 se mantienen en consulta/detalle. `consulta.estado=metodo_acordado`; `obtencion.tipo=cache`, `esRespuestaHttp=true`. `obtenidoEn` es la recuperación más antigua de los objetivos incluidos, `preparadoEn` la preparación más antigua, y `vigenteHasta` el primer vencimiento del conjunto. Las fechas y hashes exactos de cada base/objetivo se conservan en `fuentes[].capturas`, con ID de versión, URL, período y muestra de origen. `fechaActualizacion=null`: el proveedor no acredita ese instante por serie.

`spi` procede íntegro del servidor: valor, categoría, motivo, años válidos, Gamma y probabilidad. No recalcular en Flutter/JS. Redondear para presentar después de clasificar; permitir ver más precisión en el detalle. Una base/período incompleto, base constante/no ajustable, ajuste fallido o probabilidad extrema conserva valor/categoría null y motivo explícito; nunca se transforma en normal.

El marcador se dibuja en `coordenadaCelda`, manteniendo `coordenadaSolicitada` y `territorioContexto` del usuario. `municipioDelCentroCelda` puede diferir o ser null. `representacionMapa` exige puntos y `rellenarPoligonos=false`; polígonos solo como contexto. No es un promedio municipal, una estación ni superficie afectada.

## Cobertura parcial explícita

El catálogo registra 56 puntos; esto no acredita 56 series disponibles. `datos` incluye solo muestras con publicaciones verificadas y vigentes. Las no preparadas se enumeran en `cobertura.muestrasSinPreparar`, con `estadoDatos=parcial` y advertencia. Si ninguna está preparada, 503 `MUESTRAS_NO_DISPONIBLES`, nunca 200 vacío. Un resultado presente pero corrupto o vencido hace fallar la consulta; no se omite para simular cobertura válida.

`muestrasSeleccionadas = resumen.totalMuestras + len(muestrasSinPreparar)`. `totalMuestras = muestrasConIndice + muestrasSinIndice`: estas últimas sí tienen una publicación válida con SPI null/motivo. `municipiosConMuestra` cuenta puntos registrados seleccionados, `municipiosConDatos` los incluidos en la respuesta y `municipiosConIndice` los que tienen SPI. `celdasUnicas` permite detectar dependencia entre muestras. `superficieEvaluadaKm2` siempre null. No inferir porcentajes de área o SPI medio territorial.

## Preparación y vigencia

Solo consola obtiene datos de Open-Meteo/ERA5, conserva los originales y prepara los doce ajustes 1991–2020 por base/celda/motor. Bases persistentes por versión, con renovación explícita; una misma celda reutiliza su base y ajustes entre puntos, sin cambiar la procedencia de cada entrada. El servidor rechaza mezclar celdas, fuente, producto o naturaleza. Cambiar base/hash/método/código genera otra versión; la consulta rechaza una publicación del motor/catálogo anterior.

Objetivo finito: máximo 24 h desde su recuperación; objetivo null: 15 min. Recalcular/republicar no renueva esa fecha. Al vencer, 503 `PERIODO_NO_DISPONIBLE`; renovar por consola, sin devolver el dato vencido como fresco. Los originales y publicaciones anteriores se conservan con hashes. Se verifica integridad de originales en cada consulta, sin descargar historia ni recalcular el SPI durante HTTP.

Una carga de proveedor por vez en el preparador operativo; destinos HTTPS y parámetros fijos, límites de tiempo/tamaño y errores sin simulación. La descarga histórica exige `--capturar-base` si no hay base compartida o un `--base` local. La publicación ocurre al terminar; las versiones previas permanecen si falla la nueva carga. No hay renovación automática ni promesa de disponibilidad continua.

## Errores y consumo móvil

Sobre: `error={codigo,mensaje,identificadorConsulta,campos[]}`. 422 filtros inválidos/incompatibles; 404 territorio o muestra inexistente; 405 método no admitido; 429 límite común 30/min/IP con `Retry-After`; 503 muestras/período no disponibles o datos inválidos; 500 inesperado sin trazas. `Cache-Control: no-store` impide que el navegador o un proxy prolongue la publicación.

Para Flutter en emulador Android, usar la base configurable del backend, normalmente `http://10.0.2.2:5789/api/v1`; `127.0.0.1` dentro del emulador apunta al propio emulador. En teléfono físico se requiere dirección LAN y configuración de red apropiada. No copiar la clave NASA ni acceder al proveedor desde Flutter. Este cambio no configura dispositivos ni abre reglas de firewall.

[Arranque y prueba del piloto](../../../docs/API02_API_SEQUIAS.md), [respuestas HTTP reales conservadas](../../../docs/evidencias/api02-http/README.md). En el corte documentado solo el piloto de septiembre 2024 está preparado: la consulta departamental devuelve 1 muestra y enumera 55 pendientes.
