# Caché candidata: cálculo nuevo y reutilización del piloto

`calculo-nuevo.json` y `calculo-reutilizado.json` son salidas reales consecutivas de `sequias:ensayar-spi`, primero con `--recalcular`, luego con `--cache`. Cálculo realizado **24/10/2026 03:39:41.241620 UTC (23/10 23:39 Bolivia)**. No hubo una descarga nueva.

Ambas salidas conservan huella `6fd5349301de84a9f14efaf2d2cbb27ae5a95897874c80ccdfd58b160639009c`, motor, fechas de cálculo/caducidad, versiones y resultado. La primera indica `calculo_php / esCalculoNuevo=true`; la segunda `cache_calculo / false`. Caducidad técnica: 25/10/2026 03:39:41.241620 UTC.

Entradas releídas y verificadas: base `7642d10d…51dd79`, recuperada 23/10/2026 02:56:32.946304 UTC; objetivo `195010a0…bb723`, recuperado 23/10/2026 02:56:34.271956 UTC. IDs, hashes, fechas y URLs completos en los JSON. Son las mismas capturas históricas conservadas para [el ensayo PHP](../api02-spi/README.md); no se duplican los cuerpos del proveedor.

Julio–septiembre de 2024: **114,5 mm**, **SPI −0,6410480784168531 / normal**, 30 ventanas de referencia completas. Coordenada solicitada `[-63.1821,-17.7833]`, celda `[-63.25,-17.75]`: una celda de reanálisis, sin promedio ni superficie municipal.

Estado `ensayo_pendiente_revision` en ambos casos. La reutilización demuestra conservación del cálculo y trazabilidad, no aceptación científica de #35, exactitud local del clima ni operación de la API pública. [Guía y reproducción](../../API02_CACHE_CALCULO.md).
