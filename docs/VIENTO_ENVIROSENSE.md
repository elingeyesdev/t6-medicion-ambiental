# Viento animado de EnviroSense · tarea #105

Continuación de las capas del mapa, sobre el candidato de Reportes #104. Se revisó `referencias/envirosense/codigo/Frontend/src/components/MapaMonitoreo/HistoricalWindParticles.jsx`: el original dibuja trazos móviles sobre el viento, los pausa al desplazar el mapa y libera el dibujo al salir. GeoSur recupera ese recorrido dentro de Laravel/Blade y Leaflet, sin añadir React ni cambiar el diseño de las pantallas.

## Cómo verlo

1. Ingresar y abrir **Mapa de Monitoreo → Ajustes del mapa → Capas del ambiente**.
2. Elegir **Viento a 10 m**, una fecha publicada y **Mostrar capa**.
3. Activar **Animar viento**. Los trazos indican hacia dónde se mueve el aire.
4. Para comparar, activar **Comparar capas o fechas** y elegir el viento y otra fecha en B. La línea blanca separa A/B y puede desplazarse con el control de división.
5. Desactivar **Animar viento** para detener el movimiento conservando la imagen. **Mostrar capa** retira ambos dibujos.

El control empieza desactivado: también permite usar el mapa sin movimiento a quienes lo prefieren. No se anima una capa escalar ni se añaden consultas a un proveedor.

## Datos y límites

- Cada lado utiliza los mismos bytes de su PNG autenticado, codificación, rejilla y fecha que la imagen de colores. La animación no crea ni guarda lecturas y no modifica el catálogo. A y B son independientes.
- `muestraVector` comparte la georreferenciación y la interpolación del lector existente. Conserva U/V antes de calcular la magnitud y respeta alfa, filas norte/sur, nodos, centros y longitudes. Las lecturas escalares siguen devolviendo los mismos valores.
- El viento nuevo usa `viento-uv127`: el byte 127 es calma exacta. Se mantienen las codificaciones explícitas `viento-uv` y `viento-direccion`. No se deduce una codificación por el nombre del archivo.
- En el mapa norte-arriba, U positiva dibuja al este y V positiva al norte. No hay trazos en calma (<1 km/h), fuera de cobertura ni en los huecos muestreados del raster. También se comprueba el tramo antes de dibujarlo.
- La ubicación inicial y duración de los trazos varían para distribuir el dibujo. Su desplazamiento en pantalla es **ilustrativo** y acotado. No representa advección física, trayectorias de humo, propagación de incendios ni avance del tiempo del pronóstico. Para cambiar el tiempo se utiliza el recorrido de imágenes, como antes.
- Hay unas 300 partículas como máximo por lado, hasta 30 dibujos/segundo, densidad del canvas limitada y una sola animación cancelable por capa. Se pausa y limpia al mover/zoom; vuelve a muestrear al terminar. Pestaña oculta y salida de página cancelan el trabajo. Retirar o cambiar una capa elimina canvas y escuchas; cambiar la opacidad afecta a la imagen y a los trazos.
- Los canvas se colocan en los mismos paneles recortados A/B y dejan pasar los eventos del ratón. Se corrigió la línea de división: estaba debajo del panel principal de Leaflet y quedaba tapada.

## Validación

Se ejecutaron **119 pruebas JavaScript**, incluidas nueve nuevas, y **28 pruebas PHP del mapa/capas (212 comprobaciones)**. Pint y `git diff --check` correctos. Los casos controlados verifican orientación y magnitud, interpolación y faltantes, calma, dirección en pantalla, trazos cortados, límite temporal, pausa y retirada de escuchas, comparación con vectores opuestos, cambio de fecha y tamaño de canvas acotado.

La prueba en navegador usa los PNG GFS ya publicados en este equipo, no una textura sintética. Se comprobaron fechas A/B independientes, encendido/apagado, opacidad, división, cambio de capa, zoom y carga completada. Capturas y recorrido en [evidencias/viento105](evidencias/viento105/README.md). Las pruebas de pausa/limpieza incluyen un adaptador de mapa/canvas; no acreditan un ensayo de rendimiento en todo tipo de equipo.

La tarea prepara revisión de Jaziel. No se modifica Flutter ni se promueve M3 a main. AQI raster, UV/evaporación e integraciones externas conservan sus pendientes propios.
