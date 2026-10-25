# WEB-02 / #37: consulta web de sequías

Interfaz Blade y JavaScript sobre la API #36 / PR #43. La rama `feature/37-web-sequias` parte de esa implementación para avanzar en paralelo; no declara aceptada la API ni la integra en `develop`. El PR de la web se prepara en borrador sobre `feature/36-api-sequias`, para revisar solo el cambio de interfaz. Después de fusionar #43 se actualizará su base a `develop` y se pedirá la aceptación de Jaziel. `main` conserva la condición de aprobación del ingeniero.

## Probar en este equipo

Abrir **<http://127.0.0.1:5790/modulos/sequias?mesFinal=2024-09>**. El servidor de esta copia se inicia así, desde PowerShell:

```powershell
cd C:\Users\cjosu\Desktop\GeoCruz-oficial\tmp\web-sequias
.\herramientas\sequias\iniciar-visor.ps1
```

Mantener la terminal abierta. El script escucha solo en localhost y admite otro `-Puerto`. Usa las dependencias y la configuración privada de `web/`, sin cambiar Laragon. En una instalación nueva ejecutar primero `preparar.ps1`; los datos climáticos privados no se descargan al abrir el visor. Para preparar o renovar publicaciones, seguir [API-02](API02_API_SEQUIAS.md) desde **esta copia**, con su propio `storage/`.

Comprobar estos pasos:

1. Septiembre de 2024, todo Santa Cruz: **1 muestra publicada de 56 seleccionadas y 55 pendientes**, SPI mínimo **−0,64**, sin índice promedio ni superficie evaluada.
2. Abrir la muestra desde lista y desde mapa. Ambas muestran `scz-piloto-01`, SPI **−0,6410480784 / Normal**, precipitación **114,5 mm**, julio–septiembre de 2024, referencia 1991–2020 y Open-Meteo/ERA5.
3. La muestra solicitada es de Santa Cruz de La Sierra; el marcador usa el centro de celda **[−63,25; −17,75]**, correspondiente a Porongo. La ficha conserva ambas coordenadas y municipios. El contorno es contexto sin relleno; un punto no evalúa el municipio completo.
4. Cerrar la ficha conserva territorio, mes y resultados. Los enlaces guardan la consulta y, cuando está abierta, la muestra seleccionada. Atrás/adelante vuelve a consultar el contexto del enlace, sin trasladar filtros al contrato ambiental por accidente.
5. Cambiar el municipio a Cotoca: antes de aplicar, los datos visibles se etiquetan como anteriores. Al consultar sin publicación, aparece el aviso correspondiente, sin índices ficticios. Restaurar recupera los filtros aplicados. Un enlace directo a un período sin datos comienza sin resultados.
6. Pantalla pequeña: filtros en una columna, mapa/lista/ficha utilizables, sin desbordamiento horizontal del documento. El listado permite acceder a todas las muestras aunque falle el mapa base.

Los resultados proceden de la publicación real de API-02 recuperada el **25/10/2026 a las 14:04 Bolivia**. Su vigencia técnica termina el **26/10/2026 a las 14:04 Bolivia**. No es una evaluación climática actual. Si vence, renovar por consola; el visor no prolonga la fecha ni transforma faltantes en normal.

## Código y responsabilidades

- `InicioController.php`: habilita la vista y entrega los catálogos territoriales y de muestras, sin consultar lluvia ni calcular índices.
- `resources/views/sequias/index.blade.php` y `public/css/sequias.css`: estructura accesible y diseño adaptable; `inicio.blade.php` identifica los dos módulos consultables y los tres pendientes.
- `public/js/sequias/contrato.js`: filtros, enlaces, validación de contexto y cobertura, fechas y etiquetas. No ajusta Gamma ni recalcula SPI/categorías.
- `consulta.js`: carga, cancelación, respuestas atrasadas, reintento, filtros editados y vencimiento de la publicación abierta.
- `mapa.js`: Leaflet local, puntos en centros de celda, selección y contorno sin relleno. Muestras coincidentes permanecen seleccionables desde el grupo o la lista.
- `vista.js`: resumen, faltantes, ficha y procedencia del mismo conjunto recibido; no hace una segunda consulta que pueda mezclar versiones al abrir la ficha.
- `inicio.js`: conecta formulario, estado, navegación y selección; distingue resultados anteriores al editar o fallar una consulta.

La consulta usa `/api/v1/sequias` y el contorno `/api/v1/territorios/{id}/limite`. El conjunto incluye los campos completos de las fichas; abrir un detalle no recalcula ni inventa un dato. Los textos recibidos se insertan con `textContent`; no se interpreta HTML del proveedor. Las claves continúan exclusivamente en el backend privado.

## Verificación

Desde `web/`:

```powershell
..\herramientas\php.ps1 artisan test --compact
..\herramientas\php.ps1 vendor/bin/pint --test
npm test
```

Resultado local: **227 pruebas PHP / 1607 aserciones**, formato PHP correcto y **40 pruebas Node**. Las nuevas pruebas contrastan contrato, cobertura parcial, null/cero, filtros y enlaces incompatibles, solicitudes fuera de orden, edición/cancelación, datos vencidos y reintento sin mezclar contexto. Los fixtures son pruebas controladas, no consultas externas nuevas. La suite conserva focos de calor y sus verificaciones de QA.

Las [capturas y pasos de navegador](evidencias/web02/README.md) documentan la ejecución real. La revisión de Jaziel y la integración a `develop` siguen pendientes; no cierra #37 por sí solo.
