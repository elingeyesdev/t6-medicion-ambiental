# Horas recientes del tiempo — #124

Candidato web/API del 31/10/2026. Recupera el acceso a estimaciones meteorológicas recientes de `getHistoricalWeatherAtLocation`, conservado en `referencias/envirosense/codigo/Frontend/src/utils/weatherApi.js`. Se integra en la ficha del lugar; no reemplaza la línea temporal raster ni las lecturas de sensores. La calidad del aire conserva su servicio separado.

## Consulta real

Se capturó una respuesta pequeña de Open-Meteo y se consultó también desde el servicio Laravel, con TLS validado. Lugar público de referencia: Santa Cruz de la Sierra, −17.7833/−63.1833. No se usó la ubicación del usuario, credenciales del proveedor ni descargas de imágenes.

- Condiciones actuales del proveedor: 31/10/2026 21:15 UTC.
- Respuesta original: 192 filas horarias y ocho días al pedir `past_days=1` y `forecast_days=7`.
- Respuesta de GeoSur: 24 filas recientes, 168 del calendario del pronóstico y siete días, zona `America/La_Paz`.
- Ventana reciente disponible: 30/10/2026 22:00 UTC → 31/10/2026 21:00 UTC.

Los archivos [proveedor.json](proveedor.json), [respuesta-api.json](respuesta-api.json) y [comprobacion.json](comprobacion.json) conservan respuestas, conteos, instantes, tamaños y hashes. Son muestras de ese momento, no datos actuales de demostración ni resultados académicos aprobados. El proveedor puede actualizar sus estimaciones entre consultas.

La documentación oficial describe Past Days como pronósticos archivados: [Open-Meteo, Weather Forecast API](https://open-meteo.com/en/docs). No se presentan como reanálisis ERA5 ni observaciones de estaciones.

## Comprobación de la pantalla

Se usó una copia independiente en el puerto local 5792, con base vacía y cuenta/cookie de prueba propias. No se cambió la base ni la sesión del servidor 5791. Se abrió **Buscar un lugar → Tiempo de un lugar**, se eligió el centro del mapa con Enter y se alternó **Tiempo por hora → Período → Últimas 24 horas**.

La captura [ficha-horas-recientes.png](ficha-horas-recientes.png) corresponde al centro inicial del mapa, distinto del punto de la consulta anterior. Muestra el período, variable, gráfico, tabla y la identificación «Estimaciones recientes». En esta copia no se configuraron las credenciales del fondo cartográfico; el fondo vacío de la captura no representa un cambio del mapa del usuario.

Los controles conservan Tabler y el estilo existente. La variable, el período y el desplegable se mantienen al cambiar unidades; no se vuelve a consultar al proveedor por esa acción. Las horas repetidas por cambio de horario llevan su offset; las demás conservan la fecha breve. Los faltantes o intervalos sin muestras cortan la línea.

## Pruebas y revisión

- 14 pruebas PHP específicas, 204 comprobaciones: acceso, permisos, caché, errores, valores cero/faltantes, límites de la ventana, series parciales/vacías, siete fechas, medianoche con `current` retrasado, cambio de horario y Nepal.
- Suite PHP completa sobre la corrección final: **511 pruebas, 6244 comprobaciones**, sin fallos.
- 132 pruebas JavaScript del visor y gráficos, sin fallos, incluidas 14 nuevas de la ficha meteorológica.
- Pint y `git diff --check` correctos.
- La revisión independiente detectó y se corrigieron dos casos: conservar el día de consulta cuando `current` todavía corresponde a ayer y distinguir las horas locales repetidas durante DST.

La aprobación de Jaziel sigue pendiente. Esta tarea no cierra M3 ni la recuperación completa de EnviroSense. El contrato para un cliente está en [tiempo-lugar.md](../../../compartido/api/tiempo-lugar.md); Flutter no se modificó. La integración continúa por `develop` y `main` conserva el corte aprobado por el ingeniero.
