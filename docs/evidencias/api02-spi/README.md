# Contraste PHP del candidato SPI-3

[`piloto-php.json`](piloto-php.json) es la salida real de `sequias:ensayar-spi` al releer las dos versiones locales con integridad verificada. Ejecución: 23/10/2026 en Bolivia. No se descargaron datos otra vez: las capturas de Open-Meteo/ERA5 son del 23/10/2026 02:56 UTC (22/10 en Bolivia), conservadas en el commit `1d1865f` del [PR #42](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/42).

La salida incluye IDs locales y declaraciones exactas, hashes originales, URLs, fechas, coordenadas, 30 acumulados y resultado. La muestra solicitada es `[-63.1821,-17.7833]`; centro de celda `[-63.25,-17.75]`. Según la comprobación territorial de #35, el primero está en BO070101 y el segundo en BO070103: no se traslada el municipio del usuario al centro de celda.

Julio–septiembre de 2024: **114,5 mm**, Gamma forma **7,475930234113382**, escala **20,548613375097762 mm**, probabilidad **0,26074572324913725**, SPI **−0,6410480784168531**, categoría **normal**. Frente a SciPy: diferencia absoluta del índice menor que **3e-15** en esta ejecución, dentro de la tolerancia adoptada para contraste de 1e-6. No es una garantía de esa precisión para todas las series.

El método permanece propuesto y pendiente de revisión. La coincidencia numérica no valida un SPI oficial, la exactitud climática local ni un municipio entero. Los casos artificiales del oráculo y las series generadas por pruebas son fixtures controladas, no capturas externas.

Reproducción y límites: [API02_ENSAYO_SPI.md](../../API02_ENSAYO_SPI.md). El cálculo no escribe índices en el almacén, no realiza solicitudes HTTP y no habilita la ruta pública de sequías.
