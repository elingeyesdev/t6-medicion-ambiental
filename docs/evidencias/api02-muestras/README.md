# Prueba del catálogo y el piloto registrado

El generador preparó **56 puntos interiores exclusivos** del conjunto municipal compartido. PHP comprobó nuevamente su pertenencia y filtros; las pruebas Python reprodujeron exactamente el catálogo y verificaron casos cóncavos, huecos, multipolígonos y redondeo al borde. No se descargaron datos para los otros 55 puntos.

[`piloto-registrado.json`](piloto-registrado.json) es la salida de `sequias:capturar-muestra scz-piloto-01 --desde=2024-07-01 --hasta=2024-09-30`. Se ejecutó sobre la captura reciente ya preparada: `tipoObtencion=cache_captura`, `esConsultaNueva=false`. No es otra consulta al proveedor ni una descarga de los 56 municipios.

Conserva el ID `81c72f90d1cd1019449b274bb7e46a1071d0d651881d10170f1f2e6bd353431e`, fuente ERA5, recuperación **2026-10-24T02:39:15.585398Z**, 92 días completos, URL y SHA-256 de la [captura original de este corte](../api02-fuente/captura-objetivo.json). Los datos de HTTP descritos son los de esa descarga original. La celda fue `[-63.25,-17.75]`.

El contexto de la muestra es BO070101 (Santa Cruz de La Sierra), solicitado en `[-63.1821,-17.7833]`; el centro de celda pertenece a BO070103 (Porongo). Ambos campos se conservan y se advierte su diferencia. No se reasignó el municipio de referencia ni se trató la celda como estación o cobertura municipal.

La salida también conserva versión del catálogo de puntos, versión territorial y hashes de sus fuentes. La operación de listar puntos deja celda/SPI null; esta operación separada solo captura/reutiliza lluvia y no calcula un índice. El método continúa pendiente de revisión.
