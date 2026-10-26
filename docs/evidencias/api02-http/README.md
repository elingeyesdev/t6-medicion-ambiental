# API de sequías: evidencia HTTP real del piloto

Captura objetivo nueva realizada el **25/10/2026 18:04:08.846370 UTC (14:04 Bolivia)** desde Open-Meteo/ERA5, con el conector Laravel y sus parámetros fijos. `objetivo-original.json` conserva los bytes recibidos, SHA-256 `a13f643eb39edb7a3e7edcef6aad0f8dab205cad6d10ed66497a5b8a61b6bf21`, 92 días de julio–septiembre 2024. La versión local objetivo es `68870d9ba7fa4cd7e093b971fb83e4fec68d0b0243b929e22e872a41e4b51f1e`.

La base no se descargó otra vez: original `5408b175da7efb0b6e896c10cd567f8b676181e9ecde4860c7602f385c7e3199`, recuperado el 23/10/2026 02:56:32.946304 UTC, conservado en `docs/evidencias/met35/fuente-20261022/base.json` del PR #42. `preparacion.json` registra originales, publicación, catálogo, motor y clave de los doce ajustes preparados. Las preparaciones posteriores reutilizaron estos mismos originales, sin cambiar su recuperación.

`consulta.json`, `detalle.json`, `resumen.json` y `departamento-parcial.json` son **cuerpos HTTP 200 recibidos del servidor Laravel local**, no respuestas fabricadas por un generador. `manifest.json` conserva URL, estado, instante de recepción, Cache-Control y SHA-256 del archivo guardado (cuerpo más salto LF). Se repite la captura al finalizar la implementación para registrar el motor vigente.

Resultado: **114,5 mm**, **SPI −0,6410480784168531 / normal**. Muestra `scz-piloto-01`, punto `[-63.1821,-17.7833]`, contexto BO070101; celda `[-63.25,-17.75]`, centro BO070103. No se cambió el contexto al centro ni se evaluó superficie municipal.

Consulta, detalle y resumen coinciden; la consulta departamental devuelve una muestra y enumera **55 pendientes**, con `estadoDatos=parcial`. No se descargaron ni validaron historias climáticas de las otras 55 muestras. Las salidas indican reutilización de una publicación ya preparada, con recuperación original conservada y caducidad técnica; recibir HTTP 200 local no acredita una descarga del proveedor en cada visita.

[Arranque y reproducción](../../API02_API_SEQUIAS.md), [contrato](../../../compartido/api/sequias/README.md). La verificación offline `herramientas/sequias/test_contrato_http.py` comprueba cinco casos de contrato, integridad, coherencia y referencia sin red. Método #35 aprobado por el equipo; implementación #43 pendiente de revisión cruzada, sin promoción a `main`.
