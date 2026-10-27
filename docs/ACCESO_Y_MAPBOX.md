# Corrección de acceso y mapa - #56

Josué indicó el 27/10 que el acceso, la paleta y el mapa gris no correspondían al resultado esperado. Este ajuste se incorpora al PR #55, antes de su revisión, con referencia propia #56. Conserva marca GeoCruz, datos, contrato y fuentes ambientales.

## Plantilla y apariencia

La base es **Tabler 1.7.1**, la distribución libre de `@tabler/core`, sobre Bootstrap 5. Aporta controles, formularios, tarjetas y rejilla. Sus archivos originales y licencia siguen intactos en `web/public/vendor/tabler/`.

El acceso partió del ejemplo Sign in with cover. En #57 se conserva una composición propia de formulario: tarjeta clara y foto lateral en escritorio. Comparte cabecera, pie, colores y botones con el inicio; no repite su portada. La portada y la disposición actual del formulario son una adaptación propia de GeoCruz con componentes de Tabler, no una página copiada sin cambios del catálogo. No hay dos templates distintos.

- `components/publico/cabecera.blade.php`, `paisaje.blade.php` y `pie.blade.php`: cabecera y pie comunes; paisaje reservado para la portada.
- `layouts/acceso.blade.php`: organiza la tarjeta del formulario y su foto lateral.
- `acceso/entrar.blade.php` y `acceso/registro.blade.php`: campos y validación Laravel, con CSRF.
- `public/css/paginas/publico.css`: botones, cabecera, foto, pie y acceso compartidos.
- `public/css/paginas/inicio.css`: solo presentación y tarjetas de la portada.

Sobre fondo blanco la acción principal es verde; sobre la fotografía es blanca con texto verde, como el inicio elegido. Las acciones secundarias sobre la foto tienen borde. Los botones comparten tamaño de texto de 16 px, altura mínima de 48 px y esquinas de 6 px. El logo y los verdes son de GeoCruz; la interfaz usa Segoe UI con alternativas Arial/sans-serif, sin cargar Space Grotesk. La foto es real de Amboró, con su procedencia en `web/public/img/amboro/LEEME.md` y crédito en el pie.

