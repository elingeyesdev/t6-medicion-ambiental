# API-02 / #36: captura candidata de lluvia desde Laravel

Ya se puede obtener una serie diaria puntual de **Open-Meteo Historical Weather API / ERA5**, conservar sus bytes originales y procedencia en el almacén y reutilizarla sin otra solicitud. Se ejecuta por consola, separada como experimental mientras se revisa #35 / PR #42. No habilita el endpoint público de sequías ni completa el catálogo o la integración de los clientes.

## Probar en este equipo

Abrir PowerShell y ejecutar:

```powershell
cd C:\Users\cjosu\Desktop\GeoCruz-oficial\tmp\api-sequias
.\herramientas\sequias\probar-fuente.ps1
```

La primera obtención puede indicar `consulta_proveedor` si falta una captura reciente, o `cache_captura` si ya existe. La segunda debe reutilizar el **mismo ID y fecha de recuperación**, con integridad verificada. Se esperan **92 días completos** de julio–septiembre de 2024; la captura comprobada dio **114,5 mm**. El proveedor puede revisar su historia; el script muestra el total obtenido y no impone un total viejo a una descarga nueva. No necesita clave NASA ni servidor web. Requiere Internet cuando haya que descargar.

Para solicitar expresamente otra captura, sin borrar las anteriores:

```powershell
.\herramientas\sequias\probar-fuente.ps1 -Renovar
```

Si falla la red, la cuota o la estructura, se muestra un error; no hay sustitución por datos ficticios ni por una captura caducada. Si la captura resulta incompleta, el script se detiene e indica que se consulte el informe del comando individual. La salida del script siempre identifica la propuesta y la celda de reanálisis, sin llamar estación o sequía municipal al resultado.

## Comandos individuales y flujo

Desde `web/`:

```powershell
..\herramientas\php.ps1 artisan sequias:capturar-serie scz-piloto-01 --longitud=-63.1821 --latitud=-17.7833 --desde=2024-07-01 --hasta=2024-09-30
..\herramientas\php.ps1 artisan sequias:ver-serie ID_DEVUELTO --detalle
..\herramientas\php.ps1 artisan sequias:ensayar-spi ID_BASE ID_OBJETIVO 2024-09
```

Con PHP configurado usar `php artisan`. Salidas de captura: **0**, completa; **1**, incompleta conservada con informe y acumulados null; **2**, error de entrada/proveedor/almacén. `--renovar` solicita otra versión aun si hay una reciente. El ensayo necesita además una base de referencia ya preparada y compatible, según [su guía](API02_ENSAYO_SPI.md).

Cada petición es de **una muestra y un período**, con meses completos UTC desde 1940, anteriores al mes UTC actual, como máximo 14.640 días y 8 MiB de respuesta. Las coordenadas usan punto y hasta seis decimales; se rechazan listas, comas, valores no finitos y coordenadas fuera de WGS84. El identificador de muestra es una etiqueta: no acredita que esté registrado en un municipio. La disponibilidad real se verifica con la respuesta; no se promete que todo mes pasado esté disponible.

