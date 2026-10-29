# Buscador de lugares #62

- `tokio-pronostico.png`: búsqueda de Tokio y selección del resultado de Japón. El mapa se desplaza fuera de Sudamérica y abre el pronóstico del lugar; las lecturas históricas no cambian.
- Se comprobó Limpiar, nueva búsqueda y elección con el teclado, además de los errores del servicio en pruebas aisladas. La consulta se realiza al confirmar el formulario, sin buscar por cada letra.
- No se guardan respuestas reales de geocodificación ni tokens. Las capturas conservan atribución a Mapbox. Los valores de tiempo son estimaciones de Open-Meteo y pueden variar.
- La suite completa pasó: 365 pruebas de Laravel y 3410 comprobaciones; siete pruebas nuevas del buscador. Las 53 pruebas de JavaScript y la sintaxis de los archivos modificados también pasan.
