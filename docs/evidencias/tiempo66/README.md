# Consulta meteorológica #66

Comprobación del servicio de pronóstico recuperado, dentro del mapa principal de EnviroSense/GeoSur.

- `pronostico-santa-cruz.png`: ficha de Santa Cruz y pronóstico real de Open-Meteo. Son estimaciones del proveedor, no datos simulados ni lecturas de una estación física.
- `pronostico-movil.png`: consulta de otro lugar en pantalla de 390 px; el encabezado y el botón de cerrar permanecen visibles al recorrer los datos.
- `resultado-pruebas.txt`: 365 pruebas aisladas y 3410 comprobaciones, con el buscador incluido. Hay ocho pruebas específicas de este servicio, sin usar red en CI.
- Se comprobó también elegir otro lugar con el centro del mapa y Enter, cambiar la variable del pronóstico por hora y cerrar la ficha. La consulta no altera los registros locales. La rueda del ratón sobre el mapa cambia su zoom.

La fuente se consultó el 28/10/2026. Los números pueden variar al volver a consultar. No se publica ninguna clave ni la configuración local.
