# UX-03 / #58 - Lectura del mapa y rueda

Comprobación real del 27/10/2026. Consulta del 26/10 00:00 al 27/10 00:00, hora de Bolivia, fuente NASA FIRMS: 9 detecciones, de las cuales 8 se pueden abrir en el grupo de Charagua. El resumen conserva el total de la consulta; el símbolo naranja identifica calor, sin convertirlo en un incendio confirmado ni en un nivel de gravedad.

- `01-mapa-calor.png`: resumen diferenciado por color, filas breves, llamas individuales y grupos con cantidad.
- `02-grupo.png`: grupo con 8 registros seleccionables, sin el nombre largo repetido ni potencia radiativa en cada fila.
- `03-movil.png`: lectura en celular, sin desbordamiento horizontal.
- `zoom-rueda.json`: medición antes y después de usar la rueda sobre el mapa. Las teselas pasaron del nivel 6 al 10; escala de página 1, scroll 0 y devicePixelRatio sin cambios.

Se abrió una detección de Charagua: su denominación completa, coordenadas -18.23357 / -62.69306 y potencia 4,88 MW siguen disponibles. El rótulo del mapa es breve y usa 16 px. Se verificaron el tema oscuro y las acciones legibles en ambos temas. Los botones y campos mantienen la misma familia visual del resto de GeoCruz.

Pasaron 45 pruebas JavaScript, 244 Laravel / 1740 aserciones y Pint. Los casos nuevos verifican que las llamas conservan coordenadas y que los registros coincidentes se pueden seleccionar con click y Espacio. El manejo de Enter sigue siendo el nativo de Leaflet. La rueda también se habilitó en el visor de Sequías; la medición de este archivo corresponde al visor de calor.

Los símbolos usan una silueta derivada de flame de Tabler, cuya licencia está en `web/public/vendor/tabler-icons/LICENSE`. No se editaron los archivos de Tabler ni los datos ambientales. No se adjuntan tokens, contraseñas ni bases locales. La imagen satélite tiene su propia antigüedad y no acredita la fecha de la detección. Revisión de Jaziel pendiente en PR #55.
