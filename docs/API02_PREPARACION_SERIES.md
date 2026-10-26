# API-02 / #36: preparación independiente de las series

Componente de #36: **validación, agregación mensual y almacenamiento versionado de archivos diarios de lluvia con procedencia**. El método #35 / PR #42 fue aprobado el 25/10/2026; la [API #36](API02_API_SEQUIAS.md) integra estas versiones y el ensayo SPI-3. La importación local de esta guía sigue sin incorporar fórmulas, red ni publicación HTTP por sí sola.

La [captura candidata por red](API02_CAPTURA_LLUVIA.md) se añadió como comando separado: valida la respuesta del proveedor, prepara versiones en este almacén y mantiene su propio índice de reutilización. La importación/lectura local de esta guía sigue sin consultar Internet.

## Qué hace

`sequias:validar-serie` lee un JSON **local** y emite un informe JSON `calidad-precipitacion-1.0`. El usuario declara el período esperado mediante `--desde` y `--hasta`, con meses completos y fechas inclusivas UTC. Así detecta también días iniciales/finales o un mes entero que no llegaron en el archivo. No infiere cobertura a partir de la primera y última fila recibidas.

Formato admitido: `utc_offset_seconds=0`, `daily_units.precipitation_sum="mm"`, `daily.time[]` y `daily.precipitation_sum[]`. Son listas de igual longitud; otras metainformaciones del proveedor no se copian al informe ni se usan para acreditar procedencia. Que el archivo tenga esta forma no prueba que venga de Open-Meteo ni de una estación.

Cada fecha debe existir en el calendario, usar `AAAA-MM-DD`, estar dentro del período y aparecer una sola vez en orden cronológico. Los valores son números finitos no negativos o `null`; no se convierten textos numéricos, booleanos ni valores negativos. La zona horaria debe estar declarada como entero cero, sin conversiones implícitas.

Por cada mes se informa el número de días esperado, válido, ausente y nulo, junto con las fechas afectadas. Un **cero conocido es lluvia cero**; un día ausente o `null` conserva el acumulado mensual en `null`. Los meses válidos se suman con compensación de punto flotante y sin redondear cada día. La falta de un mes no impide mostrar los demás.

El informe conserva SHA-256 y bytes exactos del archivo leído. No modifica el original, no guarda datos en base/caché, no consulta Internet y no imprime el contenido original al informar un error. Límite técnico por archivo: 8 MiB, profundidad JSON 64 y 14.640 días/registros; no es la duración adoptada para un índice.

## Probar ahora en este equipo

Esta rama se encuentra en `C:\Users\cjosu\Desktop\GeoCruz-oficial\tmp\api-sequias`. Ya se instalaron las dependencias fijadas en `composer.lock` y se preparó su configuración local. En PowerShell:

```powershell
cd C:\Users\cjosu\Desktop\GeoCruz-oficial\tmp\api-sequias\web
..\herramientas\php.ps1 artisan sequias:validar-serie ..\docs\ejemplos\sequias\serie-completa.json --desde=2024-06-01 --hasta=2024-06-30
```

Resultado esperado: `estado=completa`, 30 días válidos, cero ausentes/nulos y acumulado **60 mm**. Código de salida **0**.

```powershell
..\herramientas\php.ps1 artisan sequias:validar-serie ..\docs\ejemplos\sequias\serie-incompleta.json --desde=2024-06-01 --hasta=2024-06-30
```

Resultado esperado: `estado=incompleta`, 28 días válidos, día **10 ausente**, día **20 nulo**, acumulado mensual **null**. Código de salida **1**: informe generado correctamente que detectó una serie incompleta. Ambos archivos son **fixtures controladas**, sin datos reales ni evidencia de sequía.

Para comprobar el error estructural, omitir `--desde` o usar un archivo inexistente. Se devuelve el objeto `error` con código explicativo y salida **2**, sin traza ni contenido privado del archivo. La ayuda es `..\herramientas\php.ps1 artisan sequias:validar-serie --help`.

