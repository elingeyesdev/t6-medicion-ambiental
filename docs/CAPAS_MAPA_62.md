# Entrega web de capas de EnviroSense · #62

**Continuación del 30/10:** [viento animado](VIENTO_ENVIROSENSE.md), tarea #105 sobre #104. Recupera los trazos de dirección del original, con control, fechas y recorte A/B, pausa y retiro del dibujo. La animación es ilustrativa y conserva los datos de cada capa. Este agregado y el candidato anterior siguen sujetos a revisión.

Rama `feature/62-capas-web`, worktree `tmp/m3-mapa-web`, base `c8b6b7a`. Código Laravel/Blade y JS existente, sin nuevas dependencias. Se leyó la fuente activa `tmp/panel-envirosense`, `HistoricalWebGL`, los tres hooks `useRadar*`, `windMath`, `data-worker/Dockerfile`, los ETL y los documentos de base y tiempo. La referencia permanece intacta.

## Resultado e integración pendiente

El lote contiene selección de capas y fechas, reproducción secuencial con pausa, recorrido manual, opacidad, comparación A/B de distintas fechas o variables mediante división del mismo mapa, leyendas con unidades de la cuenta y lectura espacial en su centro. Cada lado conserva fecha, procedencia y escala. Solo se ofrecen fechas enumeradas con archivos presentes. No hay relleno de horas, imágenes de respaldo ni valores ambientales generados al abrir el mapa.

Se recuperan las codificaciones de temperatura, precipitación acumulada e intensidad de precipitación, viento, visibilidad, nieve equivalente de agua, AQI, humedad y presión. El candidato #96 publica ocho capas GFS reales con dos fechas; AQI continúa sin insumo publicado. El productor y sus límites están en [RASTER_ETL66.md](RASTER_ETL66.md), PR #95 y #97. Los huecos permanecen transparentes y las consultas devuelven `null`. Los modelos se presentan como estimaciones, incluidas las fechas futuras declaradas por el productor; no se convierten en registros de estaciones.

El buscador de establecimientos es un servicio propio. No cambia `BuscarLugar`, filtros de puntos, su historial/comparación, pronóstico, roles ni demo Visualizador. No hay chatbot, móvil, SQL, migraciones, importación de cuentas ni cambios en Datos/Simulaciones/Reportes/Alertas/Cuentas.

**Los tres hooks siguientes son necesarios antes de usarlo en `/mapa`. No se editaron las rutas ni bootstrap ni la vista compartida.** El único cambio en el JS principal es importar y llamar `iniciarCapas(mapa, L)`; sin componente, retorna y conserva el comportamiento anterior.

1. Copiar el bloque de [rutas-web.php](integracion62/rutas-web.php) a `web/routes/web.php`, uniendo sus `use` con los existentes. Puede quedar fuera del grupo autenticado actual porque declara sus propios middleware. No copiar una segunda cabecera `<?php`. No hace falta modificar `api.php`.

2. Dentro de `->withExceptions(function (Exceptions $exceptions): void { ... })` de `web/bootstrap/app.php`, agregar esta línea, sin eliminar la configuración actual:

   ```php
   $exceptions->render(new \App\Services\Mapas\Capas\ErroresConsultaMapa);
   ```

   El hook maneja únicamente `/mapa/capas`, `/mapa/capas/*` y `/mapa/poi`. Da JSON en español también para sesión caducada/inactiva, falta de permisos, validación, 404 y 429 anteriores al controlador. No requiere alias en bootstrap.

3. En `web/resources/views/mapa/index.blade.php`, dentro de `<aside class="monitoreo-consulta">`, inmediatamente antes de `<details class="monitoreo-unidades">`, insertar:

   ```blade
   @include('components.mapas.capas')
   ```

   El componente aporta su CSS al stack de estilos de Tabler 1.7.1; la llamada JS ya está incluida en este lote. No se reemplaza el mapa por focos. Los tres selectores de fechas son independientes: historial de puntos, A y B.

Los tests cargan **el mismo bloque de rutas** y el mismo hook de excepciones en memoria. No se alteran esos archivos compartidos para probar.

## Configuración del entorno

Agregar al entorno del despliegue, con valores propios. No copiar el `.env` del referente:

```dotenv
MAPAS_RASTER_DIRECTORIO=C:/ruta/privada/publicacion-raster
MAPAS_POI_HABILITADO=false
MAPAS_POI_TOKEN=
```

Sin directorio/catálogo se responde `sin_insumos`, con las capas visibles como «sin imágenes» y un mensaje que permite continuar con los puntos. El catálogo no consulta proveedores. Sin habilitación/token, POI queda desactivado y no sale a la red. El token puede usar como respaldo `MAPBOX_PUBLIC_TOKEN`; nunca se devuelve en estas respuestas. El mapa de fondo conserva su configuración y atribución actuales.