El destino HTTPS está fijado en código. Parámetros: `daily=precipitation_sum`, `models=era5`, `timezone=GMT`, `precipitation_unit=mm`, `cell_selection=nearest`, `elevation=nan`, coordenadas y rango validados. No admite URL alternativa, forecast, `best_match` ni credenciales. El producto solicitado y las coordenadas devueltas se conservan; esto no prueba por sí solo la exactitud climática local. Ver la [documentación del proveedor](https://open-meteo.com/en/docs/historical-weather-api).

Se realiza un intento, sin seguir redirecciones ni reintentar automáticamente. Conexión acotada a 5 s, petición a 30 s, lectura individual a 10 s y presupuesto comprobado entre lecturas. Se cierran los flujos y se rechazan JSON erróneo, coordenadas ausentes/inválidas, zona/unidades incorrectas, fechas fuera de rango/duplicadas y precipitaciones inválidas. Los errores no incluyen cuerpo ni excepciones del proveedor. HTTP 429 tiene código `CUOTA_PROVEEDOR`; otros fallos o conexión dan `PROVEEDOR_NO_DISPONIBLE`.

Una serie con días ausentes/null se conserva explícitamente incompleta; no se rellena con cero. Solo después de validar se publica la versión con captura exacta, SHA-256, URL solicitada, recuperación UTC real, licencia/atribución, coordenadas solicitadas y centro de celda. Se conservan también estado HTTP y cabeceras Date/Content-Type como contexto de la descarga. Los bytes son el cuerpo JSON recibido por el cliente HTTP, sin volver a serializarlo.

## Reutilización y renovación

El índice Laravel enlaza solicitud/versionado de parámetros con un ID del almacén. La política técnica candidata reutiliza capturas completas hasta **6 horas**, incompletas hasta **15 minutos**. No representa frecuencia de actualización del proveedor ni vigencia científica de un índice. Las fechas originales se conservan al reutilizar; `esConsultaNueva=false` distingue esa operación y `httpOriginal` describe la descarga original, no otra llamada.

Al vencer, se pide otra captura. Una renovación crea otra versión con su recuperación/hash y no borra los originales. Si falla, devuelve error; no presenta el original como recién obtenido. Una renovación explícita fallida conserva el índice anterior si todavía estaba vigente; una consulta normal posterior puede recuperarlo, identificada como caché y con su fecha original. La corrupción de una versión/índice se informa y no se oculta con una descarga automática.

Un bloqueo por solicitud evita descargas simultáneas de esa captura durante 120 segundos. En este equipo se usa el caché de archivos de Laravel; los ensayos automatizados usan memoria aislada. Para varios servidores, índice y bloqueos necesitarían un almacén compartido admitido por [Laravel](https://laravel.com/docs/12.x/cache#atomic-locks). Este índice no es la caché de resultados SPI ni un planificador de descargas.

Las peticiones son manuales; no se consultan 56 municipios al abrir una página. El servicio gratuito se destina al uso académico no comercial y está sujeto a cuotas; consultar las [condiciones de Open-Meteo](https://open-meteo.com/en/terms) antes de ampliar el uso. No se evita la cuota con reintentos.

## Código, pruebas y evidencia

- `ClienteOpenMeteoPropuesto`: validación de solicitud, destino/parámetros fijos, lectura acotada y declaración de procedencia.
- `CapturadorPrecipitacion`: índice/caducidad/bloqueo y preparación de versiones, sin modificar la API pública.
- `CapturarSeriePrecipitacion`: comando Artisan y códigos de salida.
- `ValidadorSeriePrecipitacion::validarPeriodo`: calendario/límite compartido con la importación local, con el mismo comportamiento previo.
- `CapturadorPrecipitacionTest`: proveedor simulado solo dentro de tests, captura/reuso/renovación/caducidad, incompatibilidad/corrupción, faltantes, errores HTTP/conexión/cuota/redirección, tamaño y validación de la evidencia congelada. No necesita Internet.

Desde `web/`:

```powershell
..\herramientas\php.ps1 artisan test --compact --filter=CapturadorPrecipitacionTest
..\herramientas\php.ps1 artisan test --compact
..\herramientas\php.ps1 vendor/bin/pint --test
```

Verificación local: **36 pruebas nuevas / 198 aserciones; suite completa 153 aprobadas / 903 aserciones**. La demostración PowerShell y una descarga externa real se comprobaron por separado. [Evidencia y resultado con la nueva captura](evidencias/api02-fuente/README.md).

El [catálogo candidato de muestras y la captura por ID](API02_MUESTRAS_TERRITORIALES.md) ya permiten usar puntos registrados, con filtros y contexto de celda separados. El comando de coordenadas manuales de esta guía queda como herramienta de captura puntual.

Pendiente para completar #36: acuerdo de #35, adopción del cálculo/contrato, caché de resultados y operaciones HTTP para web/Flutter. El flujo candidato de captura → almacén → ensayo funciona por consola; no acredita esos componentes restantes ni disponibilidad climática en los 56 puntos.
