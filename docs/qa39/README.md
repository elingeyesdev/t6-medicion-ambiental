# QA-02 — Verificación de cálculo e integración de sequías (#39)

**Ejecución:** 29 de octubre de 2026, después del commit `a8e2678`.  
**Quién midió:** Jaziel Díaz (`Jazielle`), sobre la API local `http://127.0.0.1:8002` con PHP 8.2.12.  
**Contrato:** `sequias-1.0-m3-candidata`. Método `spi3-era5-gamma-mle-1991-2020-v0.1-propuesta`.  
**Depende de:** #35 MET-01, #36 API-02, #37 WEB-02 y #38 MOV-04.

Josué Padilla dejó en [#39](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/39#issuecomment-6489353903) que aprobar #84 y #87 no cierra esta comparación. Esta carpeta es el recorrido repetido que pidió. Su revisión de estos resultados sigue pendiente antes de cerrar la tarea.

## Matriz del mismo mes y la misma muestra

Consulta real: `GET /api/v1/sequias?mesFinal=2024-09`. La respuesta guardada está en [api-2024-09.json](resultados/api-2024-09.json). El cliente web (`comprobarRespuesta`) aceptó ese JSON sin cambiar cifras. Flutter muestra el SPI con dos decimales y el resto de campos desde el mismo contrato.

| Parámetro | MET-01 / API | Web y móvil sobre esta respuesta |
| :--- | :--- | :--- |
| Identificador | `scz-piloto-01` | `scz-piloto-01` |
| Territorio solicitado | Municipio de la muestra | `BO070101` |
| Centro de la celda | Puede caer en otro municipio | `BO070103`. La API avisa y no lo sustituye por el municipio pedido. El detalle móvil lo rotula como discrepancia; el detalle web muestra los dos nombres. |
| Coordenadas | Pedida y celda ERA5 | `[-63.1821, -17.7833]` y `[-63.25, -17.75]` |
| Período | Mes final y ventana de 3 meses | `2024-09`, del `2024-07-01` al `2024-09-30` UTC |
| Referencia | 1991–2020 | `1991-01-01` a `2020-12-31`, 30 años válidos |
| Fuente | Open-Meteo ERA5, `precipitation_sum` | `open_meteo_era5`, resolución 0.25° |
| Unidad e indicador | mm y SPI-3 | `114.5 mm`, SPI `−0.6413769540120462`, categoría `normal`. El móvil muestra `−0.64`. |
| Faltantes | No inventar ceros ni “normal” | 55 `muestrasSinPreparar`. La muestra preparada tiene `motivo: null`. |
| Cobertura | Muestras puntuales, no superficie | `parcial`, 1 de 56 municipios con datos |
| Resumen | Conteos de muestras | total 1, con índice 1, sin índice 0, con déficit 0 |

## Consulta real y respuestas controladas

Límites pedidos a la API real, en [limites.json](resultados/limites.json):

- Mes `2024-13`, `invalido`, `2020-12` y `2026-10`: HTTP 422 `FILTROS_INVALIDOS`.
- Cotoca `BO070102` en 2024-09: HTTP 503 `MUESTRAS_NO_DISPONIBLES`.
- Muestra `no-existe`: HTTP 404 `MUESTRA_NO_DISPONIBLE`.
- Provincia `BO0702` con municipio `BO070101`: HTTP 422 `FILTROS_INCOMPATIBLES`.

La serie insuficiente, el nulo que no se vuelve cero ni “normal”, el fallo con reintento y la respuesta tardía no se simularon contra Open-Meteo. Salieron de pruebas con transporte controlado:

- Web: `node --test web/tests/Js/sequias.test.mjs` — 8 pruebas, 0 fallos. Incluye calendario inválido, SPI null, publicación vencida, dos respuestas fuera de orden y reintento conservando el resultado anterior.
- Móvil: `flutter test test/drought_repository_test.dart test/drought_review_test.dart` — 10 pruebas, 0 fallos. Incluye null con motivo, respuesta tardía y cobertura parcial.
- Motor PHP contra el oráculo SciPy congelado: `Spi3NumericoTest` — 15 pruebas, 208 aserciones, 0 fallos.

## Contraste con una referencia que no es la interfaz

SciPy 1.18.1 ajustó Gamma por MLE sobre las 30 ventanas de la publicación vigente, no sobre la salida de Blade ni de Flutter. El SPI de referencia es `−0.6413769540120433`. Laravel entrega `−0.6413769540120462`. La diferencia absoluta es `2.9×10⁻¹⁵`. Detalle en [referencia-scipy.json](resultados/referencia-scipy.json).

Ese número no es el del oráculo congelado (`−0.6410480784`). El oráculo usa la captura de MET-01, cuya ventana de 1991 es 234.8 mm. La publicación local del 2026-10-29 tiene 233.5 mm en esa ventana. Las dos comparaciones se conservan por separado y las dos coinciden con SciPy en su propia entrada.

## Recorrido

- Teléfono, sequías de septiembre 2024, después de esperar a que el servidor quedara libre: [movil-sequias-2024-09.png](evidencias/movil-sequias-2024-09.png). Se ve el mes `2024-09`, la ventana de 3 meses ERA5, el resumen 1 / 1 / 0 / 0 y la cobertura parcial de 1 de 56 municipios.
- Teléfono, focos antes de la corrección: [movil-focos-error-antes.png](evidencias/movil-focos-error-antes.png). El texto es el que devuelve la API cuando PHP agota los 60 segundos.
- Focos después de la corrección, misma API local: HTTP 200 en 8.3 s, 3381 detecciones, cobertura parcial. Registro en [focos.json](resultados/focos.json). Las pruebas Flutter de focos (`hotspot_repository_test.dart`) pasaron 8/8, incluido período inválido, respuesta tardía y FRP en cero.

La página web `/modulos/sequias` exige sesión. El parser que usa esa página aceptó la misma respuesta que el teléfono. Falta la captura de Josué, o de esta cuenta con la sesión abierta, en `mesFinal=2024-09`.

## Defecto corregido en esta rama

`php -S` atiende una petición a la vez. Ubicar cada foco contra las 19 415 aristas del departamento superaba los 60 segundos y la API respondía «No se pudo completar la consulta». Sequías quedaba detrás y parecía tardar unos 4 minutos. Ahora la misma regla geométrica consulta solo las aristas cercanas al punto. Sequías de 2024-09 respondió en 3.2 s y focos de 7 días en menos de 9 s.

## Participación

- Jaziel Díaz: recorrido de esta comparación, evidencia de esta carpeta y corrección del tiempo de la consulta territorial.
- Josué Padilla: API, visor web y correcciones de #38 en `a8e2678`. En el comentario del 29/10 indicó que #39 sigue abierta hasta revisar este recorrido repetido. Ese visto bueno todavía no está.
