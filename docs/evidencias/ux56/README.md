# UX-01 / #56 - Acceso Tabler y Mapbox

Comprobación del 27/10/2026 en la copia local, incorporada al PR #55 antes de revisión. La base candidata continúa siendo #51.

- `01-acceso-tabler.png`: composición oficial Sign in with cover de Tabler, con foto real de Amboró y su atribución, marca GeoCruz y campos Laravel.
- `02-mapbox-satelite.png`: imágenes cargadas desde api.mapbox.com, estilo satellite-streets-v12, sin filtro CSS gris. Consulta real de focos del 25/10 00:00 al 26/10 00:00 Bolivia: 489 detecciones, 17 de confianza alta y FRP máxima 104,94 MW. Se representan los 25 registros de la página actual; la imagen satélite no corresponde necesariamente al día de las observaciones.
- `03-acceso-movil.png`: acceso en viewport 390 × 844, con ancho de documento 390 y formulario sin desbordamiento horizontal.

Se probó ingreso correcto y error de contraseña de la demo local, registro responsive y cierre de sesión. En el mapa se comprobaron las tres bases, nueve teselas cargadas de Mapbox y filtro CSS `none`. Una ficha de San Ignacio mantuvo su contenido y coordenadas al cambiar Relieve → Satélite. En Sequías se abrió el piloto de septiembre de 2024: SPI-3 -0,64, Normal, conservado al pasar a satélite; también se verificó sin desbordamiento en celular.

Pasaron **244 pruebas Laravel / 1740 aserciones**, **43 pruebas JavaScript** y Pint. Los nuevos casos verifican configuración pública, rechazo de secretos, cambio de base sin borrar capas ambientales, falta de token y error de teselas. Estas comprobaciones controladas complementan la consulta externa real; no sustituyen revisión de Jaziel ni despliegue público.

Tabler CSS y JavaScript conservan sus hashes originales. Procedencia de foto y logo Mapbox en `web/public/img/amboro/LEEME.md` y `web/public/vendor/mapbox/README.md`. No se adjuntan tokens, contraseñas ni bases locales. Las capturas previas de `web54/` documentan el estado anterior a esta corrección.

## Segunda revisión visual de #56 · 27/10

- `04-inicio-amplio.png`: portada con foto de Amboró a mayor tamaño, acciones visibles y tarjetas de consulta de Tabler.
- `05-filtros-legibles.png`: filtros sin títulos decorativos, campos de 16 px, etiquetas de 15 px y leyenda de 14 px. Consulta real conservada del 25 al 26/10 Bolivia, con 489 detecciones en total.
- `06-inicio-movil.png`: portada a 390 × 844.
- `07-filtros-movil.png`: filtros a 390 × 844, con una columna y sin desbordamiento horizontal.
- `08-resultados-legibles.png`: resultados y leyenda sin instrucciones repetidas ni rótulos pequeños en mayúsculas.

Se abrieron las ayudas para comprobar que conservan NASA FIRMS, OCHA/HDX, interpretación y límites del período. Al cambiar Confianza a Alta, el aviso de filtros modificados volvió a mostrarse, el resumen de 489 se identificó como anterior y Deshacer cambios restauró la consulta. La confirmación normal de consulta queda para lectores de pantalla; no se ocultan errores, resultados parciales ni vacíos.

Se repitió la suite completa: 244 pruebas Laravel / 1740 aserciones. Tras el último ajuste se verificaron 13 pruebas de acceso/visor y las 43 de JavaScript. Pint pasó. La presentación usa componentes de Tabler existentes; no se añaden pruebas que solo repitan tamaños CSS. No se cambian API, geometrías, datos ni permisos.
