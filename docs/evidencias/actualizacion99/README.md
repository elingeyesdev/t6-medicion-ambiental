# Actualización de capas comprobada · #99

Captura real del 29/10 en Bolivia, 30/10 UTC. Dos recortes públicos NOAA/NCEP del ciclo GFS `2026102918`, plazos 003 y 006. Son estimaciones, no observaciones de estaciones.

- [Recibo 003](recibo-003.json): HTTP 200, 1 001 251 bytes y doce mensajes GRIB2.
- [Recibo 006](recibo-006.json): HTTP 200, 994 472 bytes y doce mensajes GRIB2.
- [Publicación](publicacion.json): dieciséis cuadros nuevos, fechas, codificación, rejilla, atribución y huellas. El catálogo pasó de 17 a 33 imágenes.
- [Selección antes y después de actualizar](seleccion-comparacion.json): mismas imágenes A/B, comparación y capas activadas.
- [Comparación en el mapa](comparacion-real.png): A a las 08:00 y B a las 20:00 del 29/10, hora de Bolivia.

La tarea Windows terminó con resultado 0 sin descargar otra vez el mismo ciclo. Su configuración horaria permite retomar una ejecución omitida y no permite despertar el equipo; no se hibernó el equipo para comprobarlo. [Estado local comprobado](horario-local.json). Esto acredita la primera actualización real y una ejecución programada repetible; no una segunda captura desatendida de un ciclo futuro.

Verificación final: **486 pruebas PHP, 5803 aserciones; 103 JavaScript; 28 Python del productor y coordinador**. Pint y la comprobación de cambios también pasaron. Las pruebas cubren alteración de un PNG previo válido, lotes incompletos, intervalos inconsistentes, repetición, archivo, bloqueo entre procesos y conservación de A/B. La comparación real se volvió a consultar sin errores de JavaScript.

GRIB, PNG publicados, estado, entorno Python y credenciales de la aplicación quedan fuera de Git. No se crearon lecturas simuladas para esta evidencia. Guía y límites: [ACTUALIZACION_CAPAS.md](../../ACTUALIZACION_CAPAS.md).