En otra instalación, preparar primero con `preparar.ps1` desde la raíz o `composer install` y `composer run setup` desde `web/`. Con PHP configurado se puede usar `php artisan` directamente en Windows/Linux. No se necesita Node, Python, una clave NASA ni un servidor web para este comando.

## Organización del código

- [Comando Artisan](../web/app/Console/Commands/ValidarSeriePrecipitacion.php): archivo local, lectura acotada, JSON, hash e informe/salida. Laravel descubre el comando en `app/Console/Commands`.
- [Validador de series](../web/app/Services/Sequias/ValidadorSeriePrecipitacion.php): calendario, valores y calidad mensual. No depende de Laravel, HTTP, sesión o base de datos.
- [Error de serie](../web/app/Services/Sequias/SeriePrecipitacionInvalida.php): código y mensaje controlados, separado de los errores de focos y territorios.
- [Lector JSON](../web/app/Services/Sequias/LectorJsonLocal.php): lectura local acotada compartida por los comandos; sin URLs ejecutables ni exposición del contenido en errores.
- [Procedencia](../web/app/Services/Sequias/ProcedenciaSeriePrecipitacion.php): campos declarados, coordenadas y recuperación UTC normalizados; separa fixtures de capturas externas.
- [Repositorio](../web/app/Services/Sequias/RepositorioSeriesPrecipitacion.php): guarda las versiones y verifica los bytes y el informe al reutilizarlas.
- [Preparar](../web/app/Console/Commands/PrepararSeriePrecipitacion.php) y [ver](../web/app/Console/Commands/VerSeriePrecipitacion.php): comandos del almacén local. [Configuración](../web/config/sequias.php) solo indica su directorio, sin activar ningún módulo.
- [Pruebas de series y consola](../web/tests/Feature/Sequias): casos de calendario, calidad, tipos, límites, errores y preservación del archivo/API.
- [Ejemplos controlados](ejemplos/sequias/) e [informes de ejecución](evidencias/api02-preparacion/README.md).

## Guardar y reutilizar una serie

Prueba sencilla desde la raíz de esta rama, en PowerShell:

```powershell
.\herramientas\sequias\probar-preparacion.ps1
```

Comprueba preparación, lectura e integridad de la serie completa de **60 mm**, repite la operación para comprobar que reutiliza la misma versión y conserva la incompleta con acumulado null. No descarga datos. Se puede repetir; la fecha original de preparación se conserva. El script termina en 0 cuando las comprobaciones son correctas, aunque la serie incompleta use salida 1 en sus comandos individuales.

Los comandos individuales, desde `web/`, son:

```powershell
..\herramientas\php.ps1 artisan sequias:preparar-serie ..\docs\ejemplos\sequias\serie-completa.json ..\docs\ejemplos\sequias\procedencia-completa.json
..\herramientas\php.ps1 artisan sequias:ver-serie IDENTIFICADOR_DEVUELTO --detalle
```

En otras plataformas, usar `php artisan`. `preparar-serie` devuelve un ID de 64 caracteres, `reutilizada`, `integridad`, fecha de preparación, procedencia e informe resumido. `ver-serie --detalle` añade los meses. Salidas: 0 para serie completa, 1 para incompleta guardada/recuperada con informe, 2 para error de entrada/almacén. No hace falta un servidor web ni acceso a Internet.

La declaración de procedencia es otro JSON local de máximo 64 KiB. Los [dos ejemplos](ejemplos/sequias/procedencia-completa.json) muestran los campos obligatorios: versión, tipo de evidencia, proveedor/producto/naturaleza, muestra, coordenadas solicitada y de celda `[lon,lat]`, período UTC, instante de recuperación, URL de origen, licencia, atribución y SHA-256 exacto de la captura. El instante y las coordenadas de los ejemplos controlados son de prueba. Para capturas externas se conserva el instante de recuperación original, no el de importación; su URL HTTPS se almacena como declaración y **no se consulta**.

