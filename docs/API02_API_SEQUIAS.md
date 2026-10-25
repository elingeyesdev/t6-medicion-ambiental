# API-02 / #36: API de sequías con el método acordado

La rama integra `develop` después de la aprobación y fusión del método #35 / PR #42 por Jaziel el **25/10/2026**. Implementa consulta, detalle y resumen de SPI-3 en Laravel. El PR #43 queda para revisión de la implementación; `main` sigue reservado a la aprobación del ingeniero.

## Abrir y probar en este equipo

Servidor de prueba: **http://127.0.0.1:5789**. Consulta del piloto:

<http://127.0.0.1:5789/api/v1/sequias?mesFinal=2024-09&municipio=BO070101&muestra=scz-piloto-01>

Debe devolver JSON HTTP 200, **114,5 mm**, **SPI −0,6410480784 / normal**, una muestra y su procedencia. Es la ventana **julio–septiembre de 2024**, no una evaluación actual de Santa Cruz. El punto solicitado conserva BO070101 y el centro de celda BO070103. `superficieEvaluadaKm2=null`, sin rellenar polígonos.

Para arrancar el servidor si está cerrado, abrir PowerShell y mantenerlo abierto:

```powershell
cd C:\Users\cjosu\Desktop\GeoCruz-oficial\tmp\api-sequias
.\herramientas\sequias\iniciar-api.ps1
```

En otra terminal, prueba automática de las tres operaciones:

```powershell
cd C:\Users\cjosu\Desktop\GeoCruz-oficial\tmp\api-sequias
.\herramientas\sequias\probar-api.ps1
```

El script termina en 0 si consulta/detalle/resumen coinciden con la referencia. No consulta Open-Meteo. El arranque usa PHP portable con la configuración del proyecto y el router de Laravel desde `web/public`; no cambia PHP/Laragon global.

También abrir:

- `/api/v1/sequias/scz-piloto-01?mesFinal=2024-09`: detalle de la misma muestra.
- `/api/v1/sequias/resumen?mesFinal=2024-09&municipio=BO070101`: resumen del conjunto, sin repetir `datos`.
- `/api/v1/sequias?mesFinal=2024-09`: **1 muestra preparada, 55 pendientes**, con advertencia y lista de IDs; no devuelve 56 índices inventados.
- `/api/v1/sequias?mesFinal=2026-10`: 422 `FILTROS_INVALIDOS` porque octubre aún no terminó en el corte de prueba.
- `/api/v1/sequias?mesFinal=2024-09&municipio=BO070103&muestra=scz-piloto-01`: 422 `FILTROS_INCOMPATIBLES`.

La pantalla Blade de sequías se implementa en #37; por ahora estas pruebas verifican la API JSON.

## Renovar o preparar datos

La captura objetivo del piloto fue recuperada **25/10/2026 18:04:08.846370 UTC (14:04 Bolivia)**. Su vigencia técnica acaba el **26/10/2026 18:04:08.846370 UTC**. Después la API devuelve 503 `PERIODO_NO_DISPONIBLE`, sin entregar datos vencidos como frescos.

Para renovar desde la raíz, con Internet:

```powershell
cd web
..\herramientas\php.ps1 artisan sequias:preparar-consulta scz-piloto-01 2024-09 --consultar --renovar
```

El registro existente reutiliza la base verificada de esa celda. Solo se descarga el objetivo; no necesita MAP_KEY de NASA. Si la celda cambia o no existe una base local, el comando lo informa. Para una instalación vacía, después de instalar las dependencias con `preparar.ps1`, autorizar explícitamente la carga histórica:

```powershell
..\herramientas\php.ps1 artisan sequias:preparar-consulta scz-piloto-01 2024-09 --consultar --capturar-base
```

Primero resuelve el punto registrado y su objetivo; reutiliza la base por celda si existe y solo descarga noviembre 1990–diciembre 2020 si se autorizó y no hay base. Carga secuencial con bloqueo global, más bloqueos por captura/publicación. No ejecuta 56 descargas históricas simultáneas ni descarga historia durante una visita.

Para publicar desde versiones locales sin red:

```powershell
..\herramientas\php.ps1 artisan sequias:preparar-consulta scz-piloto-01 2024-09 --base=ID_BASE --objetivo=ID_OBJETIVO
```

