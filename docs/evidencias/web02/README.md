# Evidencia WEB-02 — 25/10/2026

Rama `feature/37-web-sequias`, base API #43 en `27d29341037265d38178a17425db994dfffc9934`. Las capturas corresponden a la implementación de este PR y los cambios se identifican en su commit. No son imágenes de diseño ni fixtures presentados como observación.

Entorno: Windows, PHP 8.3.30, Laravel del lockfile, navegador integrado. Escritorio comprobado a 1440 × 900, con dos columnas y sin desbordamiento horizontal. Backend local `http://127.0.0.1:5790`; API y página servidas desde `tmp/web-sequias`. Originales/publicaciones privados copiados sin modificar desde la copia de API-02 del mismo equipo. No se descargó lluvia nueva para producir estas capturas.

- `01-consulta-y-detalle.png`: consulta departamental para `mesFinal=2024-09`, cobertura 1/56, 55 pendientes, selección del mismo piloto desde lista y mapa y ficha con fuente/período/coordenadas. Captura completa del documento.
- `02-no-disponible.png`: consulta de Cotoca `municipio=BO070102`, septiembre de 2024, sin publicación. Aviso explícito y ausencia de resultados inventados. Captura completa del documento.
- `03-mapa-pantalla-pequena.png`: piloto histórico en viewport solicitado de 390 × 844; documento de 375 píxeles útiles tras la barra vertical, sin desbordamiento horizontal. Se ven cobertura, punto de celda y contorno sin relleno. Captura del área visible; no acredita funcionamiento de Flutter.

Pasos manuales verificados: enlace con mes; lista → ficha → volver → marcador → misma ficha; cambio de municipio con resultado anterior identificado; respuesta 503 sin muestras; apertura del piloto; reflujo a pantalla pequeña; lista → ficha → volver conservando el mes. Se restauró el viewport al terminar. Consola del recorrido sin errores JavaScript registrados. Las imágenes del mapa base dependen de la conectividad a OpenStreetMap; puntos y contorno proceden de la API local.

En el corte, el piloto ERA5 corresponde a julio–septiembre de 2024: SPI **−0,6410480784168531**, categoría **normal**, **114,5 mm**; no representa el clima actual ni toda la superficie de Santa Cruz. La captura de origen se obtuvo a las 14:04 Bolivia del 25/10 y vence técnicamente 24 h después. Referencia numérica y límites en [API-02](../../API02_API_SEQUIAS.md).

Pruebas automatizadas separadas de esta evidencia externa: 227 pruebas PHP / 1607 aserciones y 40 pruebas Node. Las respuestas modificadas en tests son controladas; no alteran las publicaciones privadas ni los resultados del visor real.
