# Verificación de calidad del aire en web y API

Caso #152 del 6 de noviembre de 2026. Se abrió el mapa de GeoSur en Chrome y se seleccionó el marcador Santa Cruz. La consulta empezó al pulsar Calidad del aire. Se usaron las coordenadas del marcador, latitud −17,7833 y longitud −63,1812.

Web y API devolvieron HTTP 200 y el mismo JSON para ese lugar. También se comprobó la fecha, los seis valores actuales y sus unidades frente a una consulta directa a Open-Meteo para CAMS global. El dato corresponde al 06/11/2026 a las 19:00 en Bolivia. El índice europeo fue 48, PM2,5 fue 28,3 µg/m³ y PM10 fue 29,2 µg/m³. El visor mostró que son estimaciones del modelo, la fuente y 24 filas de historial. [Captura de la respuesta y comprobaciones](consulta-santa-cruz.json).

Con respuestas controladas en Chrome, un fallo 503 al actualizar conservó el resultado anterior y mostró el mensaje de reintento. En una segunda respuesta controlada, PM2,5 nulo apareció como «Sin dato» y PM10 igual a cero apareció como «0 µg/m³». La web rechazó latitud 91 con HTTP 422 y la API sin sesión devolvió HTTP 401. Pasaron 10 pruebas Laravel con 206 aserciones y 4 pruebas JavaScript.

## Repetir el caso en Flutter (#153)

1. Abrir el mapa y seleccionar Santa Cruz. Confirmar que la consulta envía latitud −17,7833 y longitud −63,1812.
2. Consultar calidad del aire y contrastar la pantalla con una respuesta nueva de `GET /api/v1/aire?latitud=-17.7833&longitud=-63.1812` de la misma ejecución.
3. Comparar fecha del dato, origen estimado, fuente, escala y unidades. Los valores del archivo son históricos y pueden cambiar entre consultas.
4. Comprobar un faltante y un error con respuestas controladas y registrar el resultado en #153.

Fuente y resolución del modelo. [Open-Meteo Air Quality API](https://open-meteo.com/en/docs/air-quality-api)