La entrada debe ser captura externa ERA5 de la ventana exacta y del punto registrado. Verifica celda, fuente, producto, naturaleza, calendario, mm/UTC, hashes y calidad. Puede reutilizar una base obtenida desde otro punto únicamente si corresponde a la **misma celda climática**, conservando ambas procedencias. No publica fixtures/simulaciones como observaciones reales. Integridad de la declaración no es certificación independiente del proveedor.

Se preparan y conservan los doce ajustes por hash de base, celda y motor. La preparación reutiliza sus parámetros mensuales para el cálculo; una base incompleta mantiene los null/motivos acordados. La base y sus versiones no caducan automáticamente; su revisión exige renovar explícitamente. El objetivo caduca a las 24 h desde recuperación si tiene índice, a los 15 min si es null. Republicar o recalcular no rejuvenece el dato.

## Consulta, errores y almacenamiento

El controlador solo lee publicaciones preparadas. No descarga, ajusta Gamma ni escribe resultados durante HTTP. Relee los originales para comprobar su integridad y reconstruye las ventanas para contrastar el contexto; el SPI guardado no se recalcula en cada visita. Una publicación de otro catálogo/motor se rechaza y debe prepararse otra vez.

Consulta/detalle tienen nueve campos comunes; el resumen omite los tres campos de datos/mapa/paginación. El [contrato operativo](../compartido/api/sequias/README.md) especifica filtros, semántica de cobertura, fechas, errores y conexión móvil. El contrato de focos se conserva.

Las muestras sin publicación se enumeran como pendientes. Una publicación válida con faltantes aparece con SPI/categoría null y motivo: no se omite ni se etiqueta normal. Un registro presente pero corrupto/vencido hace fallar la consulta. Sin ninguna muestra preparada: 503 explícito, nunca 200 vacío. Errores mantienen `identificadorConsulta` y no exponen trazas ni cuerpos del proveedor.

Los originales permanecen en `storage/app/private/sequias/series/`. Las publicaciones/ajustes se guardan en `storage/app/private/sequias/publicaciones/`: JSON inmutable identificado por hash y un índice sustituido al finalizar. Versiones anteriores conservadas, escrituras con archivo temporal y bloqueo. Ante una carga nueva fallida permanece la publicación previa, utilizable solo mientras siga vigente. No hay fallback a simulación ni renovación automática.

## Organización del código

- `Http/Controllers/Api/SequiasController.php`: HTTP y filtros permitidos; no calcula SPI.
- `Services/Sequias/ConsultaSequias.php`: lectura verificada, detalle/resumen, cobertura, fechas y errores.
- `Services/Sequias/PreparadorConsultaSequias.php`: carga/importación explícita, base compartida por celda, doce ajustes y publicación.
- `Services/Sequias/AlmacenPublicacionesSequias.php`: versiones derivadas e índices privados.
- `Services/Sequias/Experimental/`: conserva los nombres de los componentes contrastados antes de aprobar #35. `Spi3Propuesto` mantiene el identificador científico y fórmulas revisados; HTTP usa `sequias-1.0-m3-candidata`. El ensayo offline sigue separado de la respuesta HTTP.
- `Console/Commands/PrepararConsultaSequias.php`: comando operativo y códigos 0 índice finito, 1 null publicado, 2 error.

## Evidencia y validación

[Cuatro respuestas HTTP 200 reales y manifiesto](evidencias/api02-http/README.md), objetivo original nuevo y referencia de base conservada. Las pruebas PHP generan datos controlados y simulan errores del proveedor únicamente en tests; estas fixtures no son evidencia climática externa.

Desde `web/`: `../herramientas/php.ps1 artisan test --compact` y `../herramientas/php.ps1 vendor/bin/pint --test`. Desde la raíz: `python herramientas/sequias/test_contrato_http.py`, con `jsonschema==4.25.1`. Se contrastan contrato/formatos, hashes y cobertura, referencia SPI, nueve casos numéricos con ajuste reutilizado, calendario, filtros inválidos/duplicados, 404/405/429, null, corrupción, caducidad y errores del proveedor. CI comprueba Linux PHP 8.2/8.3 y Windows; no necesita llamadas externas.

Alcance verificado: **un piloto real**. El software permite preparar otros puntos, pero no se declara historia comprobada de las otras 55 muestras. Método acordado por el equipo, implementación para revisión en #43; no implica validación climática con estaciones ni aprobación del ingeniero.
