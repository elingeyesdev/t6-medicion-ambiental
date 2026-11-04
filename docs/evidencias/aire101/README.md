# Evidencia de calidad del aire — #101

Consulta pública del centro de Santa Cruz, sin cuentas o datos privados del original. Implementación sobre #100, con revisión de Jaziel pendiente. Este avance no termina por sí solo #66 o #62.

## Captura externa real

- `proveedor-santa-cruz.json`: respuesta HTTP real de Open-Meteo/CAMS global, sin modificaciones. Incluye 25 posiciones de la solicitud y metadatos de celda/unidades.
- `consulta-normalizada.json`: respuesta real del servicio Laravel para el mismo lugar, obtenida en otra petición. Conserva las 24 horas dentro de la ventana; no devuelve la posición que ya queda fuera de las últimas 24 horas.
- `recibo.json`: endpoint, parámetros, momento de captura directa y SHA-256 de ambos archivos. Las huellas permiten comprobar los archivos; no autentican por sí solas al proveedor.
- `santa-cruz-aire.png`: ficha real con el índice, fecha, partículas y controles.
- `santa-cruz-horas.png`: recorrido horario en la misma ficha.

Dato válido: **30/10/2026 03:00 UTC**, equivalente al **29/10 a las 23:00 en Bolivia**. Índice europeo **47**; partículas PM2,5 **17,3 μg/m³**, PM10 **18 μg/m³**. Se conservan las otras variables del proveedor en el JSON. La etiqueta Moderada corresponde a la escala europea documentada por Open-Meteo. Son estimaciones modeladas; esta captura no acredita una estación ni la situación de toda la ciudad.

## Pruebas controladas

- Suite PHP completa: **496 pruebas, 6009 comprobaciones**, correctas.
- JavaScript completo: **110 pruebas**, correctas. Los cuatro tests nuevos siguen pasando después del ajuste final de texto/estado de carga.
- Dentro de ese total, diez pruebas PHP verifican sesión, cuenta activa, permisos, coordenadas, frecuencia, escala, unidades, cero, null, series, ubicación, fechas, caché, redirecciones, conexión y respuestas excesivas o inválidas. Se comprueba que no se crean lecturas, puntos o alertas.
- Cuatro pruebas JavaScript comprueban bandas europeas, límites, ausencia de dato, cancelación al cambiar/cerrar, descarte de respuestas atrasadas y reintentos sin borrar la consulta anterior.
- Pint, parseo JavaScript y `git diff --check`, correctos.

Las pruebas usan SQLite de pruebas y HTTP controlado; no consultan el proveedor ni alteran la demo. Los registros de ejecución quedan en `tmp/aire101-suite.log` y `tmp/aire101-js.log`, fuera de Git. Los JSON y capturas anteriores sí son una comprobación externa fechada; no se utilizan como datos sintéticos de la demostración.

## Repetir

Desde la raíz, con el entorno PHP/Node del proyecto:

```powershell
$env:VIEW_COMPILED_PATH = Join-Path (Get-Location) 'web/storage/framework/views-pruebas/aire101'
./herramientas/php.ps1 web/vendor/bin/phpunit -c web/phpunit.xml --filter CalidadAireTest
node --test web/tests/Js/aire.test.mjs
```

En el navegador con una sesión activa: Mapa de Monitoreo → Santa Cruz → Calidad del aire → Últimas 24 horas. Cambiar a otro lugar cancela la ficha anterior. Para una nueva captura externa los valores pueden cambiar; no se exige reproducir el número 47 de esta consulta.

Fuente y bandas: [Open-Meteo Air Quality API](https://open-meteo.com/en/docs/air-quality-api). Atribución CAMS ENSEMBLE, Copernicus Atmosphere Monitoring Service y Open-Meteo, CC BY 4.0. Guía: [CALIDAD_AIRE_ENVIROSENSE.md](../../CALIDAD_AIRE_ENVIROSENSE.md). Contrato: [calidad-aire.md](../../../compartido/api/calidad-aire.md).
