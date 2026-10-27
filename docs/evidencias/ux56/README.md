# UX-01 / #56 - Acceso Tabler y Mapbox

Comprobación del 27/10/2026 en la copia local, incorporada al PR #55 antes de revisión. La base candidata continúa siendo #51.

- `01-acceso-tabler.png`: composición oficial Sign in with cover de Tabler, con foto real de Amboró y su atribución, marca GeoCruz y campos Laravel.
- `02-mapbox-satelite.png`: imágenes cargadas desde api.mapbox.com, estilo satellite-streets-v12, sin filtro CSS gris. Consulta real de focos del 25/10 00:00 al 26/10 00:00 Bolivia: 489 detecciones, 17 de confianza alta y FRP máxima 104,94 MW. Se representan los 25 registros de la página actual; la imagen satélite no corresponde necesariamente al día de las observaciones.
- `03-acceso-movil.png`: acceso en viewport 390 × 844, con ancho de documento 390 y formulario sin desbordamiento horizontal.

Se probó ingreso correcto y error de contraseña de la demo local, registro responsive y cierre de sesión. En el mapa se comprobaron las tres bases, nueve teselas cargadas de Mapbox y filtro CSS `none`. Una ficha de San Ignacio mantuvo su contenido y coordenadas al cambiar Relieve → Satélite. En Sequías se abrió el piloto de septiembre de 2024: SPI-3 -0,64, Normal, conservado al pasar a satélite; también se verificó sin desbordamiento en celular.

Pasaron **244 pruebas Laravel / 1740 aserciones**, **43 pruebas JavaScript** y Pint. Los nuevos casos verifican configuración pública, rechazo de secretos, cambio de base sin borrar capas ambientales, falta de token y error de teselas. Estas comprobaciones controladas complementan la consulta externa real; no sustituyen revisión de Jaziel ni despliegue público.

Tabler CSS y JavaScript conservan sus hashes originales. Procedencia de foto y logo Mapbox en `web/public/img/amboro/LEEME.md` y `web/public/vendor/mapbox/README.md`. No se adjuntan tokens, contraseñas ni bases locales. Las capturas previas de `web54/` documentan el estado anterior a esta corrección.