Rutas autenticadas de lectura: `GET /mapa/capas`, `GET /mapa/capas/cuadros/{cuadro}.png`, `GET /mapa/poi?texto=museo&pais=es`. Exigen cuenta activa, `mapa.ver` y `datos.consultar`, presentes en los roles originales. Límites independientes por cuenta: 120 consultas/minuto de catálogo, 120 imágenes/minuto, 12 búsquedas/minuto. Todas las respuestas son privadas y `no-store`. Las imágenes se solicitan con sesión; ninguna URL de archivo llega desde un parámetro del cliente.

## Publicación local reproducible

La salida PNG de los ETL originales es el insumo, no una imagen coloreada ni una captura de pantalla. El adaptador **no ejecuta los ETL antiguos ni descarga GRIB/NetCDF**, ni acepta URLs o rutas absolutas desde HTTP. El productor de #66 puede preparar estas publicaciones fuera del servidor web. No se conectaron los endpoints PNG que los hooks originales mencionan: no tienen implementación en el Backend conservado; `services/data_pipeline/generate_raster.py` es un esbozo.

1. En un directorio privado propio, conservar los PNG reales y crear una receta con los campos de [receta.example.json](integracion62/receta.example.json). El ejemplo es de **estructura**, no acredita que ese archivo o esa rejilla estén disponibles. Confirmar con las coordenadas del NetCDF/GRIB original la extensión, el orden de filas, el registro y el primer meridiano. No se adivinan a partir de las dimensiones.
2. Enumerar explícitamente cada cuadro con ID único, fecha UTC con segundos y Z, capa, codificación, producto y fuente. No mezclar dos fuentes en la misma capa/fecha. Para compararlas por separado se necesitan publicaciones distintas; no se promedia ni se elige una silenciosamente.
3. Desde la raíz del worktree:

   ```powershell
   & herramientas/php.ps1 docs/integracion62/publicar-capas.php C:/ruta/privada/publicacion-raster C:/ruta/receta.json
   ```

   Verifica PNG, CRC, tamaño, dimensiones y contrato; calcula SHA-256 y publica `catalogo.json` mediante reemplazo atómico. Si falla, conserva el catálogo anterior y devuelve código 1. No copia ni borra los PNG. Para actualizar, preparar archivos con nombres nuevos y publicar una receta completa; no sobrescribir imágenes usadas por el catálogo activo. Conservar las versiones anteriores hasta terminar las consultas en curso.
4. Configurar `MAPAS_RASTER_DIRECTORIO` con ese directorio y pulsar **Actualizar capas**. No hay proceso remoto que arranque al visitar el mapa. La incorporación al cron/worker pertenece a #66: después de cerrar los PNG válidos, ejecutar este publicador; no publicar mientras el ETL aún escribe un archivo.

Contrato `version=1`, máximo 512 cuadros y JSON de 2 MiB; PNG de datos de 8 bits, 5 MiB y 2,2 millones de píxeles como máximo. Los archivos solo admiten segmentos alfanuméricos, guion y guion bajo, separados por `/` y terminados en `.png`. Se comprueba `realpath` dentro del directorio autorizado; se rechazan escapes por enlaces. Al entregar un PNG se valida su SHA-256, CRC y dimensiones. Un faltante sale de las fechas disponibles; corrupción detectada al cargar produce un error breve y detiene la reproducción, sin cambiar de fecha.

Rejilla geográfica `EPSG:4326`, `filas=sur-norte|norte-sur`, `registro=centros|nodos`; extensión rectangular real en grados. Se admite longitud 0..360, antimeridiano y rejillas regionales. El renderizador transforma la rejilla a teselas Web Mercator de Leaflet; no estira una imagen equirectangular como si ya estuviera proyectada. La lectura interpola campos escalares, componentes U/V antes de su magnitud y usa vecino cercano para AQI. Si un vecino necesario es ausente, devuelve `null`. La resolución/quantización del PNG limita la precisión.

## Codificaciones comprobadas en la referencia

- `temp-era5`: R/255×100−50 °C, usada por ETL histórico y operacional. `temp-textura`: R/255×120−60 °C de `windMath`. Nunca se confunden.
- `viento-uv`: R/G normalizados de −100 a 100 m/s; la magnitud se convierte a km/h. `viento-direccion`: R de 0 a 150 km/h y G de 0 a 360° meteorológicos.
- `lluvia-escalones`: los 22 umbrales no lineales 0..150 del ETL. `capa=lluvia` significa acumulado en mm; `lluvia_intensidad`, mm/h. El productor debe verificar variable y período. No se acepta el fallback PRATE→APCP del ETL como si fueran equivalentes; PRATE en kg/m²/s requiere la conversión ×3600, APCP ya es cantidad acumulada.
- `vis-etl`: 0..24.140 km; `vis-textura`: 0..24.135 km. `nieve-etl`: R de 0..150 mm equivalentes de agua; `nieve-fresca-textura`: G de 0..300 mm. El producto debe distinguir nevada del período y nieve acumulada. No se anuncia profundidad en cm.
- `aqi-textura`: R×2; exige insumo y producto documentados, sin un productor AQI operativo acreditado en la referencia. `humedad-etl`: 0..100 %. `presion-etl`: 900..1100 hPa.

