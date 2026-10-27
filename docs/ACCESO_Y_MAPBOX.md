# Corrección de acceso y mapa - #56

Josué indicó el 27/10 que el acceso, la paleta y el mapa gris no correspondían al resultado esperado. Este ajuste se incorpora al PR #55, antes de su revisión, con referencia propia #56. Conserva marca GeoCruz, datos, contrato y fuentes ambientales.

## Plantilla y apariencia

El acceso y el registro usan la composición oficial **Sign in with cover de Tabler**: fila sin separaciones, columna de formulario `col-12 col-lg-6 col-xl-4`, borde superior primario, `container-tight` y cubierta `col-lg-6 col-xl-8`. Referencia: https://preview.tabler.io/sign-in-cover.html. Tabler 1.7.1 permanece local y sin editar; su licencia e integridad están en `web/public/vendor/tabler/`.

Las adaptaciones propias son el nombre/logo, los campos Laravel, los textos breves, el verde de la marca y la fotografía real de Amboró. No se usa el fondo financiero de la demostración de Tabler. Fuente y licencia de la foto: `web/public/img/amboro/LEEME.md`. El diseño se reduce a una columna en celular; no se mantiene un mapa decorativo gigante junto al formulario.

- `layouts/acceso.blade.php`: composición de cubierta.
- `acceso/entrar.blade.php` y `acceso/registro.blade.php`: formularios con validación y CSRF.
- `public/css/paginas/publico.css`: base pública y cubierta de acceso.
- `public/css/paginas/inicio.css`: portada, foto y tarjetas de consultas, separadas del acceso.
- `public/css/panel/panel.css`: colores y controles compartidos.

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

Se quitaron los rótulos decorativos en mayúsculas, la explicación repetida de los colores y la tarjeta inicial sin resultados. Los filtros usan etiquetas de 15 px y campos de 16 px. Resultados, leyenda y ayudas usan 14 px como mínimo, sin modificar los créditos obligatorios de las teselas. El aviso de una consulta normal se conserva para lectores de pantalla; los estados de carga, error, filtros modificados, vacío y cobertura parcial siguen visibles.

Fuentes, interpretación y reglas de fechas están en Acerca de esta consulta / Fuente y detalles. La leyenda se titula Confianza. El período mantiene el límite final excluido; no cambia la API. La portada usa la misma fotografía real a mayor tamaño, acciones breves y tarjetas Tabler diferenciadas por consulta. No se añaden cifras, fuentes nuevas ni pantallas pendientes.

La apariencia de inicio está en `css/paginas/inicio.css`; la cubierta y los estilos públicos comunes siguen en `css/paginas/publico.css`. Los archivos de Tabler no se editan.