La validación de una declaración no certifica su autenticidad. El hash debe coincidir con los bytes del archivo; si este contiene coordenadas devueltas, deben coincidir con la celda declarada. No se deduce un territorio municipal ni se equipara muestra con cobertura. Se conservan capturas incompletas con su calidad y sus faltantes, sin habilitarlas como índices válidos.

Las versiones se guardan únicamente en `web/storage/app/private/sequias/series/`, excluido de Git, con captura original y manifiesto. El ID depende de la procedencia normalizada, que incluye el hash de datos; cambiar producto, muestra, período o recuperación crea otra versión. Repetir la misma preparación devuelve la existente sin reescribirla ni cambiar `preparadoEn`.

Se escriben ambos archivos en un temporal de la misma carpeta y se publica el directorio al finalizar. Un bloqueo local de escritura evita preparaciones simultáneas por estos comandos; si está ocupado, se informa `ALMACEN_OCUPADO` para reintentar. Se usan las operaciones documentadas de PHP [flock](https://www.php.net/manual/en/function.flock.php) y [rename](https://www.php.net/manual/en/function.rename.php). El almacén está pensado para disco local y estos lectores/escritores, no como almacenamiento distribuido.

Cada lectura verifica identificador, checksums, declaración y captura, y recalcula la calidad mensual para contrastarla con el informe conservado. Una versión incompleta/modificada devuelve `SERIE_CORRUPTA`; no se repara ni sustituye silenciosamente. Los checksums detectan inconsistencias: no son una firma de un proveedor ni un respaldo contra pérdida del disco. Tampoco existe caducidad/revalidación de proveedor: este almacén versionado no sustituye la futura caché operativa del cálculo.

## Verificación ejecutada

Desde `web/`:

```powershell
..\herramientas\php.ps1 artisan test --compact --filter="Serie|Sequias"
..\herramientas\php.ps1 artisan test --compact
..\herramientas\php.ps1 vendor/bin/pint --test
```

Verificación inicial del almacén: **63 casos de preparación de sequías aprobados (198 aserciones)**; suite completa entonces **98 aprobados (461 aserciones)**; formato aprobado. Los totales posteriores al ensayo SPI están en su [guía](API02_ENSAYO_SPI.md). Se verifican bisiestos, cambio de año, ceros/null/ausentes, mes entero faltante, decimales, orden/duplicados/fechas imposibles, valores/unidades/zona inválidos, desbordamiento numérico, lectura máxima, JSON inválido y errores sin red. Las pruebas del almacén cubren reutilización, versiones distintas, procedencia, hashes, archivos ausentes/modificados, bloqueo ocupado y errores del directorio. Una prueba confirma que `/api/v1/sequias` continúa en 503 después de usar los comandos; otra comprueba que los ejemplos publicados producen exactamente sus informes conservados.

Además se releyó **offline** la captura externa conservada en el commit `1d1865f` del PR #42: 92 días completos de julio–septiembre de 2024, acumulados mensuales **28,2 / 64 / 22,3 mm**, total **114,5 mm**, con el mismo SHA-256 del archivo original. Esta es una nueva validación local de esa captura, no otra descarga ni una nueva prueba del proveedor. La ejecución se realizó el 22/10/2026 en Bolivia (23/10 03:30 UTC); enlaces y detalles en la evidencia.

## Estado y siguiente paso

Este avance puede revisarse por separado sin adoptar el SPI propuesto. Ya funcionan la validación y el almacén local de series, y se prepararon las dos capturas de #35 con integridad y reutilización verificadas. Después se agregaron el ensayo numérico PHP contrastado, la captura candidata con reutilización/renovación y el catálogo de muestras con filtros territoriales, por consola y documentados por separado. El resto de #36 mantiene su dependencia de #35: método/contrato revisados, caché del cálculo, operaciones HTTP e integración. No se cierra #36 ni se habilita el módulo con esta preparación.
