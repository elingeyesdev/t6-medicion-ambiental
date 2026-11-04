# Condición actual y diaria del tiempo — #127

Recuperación de `WeatherWidget.jsx` y `utils/weatherIcons.js` de EnviroSense, como parte de #66/#62. Mantiene el nombre GeoSur y el recorrido de la ficha del mapa. Tabler 1.7.1 y los estilos existentes se conservan.

## Qué se comprobó

- La condición actual presenta descripción e icono. Si el proveedor indica noche, despejado y parcialmente nublado usan luna.
- Cada día conserva su fecha, máxima, mínima y lluvia, y añade la condición prevista. No se agregan códigos numéricos al texto del usuario ni al selector de magnitudes.
- Cero es despejado; una clasificación ausente, inválida o desconocida no se inventa. Los símbolos usan SVG estáticos y la descripción está en texto accesible.
- El gráfico conserva su estilo propio; sus márgenes, fondo y ancho ya no afectan a los iconos de condición. En el navegador los ocho iconos medidos fueron 24×24 px, margen cero y fondo transparente.
- Las últimas 24 horas, datos actuales y pronóstico mantienen su separación y las preferencias de unidades. No se insertan estimaciones en sensores ni se disparan alertas.

## Fuentes y muestra

La [documentación oficial de Open-Meteo](https://open-meteo.com/en/docs) define `weather_code`, `is_day` y la condición diaria como la más intensa prevista. Se verificó además el código 97 de tormenta fuerte. Se conserva la misma consulta; no se contratan servicios ni se solicita una clave nueva.

La consulta real del servicio Laravel para −17.7833/−63.1833 devolvió `codigoTiempo=2`, `esDia=false`, 24 filas recientes, 168 de pronóstico y siete días. [proveedor.json](proveedor.json) contiene la respuesta HTTP de esa misma consulta y [respuesta.json](respuesta.json) el resultado normalizado. [comprobacion.json](comprobacion.json) conserva sus fechas, códigos y hashes. Son una muestra de ese instante, no datos actuales permanentes ni observaciones de una estación.

## Pantalla y pruebas

Se usó una copia local en 5792, con base, cuenta y cookie de prueba propias. Solo se reutilizó la configuración pública del fondo Mapbox existente; no se modificó el servidor 5791 ni se versionaron credenciales. El punto y su lectura de 25 °C son una demostración **simulada** de esa copia; la consulta meteorológica se presenta aparte como **estimada**. Las coordenadas de la captura son −17.78/−63.18, próximas a la consulta anterior.

Capturas verificadas: [estado actual](actual.png), [próximos días](diaria.png). Se probó también la representación con datos ausentes y códigos desconocidos mediante respuestas controladas.

- 19 pruebas PHP específicas, 279 comprobaciones.
- Suite PHP completa: **516 pruebas, 6319 comprobaciones**, sin fallos.
- Suite JavaScript: **137 pruebas**, sin fallos, incluyendo clasificación, noche, código cero, degradación, DOM seguro y conservación de unidades.
- Pint y `git diff --check` correctos. La revisión independiente detectó el estilo del gráfico heredado por los iconos y se corrigió antes de las capturas.

Pendientes: aprobación de Jaziel e integración de la cadena de #125. El [contrato compartido](../../../compartido/api/tiempo-lugar.md) queda disponible para su tarea Flutter; no se modificó móvil. M3 continúa abierto y main conserva el corte aprobado por el ingeniero.
