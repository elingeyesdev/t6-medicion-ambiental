# API-02 / #36: preparación independiente de las series

Primer avance de #36, separado del acuerdo científico de #35 / PR #42. Se implementa **validación y agregación mensual de archivos diarios de lluvia**, reutilizable cuando se adopte el método. No incorpora SPI, umbrales, años de calibración, catálogo de muestras, llamadas de red ni publicación de la API de sequías. La ruta existente sigue respondiendo 503 `MODULO_NO_DISPONIBLE`.

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
- [Pruebas de series y consola](../web/tests/Feature/Sequias): casos de calendario, calidad, tipos, límites, errores y preservación del archivo/API.
- [Ejemplos controlados](ejemplos/sequias/) e [informes de ejecución](evidencias/api02-preparacion/README.md).

## Verificación ejecutada

Desde `web/`:

```powershell
..\herramientas\php.ps1 artisan test --compact --filter="Serie|Sequias"
..\herramientas\php.ps1 artisan test --compact
..\herramientas\php.ps1 vendor/bin/pint --test
```

Resultado local: **40 pruebas nuevas aprobadas (97 aserciones)**; suite completa **75 aprobadas (360 aserciones)**; formato aprobado. Se verifican bisiestos, cambio de año, ceros/null/ausentes, mes entero faltante, decimales, orden/duplicados/fechas imposibles, valores/unidades/zona inválidos, desbordamiento numérico, lectura máxima, JSON inválido y errores sin red. Una prueba confirma que `/api/v1/sequias` continúa en 503 después de usar el comando; otra comprueba que los ejemplos publicados producen exactamente sus informes conservados.

Además se releyó **offline** la captura externa conservada en el commit `1d1865f` del PR #42: 92 días completos de julio–septiembre de 2024, acumulados mensuales **28,2 / 64 / 22,3 mm**, total **114,5 mm**, con el mismo SHA-256 del archivo original. Esta es una nueva validación local de esa captura, no otra descarga ni una nueva prueba del proveedor. La ejecución se realizó el 22/10/2026 en Bolivia (23/10 03:30 UTC); enlaces y detalles en la evidencia.

## Estado y siguiente paso

Este avance puede revisarse por separado sin adoptar el SPI propuesto. El resto de #36 mantiene su dependencia de #35: método/contrato, fuente operativa, preparación histórica y caché, cálculo contrastado, territorio y respuesta HTTP. No se cierra #36 ni se habilita el módulo con este validador. Después del acuerdo se reutilizará esta validación antes de preparar las series del cálculo y se ampliarán los tests de integración correspondientes.
