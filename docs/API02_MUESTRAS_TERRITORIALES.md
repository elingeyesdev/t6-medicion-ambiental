# API-02 / #36: catálogo candidato y consulta por muestra

Ya funciona el catálogo de **56 puntos de solicitud**, con filtros territoriales y captura por identificador. El método #35 fue aprobado el 25/10/2026; la [API #36](API02_API_SEQUIAS.md) usa este catálogo y publica solo muestras con datos verificados, con cobertura parcial explícita. La implementación #43 requiere revisión y los clientes siguen pendientes; registrar 56 puntos no calcula 56 índices.

## Probar ahora

En PowerShell:

```powershell
cd C:\Users\cjosu\Desktop\GeoCruz-oficial\tmp\api-sequias
.\herramientas\sequias\probar-muestras.ps1
```

Debe mostrar **56 puntos**, **5** al filtrar Andrés Ibáñez (`BO0701`) y **1**, `scz-piloto-01`, para Santa Cruz de la Sierra (`BO070101`). El piloto conserva longitud **−63,1821**, latitud **−17,7833**. Esta prueba no necesita Internet: muestra celda e índice null porque listar coordenadas no consulta lluvia ni calcula sequía.

Para comprobar además una captura registrada:

```powershell
.\herramientas\sequias\probar-muestras.ps1 -ConsultarPiloto
```

Solo consulta el piloto, reutilizando una captura reciente si existe; necesita Internet si hay que obtener otra. Debe conservar **BO070101 como municipio de referencia**, aunque el centro de la celda recibida en el piloto esté en **BO070103 (Porongo)**. La prueba verificada tuvo 92 días completos de julio–septiembre de 2024 y reutilizó la captura guardada. No se trasladó el municipio del usuario al centro de celda.

## Comandos y filtros

Desde `web/`:

```powershell
..\herramientas\php.ps1 artisan sequias:muestras --provincia=BO0701
..\herramientas\php.ps1 artisan sequias:muestras --municipio=BO070101
..\herramientas\php.ps1 artisan sequias:muestras --muestra=scz-piloto-01 --geojson
..\herramientas\php.ps1 artisan sequias:capturar-muestra scz-piloto-01 --desde=2024-07-01 --hasta=2024-09-30
```

Con PHP configurado se usa `php artisan`. El listado/exportación solo lee el catálogo. Salida 0: operación correcta; 2: catálogo/filtro inválido. La captura admite `--provincia`, `--municipio` y `--renovar`; no acepta coordenadas manuales: obtiene las del registro y rechaza una muestra ajena al filtro **antes de HTTP**. Salidas de captura: 0 completa, 1 incompleta conservada con informe, 2 error.

Una muestra sola selecciona un punto, pero no cambia implícitamente el universo territorial del listado. El resumen distingue `municipiosEnTerritorio`, `muestrasEnTerritorio` y `muestrasSeleccionadas`; filtrar una muestra en una provincia no se presenta como falta de puntos del resto. Se informa cero consultas/cálculos **en esa operación de listado**, sin afirmar que nunca se haya consultado antes una muestra.

La captura por ID devuelve `muestraCatalogada` con PCODE/nombres, punto solicitado y versiones/hashes territoriales; conserva aparte `municipioDelCentroCelda` y motivo de asignación del centro. Son contextos de punto y centro, no propiedad de toda la celda. Se mantiene la política de caducidad, recuperación original y versiones del [conector de lluvia](API02_CAPTURA_LLUVIA.md).

## Cómo se prepara y verifica

El [recurso compartido](../compartido/sequias/README.md) fija versión, fuente y hashes de los dos archivos territoriales existentes. Los otros 55 puntos se seleccionan dentro de sus geometrías; el piloto anterior se conserva. Se verifican huecos, bordes, redondeo y pertenencia exclusiva; no se usa un centroide sin comprobación ni se ajustan los límites.

Laravel valida al cargar el registro: versión/CRS/orden de coordenadas, hashes de fuentes locales, cantidad y unicidad, jerarquía provincial, IDs, coordenadas finitas con hasta seis decimales y pertenencia al municipio declarado. Si algo no coincide, devuelve `CATALOGO_MUESTRAS_INVALIDO`; no corrige o sustituye puntos silenciosamente. El catálogo se conserva en memoria durante esa instancia del servicio.

Organización:

- `compartido/sequias/muestras.json`: datos candidatos comunes, sin lluvia, celdas ni índices.
- `herramientas/sequias/preparar-muestras.py`: generador reproducible; dependencias de desarrollo fijadas y pruebas Python separadas del servidor.
- `CatalogoMuestrasSequias`: carga, validación, filtros y exportación GeoJSON.
- `CapturaMuestraCatalogada`: une el punto registrado al conector de captura y conserva el contexto del centro de celda.
- `sequias:muestras` y `sequias:capturar-muestra`: comandos del flujo.
- `GeometriaTerritorial::interiorEstricto`: excluye bordes/huecos para muestras. El método `contiene` utilizado por focos conserva su comportamiento anterior en bordes.

Desde `web/`, pruebas PHP:

```powershell
..\herramientas\php.ps1 artisan test --compact --filter=Muestra
..\herramientas\php.ps1 artisan test --compact
..\herramientas\php.ps1 vendor/bin/pint --test
```

Las pruebas Python del generador se ejecutan según el README compartido. CI añade su reproducción en Linux y la demostración de listado de PowerShell en Windows, sin consultar Open-Meteo. [Evidencia de la prueba registrada](evidencias/api02-muestras/README.md).

Verificación local de este avance: **32 pruebas PHP nuevas / 343 aserciones**, **4 pruebas Python** y demostración PowerShell aprobadas. Suite completa: **185 pruebas PHP / 1.246 aserciones**, formato aprobado. Los casos incluyen puntos fuera del municipio, duplicados, fuentes/versiones incoherentes, filtros incompatibles y preservación del contexto cuando el centro ERA5 pertenece a otro municipio.

## Lo que falta

Los puntos son candidatos de solicitud, no estaciones ni una validación espacial del clima. Solo el piloto tiene captura comprobada en esta evidencia; los demás requieren recuperar historia suficiente y contrastar celdas/resultados. No se infieren 56 celdas únicas ni superficie cubierta por tener 56 puntos.

El método #35 ya está acordado y las operaciones HTTP se incorporan en [#36 / PR #43](API02_API_SEQUIAS.md). Quedan revisión de implementación y clientes. El catálogo por sí solo no acredita cobertura climática; el piloto es la única historia comprobada en la evidencia actual.
