# API-01 · Focos de calor y contrato compartido

Issue #18, M2. Responsable: Josué Padilla; revisión de Jaziel pendiente. Preparación y verificación: 16/10/2026. Rama `feature/18-api-focos-calor`.

## Resultado y dependencias

Se implementan catálogo, límites y consulta NASA FIRMS VIIRS_SNPP_NRT bajo `/api/v1`, con el contrato candidato `1.0-m2-candidata`, filtros territoriales/UTC, detalle, resumen del conjunto completo, paginación coherente e identificación de caché. El mapa recibe explícitamente una página; la pantalla web pertenece a #19 y Flutter a #20. No hay simulaciones de reemplazo ni registro de aprobación del compañero.

La rama incorpora las bases pendientes de los PR #23 (#15) y #24 (#16) **en esta rama de trabajo**, manteniendo `develop` sin esos cambios hasta su revisión. El PR de #18 depende de ambos y puede incluir sus archivos mientras no se integren. Después de la aprobación de esas bases se sincroniza esta rama con `develop` y se revisa el diff restante antes de fusionar. No se aprueban ni cierran las tareas dependientes por esta preparación.

## Prueba externa real desde Laravel

`GET http://127.0.0.1:8002/api/v1/focos-calor?desde=2026-10-10T00:00:00Z&hasta=2026-10-11T00:00:00Z&tamanioPagina=500`

Captura HTTP del backend: **2026-10-16T05:30:26Z**. Respuesta 200 de consulta externa nueva, **60 registros departamentales**, 1 de confianza alta, FRP máxima **6.31 MW**. Se excluyeron 24 registros por filtros. Fuente: NASA FIRMS, producto VIIRS_SNPP_NRT, versión informada 2.0NRT. El período es histórico y no representa el estado ambiental actual. Los resultados pueden cambiar en una nueva descarga del producto NRT.

La respuesta completa está en [consulta_real.json](evidencias/api01/consulta_real.json); incluye las fechas de recuperación y adquisición, disponibilidad temporal publicada, los IDs territoriales candidatos, faltantes, cobertura y advertencias. `cobertura=desconocida` no contradice los 60 registros: la muestra no acredita observación completa de cada lugar. No se comparan estos resultados con la muestra de DOC-13 como si usaran la misma geometría o período.

Se verificaron por HTTP catálogo 200, límite 200, provincia-municipio incompatibles 422, confianza alta 200 (1 registro) y otro módulo 503. [Registro HTTP](evidencias/api01/http.json). La MAP_KEY está únicamente en `web/.env` local y no se conserva en evidencia, URLs públicas, Git ni capturas. Las pruebas automáticas usan una clave ficticia y respuestas controladas.

## Verificación reproducible

- Suite Laravel: 33 pruebas, 248 aserciones; casos de vacío, FRP cero/nulo, faltantes, coordenadas/fechas inválidas, duplicados y conflictos, polígonos con huecos y bordes compartidos, jerarquía, fechas con offset, bloques de cinco días, paginación/expiración, proveedor caído, respuesta HTML/CSV inválido, configuración/catálogo ausente y errores HTTP. No llama a Internet.
- Contrato OpenAPI y formatos: las respuestas reales congeladas de focos, catálogo y límite se validan contra sus esquemas JSON Schema. Herramienta `compartido/api/validar_respuestas.py`; requiere `jsonschema==4.25.1` solo para verificar.
- Formato PHP con Laravel Pint y verificación del catálogo heredado de #16. Los resultados se guardan en esta carpeta de evidencia; el estado de GitHub Actions se comprueba por separado en el PR.
- Contraste independiente de las 60 coordenadas y asignaciones municipales con Shapely, sin duplicados y con FRP máxima coherente: `compartido/api/contrastar_geografia.py`, usando los requisitos geométricos de #16. Resultado en `evidencias/api01/contraste_geografico.txt`.

## Dónde probar y qué observar

Para una explicación de dos minutos solicitada en la revisión de #18: [demostración sencilla de NASA](API01_DEMOSTRACION_NASA.md). Ejecutar `./demostrar-api.ps1 -FechaUtc 2026-10-17 -Confianza nominal` desde la raíz de esta copia muestra fuente, período, resumen y una detección real en la terminal, sin revelar la clave. Se agregó evidencia nueva del 18/10, separada de la muestra histórica del 16/10.

Abrir PowerShell en `C:\Users\cjosu\Desktop\GeoCruz-oficial\tmp\api-focos` y ejecutar `./iniciar-api.ps1`. El servidor queda en **http://127.0.0.1:8002**. Detener con Ctrl+C. En una copia nueva, ejecutar antes `preparar.ps1` y configurar la propia clave en `web/.env`.

1. `/api/v1/territorios?tipo=provincia` devuelve 15 provincias; `/api/v1/territorios?tipo=municipio` devuelve 56 unidades de nivel municipal del catálogo candidato.
2. La URL de focos de la prueba externa devuelve JSON con `datos`, `resumen`, `cobertura`, fechas, fuente y `paginacion`.
3. Añadir `confianza=alta`: todos los datos devueltos tienen esa confianza y el resumen corresponde a ese conjunto.
4. Añadir `provincia=BO0702&municipio=BO070101`: devuelve 422 `FILTROS_INCOMPATIBLES`.
5. Con `tamanioPagina=1`, guardar `paginacion.versionConjunto`; enviarlo en la siguiente URL como `versionConjunto=...&pagina=2`, conservando fechas y filtros. Se obtiene otra página con el mismo total, resumen y recuperación original, tipo `cache`.

El navegador muestra JSON. Para una presentación legible, activar su casilla de formato. Las instrucciones completas y reglas de clientes están en [contrato compartido](../compartido/api/README.md).

## Pendientes de aceptación

Jaziel registró una revisión preliminar el 17/10 en #18: comprobó el alcance y las pruebas, y pidió una demostración sencilla de la consulta real antes de aprobar. La guía y el comando anteriores responden a esa solicitud. No hay una aprobación final registrada por esta preparación.

Jaziel debe revisar el PR, el uso de PCODE/límites de #16, la política conservadora de fronteras, el máximo de 168 horas y la retención/parametrización de `versionConjunto`. Se documenta una implementación candidata; las decisiones no se presentan como acuerdo del equipo. La integración en web, Flutter y la revisión conjunta siguen sus propios issues. El proveedor puede corregir NRT y la retención del snapshot es temporal; no se implementa una base histórica.
