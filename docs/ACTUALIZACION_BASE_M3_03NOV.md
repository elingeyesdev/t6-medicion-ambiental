# Actualización del candidato M3 · 03/11/2026

Actualicé el borrador #96 con develop después de las aprobaciones de Jaziel. Refs #41. No se publica M3 en main ni se da por aceptada la entrega.

## Cortes incorporados

- Candidato de partida: `7925c3b9bf061717fb28556b32d04d2dc13b09f8`. Ya contiene los PR #98, #100 y #102, aprobados e integrados por Jazielle.
- Develop incorporado: `1313e04d050ee2f0c42ef3a080b0559248593513`. Incluye #88, revisado e integrado, y conserva las correcciones de Android y sequías de Jaziel.
- Main se mantiene en M2: `851a79f5c699249c284001114f76f994c9ce4335`.

## Conflictos resueltos

- `docs/RASTER_ETL66.md`: conservé la actualización de #99 y sus límites; ya existe actualización automática local y eso no acredita histórico completo ni AQI raster.
- `herramientas/datos/raster/tests/publicador_real.php`: conservé las clases del publicador y bloqueo que añadió #99.
- `web/bootstrap/app.php`: conservé el manejo de errores del mapa y el de avisos/cuenta. Ninguno reemplaza al otro.

El resultado conserva exactamente los árboles de `web/` y `herramientas/` del candidato de partida. Las ocho diferencias recibidas desde develop corresponden a la integración revisada de #88: cinco archivos Flutter y tres evidencias. No escribí código móvil.

## Comprobación de este corte

- **107 pruebas JavaScript correctas**, cero fallos y cero omitidas. [Salida completa](evidencias/actualizacion96-20261103/javascript.txt).
- Índice sin archivos pendientes de conflicto. Comparación del código web y herramientas con el padre: sin diferencias.
- Huella del árbol `web/`, antes y después: `ae71b0e47cc3722bd7c6a02e42c5c450d33f77ac`.
- Huella de `herramientas/`, antes y después: `2dc4ac7233b64dd64495608b0a47da727502b3ef`.
- No se ejecutó PHPUnit ni se recompiló Flutter para esta actualización. Sus resultados anteriores conservan su propio commit; las comprobaciones de GitHub del siguiente commit deben revisarse aparte.

## Lo que sigue pendiente

#96 sigue como borrador. Los PR web posteriores (#104, #106, #108, #121, #125, #128, #129, #133, #134 y #135) conservan sus revisiones y dependencias; no quedan integrados por esta actualización. #94 mantiene su revisión separada aunque comparta la resolución del arranque. #120 sigue sobre #46, pendiente de la revisión del método.

Jaziel debe revisar lo que falta y después repetimos el recorrido conjunto. M3 y #41 siguen abiertos; no hay una nueva fecha académica confirmada ni autorización para llevar M3 a main.