Alfa cero significa ausencia, incluido viento, lluvia y nieve. **Un PNG monocromo sin máscara no permite recuperar faltantes que el ETL ya convirtió a cero.** Sanear esa máscara/procedencia es una obligación del productor; el visor no inventa lo perdido. No se publica como UV el campo DSWRF del operacional, que es irradiancia, ni se asigna una unidad no verificada a evaporación. Esas salidas requieren contrato propio antes de añadirlas.

## POI y límites externos

El original buscaba con Geocoding v5. Mapbox retiró POI de ese servicio; la adaptación usa `/search/searchbox/v1/forward`, `types=poi`, idioma español, país obligatorio y seis resultados como máximo. Las URLs son fijas y no se siguen redirecciones. Validamos forma, tipo POI, coordenadas, país y tamaños, distinguimos búsqueda vacía de error y no persistimos respuestas ni datos de cuenta. Las consultas se envían solo al pulsar Buscar.

La documentación oficial de [Search Box](https://docs.mapbox.com/api/search/search-box/#supported-geographies) declara Estados Unidos, Canadá y Europa; [Geocoding v5](https://docs.mapbox.com/api/search/geocoding-v5/) ya no incluye POI. La interfaz declara que **no hay cobertura de Bolivia** y ofrece un subconjunto explícito de países de esa cobertura en `config/capas.php`. El buscador geográfico mundial existente sigue aparte. El token y el plan del despliegue deben admitir Search Box; **no se hizo conexión real a Mapbox**, ni se acreditaron cobertura fuera de esos países o resultados de establecimientos reales.

No se descargaron imágenes ambientales ni se ejecutaron NOAA/Copernicus. La carga real de PNG locales, el transporte controlado de POI y el renderizado están implementados; no se afirma una conexión operativa a proveedores ni una recuperación completa de #66/M3.

## Verificación

Usar el `.env.example` propio como entorno local de pruebas, sin credenciales. Copia independiente de `vendor` y `VIEW_COMPILED_PATH` en `web/storage/framework/views-pruebas` de este worktree. PHP 8.3.30 y wrapper del repositorio.

```powershell
cd web
$env:VIEW_COMPILED_PATH = (Join-Path (Get-Location) 'storage/framework/views-pruebas')
& ../herramientas/php.ps1 vendor/bin/phpunit tests/Feature/Mapas --colors=never
node --test tests/Js/*.test.mjs
& ../herramientas/php.ps1 vendor/bin/pint --dirty --test
git diff --check
```

Las pruebas PHP prohíben cualquier acceso a `DB::connection`, usan usuarios no persistidos y archivos de prueba en directorios aleatorios propios. No se ejecuta SQL. Se comprueban roles, permisos, sesiones inactivas, contratos, fechas, faltantes, corrupción, límites, publicación atómica, rutas arbitrarias, proveedor sin configurar, validación, respuestas malformadas, redirecciones, fallos de red, ausencia de caché de POI y frecuencia.

Las pruebas JS verifican escalas, unidades reales de la cuenta, georreferenciación, antimeridiano, vecindarios ausentes, U/V, AQI y reproducción, incluida pausa durante una petición y fallo sin saltos. Se verificó además en navegador `/mapa` con el componente real, los snippets cargados en memoria y una textura técnica 2×2 claramente rotulada como prueba: activación, dos fechas A/B independientes, leyendas, lectura del centro, selección POI mediante respuesta controlada y avance temporal. El fondo sin token permaneció sin conexión. La textura, el usuario en memoria y el servidor de comprobación están solo en `tmp/`, no forman parte del producto ni de los commits.

Resultado del 29/10/2026: **18 pruebas PHP, 152 comprobaciones; 88 pruebas JS, todas correctas**, incluidas las 15 nuevas de raster y las regresiones existentes de mapa, puntos, unidades y QA21. Pint y `git diff --check` correctos. No se ejecutó la suite PHP completa porque otros módulos requieren SQL y quedan fuera de este lote.

## Archivos del lote

- `web/app/Services/Mapas/Capas/`: variables, POI y hook de errores.
- `web/app/Services/Mapas/Radar/`: lectura segura, catálogo y publicador local.
- `web/app/Http/Controllers/Mapas/` y `web/app/Http/Requests/Mapas/`: endpoints y validación propios.
- `web/config/capas.php`; componente `web/resources/views/components/mapas/capas.blade.php`.
- `web/public/js/mapas/{raster,capa-raster-leaflet,capas,poi}.js`; dos líneas de conexión en `monitoreo.js`; `web/public/css/mapas/capas.css`.
- `web/tests/Feature/Mapas/{CapasRasterTest,PoiMapaTest}.php`; `web/tests/Js/capas-raster.test.mjs`.
- Este documento y `docs/integracion62/{rutas-web.php,publicar-capas.php,receta.example.json}`.

Quedan para el integrador los tres hooks indicados y la configuración/publicación con insumos reales saneados; adquisición y ejecución del ETL de #66, conexión real de Search Box con token habilitado y revisión conjunta de QA. No se publican PR, comentarios, merges ni cambios en otros checkouts.