Referencias oficiales: [Tabler](https://tabler.io/admin-template), [botones](https://docs.tabler.io/ui/components/button) y [ejemplo de acceso original](https://preview.tabler.io/sign-in-cover.html).

## Mapa base

EnviroSense original usa Mapbox en `pages/MapaMonitoreo/MapaMonitoreo.jsx`. La adaptación inicial había usado Leaflet con teselas de OpenStreetMap y luego un filtro gris propio. Se retira ese filtro y se recupera **Mapbox como proveedor de imágenes de fondo**, conservando Leaflet 1.9.4 para dibujar capas y mantener el comportamiento ya probado.

Opciones de lectura:

- **Relieve**: Mapbox Outdoors v12, inicial.
- **Satélite**: Mapbox Satellite Streets v12.
- **Calles**: Mapbox Streets v12.

El selector cambia solo la base; no modifica coordenadas, agrupaciones, pertenencia territorial, SPI, resultados, fechas o cobertura. La selección se recuerda localmente y la usan ambos visores. Las imágenes base tienen su propia antigüedad y no demuestran condiciones actuales ni el momento de una detección. FIRMS y ERA5 siguen siendo las fuentes ambientales.

Código común: `web/public/js/mapas/base.js`. Configuración: `web/config/mapas.php`, `App/Support/ConfiguracionMapa.php` y el bloque JSON del layout. Estilos: `web/public/css/panel/mapas.css`.

## Preparar en otra instalación

Configurar `MAPBOX_PUBLIC_TOKEN=pk...` en el `.env` privado. Usar solo un token público de lectura de la cuenta autorizada y limitar sus URL según el entorno. No usar tokens `sk.`, no guardar un valor real en Git ni reutilizar la clave NASA como si fuera un token Mapbox. El token público debe llegar al navegador para solicitar las teselas; no es una clave de servidor. La configuración descarta valores secretos o mal formados.

En este PC se reutilizó el token público ya configurado en la instalación local original y se comprobó una solicitud de Mapbox con HTTP 200. No se cambió la cuenta del proveedor ni se creó una suscripción. Otra copia requiere su configuración y acceso al servicio; no se considera desplegada por tener este código.

Sin configuración o ante fallos, se informa la falta del mapa base y se conservan las capas y la lista; no se recurre silenciosamente a otro proveedor. Se conservan el logotipo y los créditos exigidos. Mapbox usa también datos de OpenStreetMap; por eso ese crédito seguirá visible junto a Mapbox. No debe borrarse para aparentar un cambio de proveedor.

Documentación oficial: [Mapbox en Leaflet](https://docs.mapbox.com/help/dive-deeper/mapbox-in-leaflet/), [Static Tiles API](https://docs.mapbox.com/api/maps/static-tiles/), [atribución](https://docs.mapbox.com/help/dive-deeper/attribution/) y [uso de tokens](https://docs.mapbox.com/help/dive-deeper/how-to-use-mapbox-securely/).

## Probar

En este PC: http://127.0.0.1:5791/login, con la misma cuenta demo local. Comprobar ingreso y error de clave, crear cuenta y su validación, cambiar Relieve/Satélite/Calles manteniendo consulta y selección, abrir el piloto de sequías, menú contraído, tema oscuro y celular. El iniciador sigue siendo `./iniciar-web.ps1 -Puerto 5791`. La cuenta demo y las publicaciones locales conservan sus condiciones anteriores; las imágenes satélite no sustituyen una publicación vencida de sequías.

## Lectura y portada revisadas · 27/10

Se quitaron los rótulos decorativos en mayúsculas, la explicación repetida de los colores y la tarjeta inicial sin resultados. Los filtros usan etiquetas y campos de 16 px. Resultados, leyenda y ayudas usan 16 px como base, sin modificar los créditos obligatorios de las teselas. El aviso de una consulta normal se conserva para lectores de pantalla; los estados de carga, error, filtros modificados, vacío y cobertura parcial siguen visibles.

Fuentes, interpretación y reglas de fechas están en Acerca de esta consulta / Fuente y detalles. La leyenda identifica las detecciones de calor; la confianza aparece en cada registro. El período mantiene el límite final excluido; no cambia la API. La portada usa la misma fotografía real a mayor tamaño, acciones breves y tarjetas Tabler diferenciadas por consulta. No se añaden cifras, fuentes nuevas ni pantallas pendientes.

La apariencia de inicio está en `css/paginas/inicio.css`; el acceso y los estilos públicos comunes siguen en `css/paginas/publico.css`. Los archivos de Tabler no se editan.


## Lectura y mapa de calor · #58

El aviso visible es Cargando. Fuentes, potencia radiativa y demás datos técnicos se conservan en Fuente y detalles / Más información. El resumen destaca las detecciones y la confianza alta; el encabezado es verde y los indicadores tienen fondos diferenciados. Las filas muestran municipio abreviado, fecha, confianza y Ver detalle. La denominación oficial completa permanece en el detalle y en la descripción accesible.

El texto del visor usa 16 px como base, sin encabezados pequeños en mayúsculas. Las detecciones se dibujan con una llama naranja derivada del SVG flame de Tabler, y los grupos con llama y cantidad. El naranja identifica calor; no representa confianza ni confirma un incendio. La confianza se muestra con su etiqueta, no como un punto verde.

Se habilita `scrollWheelZoom` de Leaflet en focos y sequías. El manejo se limita al mapa: no se desactiva el zoom del navegador en el resto de la página. Comprobación real en focos: la rueda cambió las teselas del nivel 6 al 10, con escala de página 1, mismo devicePixelRatio y scroll de página 0. El SVG cambia la representación, no las coordenadas ni la agrupación de datos.

Código de las filas y símbolos: `js/focos-calor/elementos.js`; nombres breves y agrupación: `representacion.js`; interacción cartográfica: `mapa.js`. Las pruebas verifican selección de registros coincidentes, teclado y preservación de coordenadas.

Para nuevas pantallas se mantiene texto normal de 16 px, controles reconocibles y títulos breves. Los nombres de proveedores, métricas y detalles de procedencia van en los apartados de información; el aviso de espera es Cargando. Compartir componentes no obliga a repetir la distribución de la portada en los formularios.
