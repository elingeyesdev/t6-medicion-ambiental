# Captura real de energía · 30/10/2026

Datos NOAA/NCEP GFS del ciclo 29/10/2026 18 UTC y plazos 003/006. Son modelos, no observaciones de estaciones. Dos peticiones HTTPS de 323 762 bytes en total. Los recibos identifican URL pública, fecha, estado HTTP, tamaño y SHA-256 del insumo. No incluyen credenciales.

- `plazo-3/` y `plazo-6/`: PNG de datos, receta y procedencia del productor, sin volver a guardar los GRIB privados.
- [Auditoría](auditoria.json): comparación de cada píxel válido con el GRIB, máscara, límites de representación, signo y errores máximos de cuantización.
- [Comparación A/B](mapa-energia.png): radiación solar a la izquierda y PEVPR a la derecha, ambos con fecha válida del 29/10 a las 17:00 de Bolivia. Sus escalas son distintas; no se interpreta la misma tonalidad como el mismo valor.
- [Unidades y lectura](lecturas-energia.png): W/m² y las leyendas. La lectura del centro fue 204,59 W/m² de radiación media y 209,02 W/m² de PEVPR instantáneo en (-26,3525, -59,2383). Mover el mapa cambia el punto de lectura.

Guía, comandos, pruebas, perfil de actualización y límites en [ENERGIA_ENVIROSENSE.md](../../ENERGIA_ENVIROSENSE.md). Se conservaron las 33 imágenes publicadas antes de esta captura; nuevas actualizaciones dejaron 57 cuadros activos en el corte final.
