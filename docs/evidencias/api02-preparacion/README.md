# Evidencia de preparación de series / #36

Ejecución local en Laravel de la rama `feature/36-api-sequias`, base `e9a591c` de develop. Fecha: 23/10/2026 03:30 UTC, 22/10 en Bolivia. Los informes son salidas del comando `sequias:validar-serie`, conservadas sin convertir faltantes en cero. No son respuestas de la API de sequías.

- [Completa controlada](completa-controlada.json): [fixture](../../ejemplos/sequias/serie-completa.json), junio 2024. Salida 0, 30 días válidos, acumulado 60 mm. No representa precipitación real.
- [Incompleta controlada](incompleta-controlada.json): [fixture](../../ejemplos/sequias/serie-incompleta.json), junio 2024. Salida 1, 28 días válidos, ausencia el día 10 y null el 20, acumulado null. El informe conserva ambos motivos.
- [Captura real releída](captura-real-releida.json): archivo externo ya descargado en #35, leído localmente sin red. Salida 0, 92 días y tres meses completos; julio 28,2 mm, agosto 64 mm, septiembre 22,3 mm. SHA-256 de entrada: `466daaa00dc6e067bfb625532b5de1f6e40bb5a8dc2f81126188f81c23ba4fc3`.

Procedencia inmutable de la tercera entrada: [respuesta original](https://github.com/elingeyesdev/t6-medicion-ambiental/blob/1d1865f9686a97c9a61a3ecccd07006040e2b7f5/docs/evidencias/met35/fuente-20261022/objetivo.json) y [manifiesto de descarga](https://github.com/elingeyesdev/t6-medicion-ambiental/blob/1d1865f9686a97c9a61a3ecccd07006040e2b7f5/docs/evidencias/met35/fuente-20261022/manifest.json). Open-Meteo/ERA5, `precipitation_sum`, mm/UTC; recuperada a las 02:56:34 UTC del mismo día. Datos CC BY 4.0, atribución Open-Meteo y ECMWF / Copernicus Climate Change Service. Se valida completitud de una captura puntual; no exactitud de la lluvia, cobertura municipal, SPI, sequía actual ni aprobación del método.

Comandos y resultados esperados: [guía de preparación](../../API02_PREPARACION_SERIES.md). Los dos ejemplos controlados y sus informes se incluyen en las pruebas automáticas para detectar cambios en lo que el usuario puede probar.
