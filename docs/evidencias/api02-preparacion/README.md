# Evidencia de preparación de series / #36

Ejecución local en Laravel de la rama `feature/36-api-sequias`, base `e9a591c` de develop. Fecha: 23/10/2026 03:30 UTC, 22/10 en Bolivia. Los informes son salidas del comando `sequias:validar-serie`, conservadas sin convertir faltantes en cero. No son respuestas de la API de sequías.

- [Completa controlada](completa-controlada.json): [fixture](../../ejemplos/sequias/serie-completa.json), junio 2024. Salida 0, 30 días válidos, acumulado 60 mm. No representa precipitación real.
- [Incompleta controlada](incompleta-controlada.json): [fixture](../../ejemplos/sequias/serie-incompleta.json), junio 2024. Salida 1, 28 días válidos, ausencia el día 10 y null el 20, acumulado null. El informe conserva ambos motivos.
- [Captura real releída](captura-real-releida.json): archivo externo ya descargado en #35, leído localmente sin red. Salida 0, 92 días y tres meses completos; julio 28,2 mm, agosto 64 mm, septiembre 22,3 mm. SHA-256 de entrada: `466daaa00dc6e067bfb625532b5de1f6e40bb5a8dc2f81126188f81c23ba4fc3`.

Procedencia inmutable de la tercera entrada: [respuesta original](https://github.com/elingeyesdev/t6-medicion-ambiental/blob/1d1865f9686a97c9a61a3ecccd07006040e2b7f5/docs/evidencias/met35/fuente-20261022/objetivo.json) y [manifiesto de descarga](https://github.com/elingeyesdev/t6-medicion-ambiental/blob/1d1865f9686a97c9a61a3ecccd07006040e2b7f5/docs/evidencias/met35/fuente-20261022/manifest.json). Open-Meteo/ERA5, `precipitation_sum`, mm/UTC; recuperada a las 02:56:34 UTC del mismo día. Datos CC BY 4.0, atribución Open-Meteo y ECMWF / Copernicus Climate Change Service. Se valida completitud de una captura puntual; no exactitud de la lluvia, cobertura municipal, SPI, sequía actual ni aprobación del método.

Comandos y resultados esperados: [guía de preparación](../../API02_PREPARACION_SERIES.md). Los dos ejemplos controlados y sus informes se incluyen en las pruebas automáticas para detectar cambios en lo que el usuario puede probar.

## Preparación versionada y reutilización

Nueva ejecución del 23/10/2026, aproximadamente 04:16 UTC (00:16 en Bolivia). Las dos capturas de #35 se importaron desde sus archivos locales con su procedencia original, se prepararon de nuevo y se leyeron por ID; todas las operaciones terminaron en 0. No se hizo una nueva descarga. La copia cruda y el manifiesto de cada versión están en el almacén privado local, fuera de Git.

- Base histórica extendida: [preparada](almacen/preparada-base.json), [reutilizada](almacen/reutilizada-base.json). **11.019 días / 362 meses completos**, período 1990-11-01 a 2020-12-31. ID `7642d10dab58e12edaf928a3d4548a3d7d804c74aa5c36d0fc585316ab51dd79`. La selección de una referencia para SPI sigue pendiente del acuerdo; importar todo el archivo no adopta ese método.
- Período de prueba: [preparada](almacen/preparada-objetivo.json), [reutilizada](almacen/reutilizada-objetivo.json). **92 días / 3 meses completos**, período julio–septiembre 2024. ID `195010a0862fd4f0384b65693bb3a7966fa0540f1ea5c41c54c0fb7313ebb723`. Se conservan hash y recuperación del archivo original de #35.

En ambos casos, la segunda preparación devolvió `reutilizada=true`, el mismo ID/fecha y la lectura devolvió `integridad=verificada`. El código de calidad no contiene cálculo SPI ni umbrales. Las pruebas controladas verifican además que editar datos, informe, procedencia o fecha de preparación detecta corrupción, que los incompletos conservan null y que una versión dañada no se sobrescribe al repetir el importado.
