# API-02 / #36: caché candidata del cálculo SPI-3

El ensayo puede reutilizar un resultado para **las mismas versiones locales, ventana e implementación**, conservando por separado cuándo se recuperó la lluvia y cuándo se calculó el índice. Es una preparación experimental del PR #43: el método #35 / PR #42 sigue pendiente de revisión. No habilita la ruta HTTP ni completa #36.

## Probar en este equipo

Desde PowerShell:

```powershell
cd C:\Users\cjosu\Desktop\GeoCruz-oficial\tmp\api-sequias
.\herramientas\sequias\probar-calculo.ps1 -Recalcular
```

Debe mostrar **primera: calculo_php; segunda: cache_calculo**, aproximadamente **SPI −0,6410480784 / normal**, lluvia **114,5 mm**, mismo instante de cálculo y mismas versiones en la segunda llamada. Es el piloto histórico julio–septiembre de 2024: no descarga datos, no necesita servidor ni clave NASA. Sin `-Recalcular`, la primera llamada también puede reutilizar un resultado vigente.

El script usa las dos versiones privadas ya preparadas en este equipo. Para otra instalación, importar las capturas y declaraciones indicadas en [el ensayo](API02_ENSAYO_SPI.md) o ejecutar los tests controlados, que no requieren esas capturas ni Internet.

JSON completo, desde `web/`:

```powershell
..\herramientas\php.ps1 artisan sequias:ensayar-spi 7642d10dab58e12edaf928a3d4548a3d7d804c74aa5c36d0fc585316ab51dd79 195010a0862fd4f0384b65693bb3a7966fa0540f1ea5c41c54c0fb7313ebb723 2024-09 --cache
```

`--recalcular` implica usar la caché y reemplaza solo el resultado derivado después de releer las entradas. No descarga ni renueva las series. Sin ambas opciones, el ensayo conserva su funcionamiento anterior sin caché de cálculo. Códigos: 0 índice finito; 1 null con motivo; 2 entrada/caché inválida, bloqueo o fallo al guardar.

## Identificación, integridad y fechas

`EnsayoSpi3::preparar` vuelve a comprobar el mes UTC, compatibilidad y originales a través del repositorio antes de cualquier búsqueda. Una captura o manifiesto ausente/alterado falla incluso si existe un resultado en caché; `--recalcular` tampoco salta esa validación. No se aceptan acumulados arbitrarios enviados por un cliente HTTP.

La clave contiene la huella SHA-256 del informe preparado y del motor. Incluye IDs/procedencia/hashes, muestra, coordenadas, mes, 30 ventanas de base, lluvia objetivo, versión metodológica y SHA-256 de los tres archivos PHP de preparación/cálculo numérico. Los hashes del código normalizan CRLF a LF para identificar el mismo código en Windows/Linux; los originales climáticos conservan sus bytes exactos. Cambiar base, objetivo, mes o código produce otra clave y no borra resultados anteriores.

El registro interno tiene checksum, contexto completo y fechas de cálculo/caducidad. Al recuperarlo se contrastan checksum, motor, entradas, estructura del resultado, categoría, parámetros, año válido y duración. Un registro alterado devuelve `CACHE_CALCULO_INVALIDA`; no se corrige silenciosamente. Tras investigar, `--recalcular` permite reconstruirlo desde originales válidos. El checksum detecta inconsistencia: no es firma del proveedor ni una nueva validación científica del método.

La salida añade `cacheCalculo`: `tipo`, `esCalculoNuevo`, `huella`, `motor`, `calculadoEn`, `expiraEn`, `antiguedadCalculoSegundos` e `integridad`. Las fechas `versiones[].procedencia.obtenidoEn` permanecen intactas. Reutilizar conserva `calculadoEn` y `expiraEn`; no prolonga su vigencia. Un cálculo nuevo tampoco vuelve actuales los datos históricos.

## Caducidad y concurrencia

Política técnica candidata: **24 horas** para un índice finito; **15 minutos** para null con motivo. No representa un período de validez climática. Se valida la caducidad del contenido además del TTL del almacén. Al vencer, se recalcula desde las mismas entradas; no se descarga lluvia y el resultado determinista debe coincidir. Consultar una versión nueva del proveedor requiere hacerlo explícitamente con [la captura](API02_CAPTURA_LLUVIA.md).

Se utiliza el almacén Laravel configurado y un bloqueo por huella de 120 segundos, sin espera: otra ejecución recibe `CALCULO_OCUPADO`. Se libera al terminar, también ante errores. Un fallo de escritura comunicado por el almacén produce `CACHE_CALCULO_NO_DISPONIBLE`, preservando los originales. No usar el driver `array` si se pretende reutilizar entre procesos; los tests sí lo usan para aislarse. En varios servidores, caché y bloqueos necesitan el mismo almacén compartido ([Laravel 12: atomic locks](https://laravel.com/docs/12.x/cache#atomic-locks)).

No es una caché HTTP, ni la caché de capturas del proveedor. Las validaciones de originales se mantienen en cada ejecución; no se presenta una mejora de rendimiento medida.

## Verificación

Desde `web/`:

```powershell
..\herramientas\php.ps1 artisan test --compact --filter=CacheCalculoSpi3Test
..\herramientas\php.ps1 artisan test --compact
..\herramientas\php.ps1 vendor/bin/pint --test
```

**11 pruebas nuevas / 77 aserciones**, sin red: reutilización y fechas, renovación explícita, caducidad finita/null, aislamiento entre versiones/meses, originales ausentes/corruptos, checksum/contexto/motor/fechas/forma inválidos, bloqueo, fallo de escritura, códigos de consola y conservación del 503. Suite completa: **196 pruebas / 1.323 aserciones**; formato y demostración PowerShell aprobados. La matriz PHP 8.2/8.3 y las pruebas Windows existentes incluyen estos casos por su directorio/namespace.

[Dos salidas reales del piloto](evidencias/api02-cache/README.md) acreditan cálculo nuevo y reutilización de entradas históricas conservadas. No acreditan una descarga nueva, todos los puntos de Santa Cruz ni aprobación del método.

Quedan revisión/acuerdo de #35, integración del método revisado, operaciones HTTP de consulta/detalle/resumen y clientes. Esta caché se adaptará si cambia el acuerdo científico.
