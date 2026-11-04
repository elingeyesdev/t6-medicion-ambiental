# Entrega web de ingestión e historial — #66

Rama `feature/66-cargas-envirosense`, worktree `tmp/m3-datos-web`, base `c8b6b7a69f7aadc9e88361c38e63968ea6623f0a`. El destino de integración es la fuente activa `tmp/panel-envirosense`. Al revisar, no había diferencias en los servicios, controlador, vistas y documentación de Datos entre esa fuente y la base. Solo se editó el alcance web/Datos y su documentación/herramientas/pruebas. No se cambiaron móvil, referencia, otros checkouts, rutas, bootstrap, composer, roles ni cuenta demo. No hay migraciones nuevas; queda libre la reserva 0020xx.

## Resultado para el usuario

En **Mediciones → Importar lecturas**, un administrador o analista puede elegir CSV o **Historial saneado JSON**. JSON exige confirmar la revisión de procedencia y retirada de información privada. Cada archivo admite hasta 1000 lecturas y 2 MB. Las consultas y controles de Visualizador conservan sus permisos. Se reutilizan Blade, Tabler 1.7.1 y la pantalla existente; no hay una pantalla alternativa ni chatbot.

Todas las entradas terminan en `RegistrarLecturas`: punto existente y activo, cinco variables y rangos actuales, instante ISO 8601 con segundos y zona, no futuro, origen y fuente explícitos. No se crean puntos o cuentas por importar. La identidad sigue siendo punto + variable + instante UTC + origen + fuente. Se conservan coordenadas históricas y actor. Repetidos iguales no duplican; valores distintos provocan conflicto y revierten el archivo completo. No se mezclan registrados, estimados o simulados. Una importación puede contener varios puntos; la selección visible no reemplaza sus códigos.

## Contrato del historial

```json
{
  "formato": "geosur.historial.v1",
  "lecturas": [
    {
      "tiempo": "2026-10-27T12:00:00Z",
      "localidad": "santacruz",
      "metrica": "temperatura",
      "valor": 25.5,
      "unidad": "°C",
      "origen": "simulado",
      "fuente": "ejemplo-local"
    }
  ]
}
```

Campos exactos, todos obligatorios. Unidades exactas: `temperatura/°C`, `humedad/%`, `aqi/AQI`, `ica/ICA`, `ruido/dB`. Se rechazan unidades incompatibles; no se convierte gas crudo a AQI, AQI europeo a AQI, Kelvin o voltaje. La fuente admite identificadores de hasta 80 caracteres `[a-z0-9._-]`, comenzando con letra minúscula o dígito. No incluir URLs ni claves. Declarar `observado` no certifica un dispositivo: el operador debe revisar su procedencia.

### Preparación reproducible sin SQL

Se revisaron `Backend/Src/modules/{sensores/mqtt.service.js,historial/historial.service.js}`, `data-worker/Dockerfile` y los scripts `ETL/` de la referencia, sin modificarlos o ejecutarlos. El historial original expone `ts`, `localidad_id`, `ciudad`, coordenadas, `metrica` y `valor`; esa salida omite origen y fuente. **Ese export solo no permite acreditar procedencia**. El normalizador exige añadir `unidad`, `origen` y `fuente` a cada fila mediante una revisión previa, sin inventar esos campos. Un historial cuya procedencia no puede establecerse debe quedar pendiente.

`herramientas/datos/normalizar-historial.py` lee una lista JSON revisada con `ts,localidad_id,metrica,valor,unidad,origen,fuente`; permite las columnas de ubicación `ciudad,latitud,longitud` y las retira. Requiere un mapa explícito `{"localidades":{"identificador-del-export":"codigo-geosur"}}`. Nunca interpreta los identificadores como cuentas o IDs de puntos de Laravel. Rechaza otras columnas, fechas inválidas/futuras, escalas incompatibles y lotes excesivos. No ejecuta JavaScript, SQL ni conexiones. No sobrescribe archivos. El resultado conserva el orden y produce una huella reproducible.

Desde la raíz del worktree, ejemplo completamente sintético:

```powershell
python herramientas/datos/normalizar-historial.py herramientas/datos/ejemplos/export-revisado.json --mapa herramientas/datos/ejemplos/mapa-localidades.json --comprobar
# Crear primero una carpeta privada de trabajo; --salida debe ser un archivo nuevo.
python herramientas/datos/normalizar-historial.py herramientas/datos/ejemplos/export-revisado.json --mapa herramientas/datos/ejemplos/mapa-localidades.json --salida tmp/historial-ejemplo.json
```

La muestra de dos lecturas genera SHA-256 `233ec0c1616565139f3daf22c77f6a4c65c10ee7d019910cec2b7deafb935f49`. Los fixtures son ejemplos simulados, no una extracción de la base original. La existencia y estado de puntos se valida finalmente en Laravel.

Desde `web/`, sustituir `ID_AUTORIZADO` por una cuenta local activa ya existente con permiso de gestión:

```sh
php artisan geosur:importar-historial ../tmp/historial-ejemplo.json --actor=ID_AUTORIZADO --confirmar-saneado --comprobar
php artisan geosur:importar-historial ../tmp/historial-ejemplo.json --actor=ID_AUTORIZADO --confirmar-saneado
php artisan geosur:importar-lecturas /ruta/privada/lecturas.csv --actor=ID_AUTORIZADO
```

`--comprobar` ejerce validación y conflictos en una transacción que se revierte; no evalúa alertas ni guarda lecturas o avisos. No garantiza que el estado de los puntos permanezca igual hasta una importación posterior. CSV previo conserva su formato; el cambio de compatibilidad de consola es `--actor` obligatorio. No hay credenciales en argumentos.

## Worker local explícito

```sh
php artisan geosur:datos-worker /ruta/privada/bandeja --actor=ID_AUTORIZADO --ejecutar
php artisan geosur:datos-worker /ruta/privada/bandeja --actor=ID_AUTORIZADO --ejecutar --daemon --intervalo=5
# Ensayo finito del daemon:
php artisan geosur:datos-worker /ruta/privada/bandeja --actor=ID_AUTORIZADO --ejecutar --daemon --intervalo=1 --ciclos=2
```

Solo `local/testing`. Sin `--ejecutar` no empieza. No hay scheduler, servicio, Docker ni tarea añadida a bootstrap. `--daemon` continúa hasta Ctrl+C; `--ciclos=0` no limita ciclos. Intervalo de 1 a 60 segundos. Examina hasta 100 archivos `.json`, ordenados por nombre, y reserva la carpeta con `.geosur-worker.lock`. Publicar cada archivo terminado mediante renombrado local desde una extensión temporal distinta de `.json`.

Cada archivo es una transacción; la carpeta completa no lo es. Ante error se detiene y conserva todos los archivos; los anteriores pueden estar cargados. Repetir es seguro por deduplicación. No mueve ni borra datos. En ciclos posteriores relee los archivos y los permisos actuales del actor. Para evitar reprocesamiento costoso, el operador puede retirar de la bandeja los archivos ya comprobados. No hay recibos persistentes ni un panel de colas en este alcance.

## MQTT configurable y prueba local

`config/datos.php` lee estas variables; añadirlas a la configuración privada de integración según necesidad (no se editó `.env.example` compartido):

```dotenv
GEOSUR_MQTT_HABILITADO=false
GEOSUR_MQTT_URL=
GEOSUR_MQTT_USUARIO=
GEOSUR_MQTT_CLAVE=
GEOSUR_MQTT_CLIENTE=geosur-datos-local
GEOSUR_MQTT_CA=
GEOSUR_MQTT_ESPERA=5
GEOSUR_MQTT_TEMAS='{"geosur/ejemplo/temperatura":{"punto":"santacruz","metrica":"temperatura","unidad":"°C","origen":"simulado","fuente":"mqtt-prueba-local"},"geosur/ejemplo/humedad":{"punto":"santacruz","metrica":"humedad","unidad":"%","origen":"simulado","fuente":"mqtt-prueba-local"}}'
```

La URL real requiere `mqtts://HOST:PUERTO`, sin usuario, clave, ruta o parámetros. TLS verifica cadena y nombre; `CA` puede indicar un certificado privado local legible. `mqtt://127.0.0.1:PUERTO` o loopback IPv6 solo se admiten en `local/testing`. No se fija un broker ni credenciales del referente. Cliente estable de 1 a 23 caracteres alfanuméricos, guion o guion bajo; usar IDs distintos para receptores distintos. Usuario/clave se leen del entorno, nunca del payload o URL.

El mapa admite de 1 a 50 temas exactos sin comodines. Cada tema fija punto, variable, unidad, origen y fuente; el mensaje solo acepta:

```json
{"valor":25.5,"instante":"2026-10-27T12:00:00Z"}
```

Máximo 4096 bytes por contenido. Fecha del mensaje obligatoria: no se sustituye por la recepción. `gas_crudo`, números parciales y valores sin escala documentada no se aceptan como una conversión ambiental. Revalidar y documentar calibración/procedencia antes de configurar un tema con origen registrado.

Reproducción sin red, siempre `simulado/mqtt-ejemplo-local` aunque el mapa diga observado:

```sh
php artisan geosur:mqtt-reproducir ../herramientas/datos/ejemplos/mensajes-mqtt.json --actor=ID_AUTORIZADO --ejecutar
```

Solo `local/testing`; requiere el mapa anterior, pero no broker, credenciales ni habilitación externa. El archivo entero es atómico. Los tests también ejecutan un broker de contrato TCP efímero en loopback: no representa un dispositivo físico.

Recepción de un broker configurado y autorizado:

```sh
php artisan geosur:mqtt-recibir --actor=ID_AUTORIZADO --conectar --max-mensajes=10 --segundos=30
```

Exige además `GEOSUR_MQTT_HABILITADO=true`. No arranca al abrir la web. Duración de 1 a 3600 segundos; hasta 10000 mensajes, `0` sin límite de cantidad dentro de la duración. Sin mensajes puede terminar correctamente con cero; no acredita captura. Sin reconexión automática: un fallo devuelve código 2, conserva commits previos y requiere revisar/reiniciar explícitamente. Los permisos se consultan de nuevo por mensaje. Un cerrojo local por URL/cliente evita dos receptores locales con el mismo ID.

Receptor acotado a [MQTT 3.1.1 de OASIS](https://docs.oasis-open.org/mqtt/mqtt/v3.1.1/os/mqtt-v3.1.1-os.html), temas exactos y QoS 0/1. Solicita QoS 1 y sesión persistente con cliente estable. Envía PUBACK después del commit; un fallo cierra sin confirmación. Reenvíos/retained válidos conservan instante y se deduplican. QoS 0 no permite recuperación garantizada; QoS 2 se rechaza. Un mensaje inválido persistente puede ser reenviado por el broker: corregir/retirar ese mensaje o su suscripción mediante administración del broker. Al cambiar los temas configurados, limpiar explícitamente la sesión anterior en el broker o usar un cliente nuevo para evitar suscripciones antiguas; los temas no permitidos se rechazan. No publica, cambia ACL, borra sesiones ni administra el broker.

## Frontera ETL y módulos ajenos

El Dockerfile de `data-worker` original termina en `tail -f /dev/null`. Los scripts `ETL/etl_historico_temperatura.py`, `etl_historico_humedad.py` y `etl_noaa_operacional.py` descargan NetCDF/GRIB y producen PNG con escalas/fechas propias. No constituyen lecturas físicas de los puntos de Laravel. Radar/raster y descargas NOAA/CDS pertenecen al otro agente; no se implementaron aquí.

El punto de entrega para datos puntuales de un ETL futuro es `App\Services\Datos\ImportarHistorial::ejecutar($archivo, $actor)` con el contrato `geosur.historial.v1`, o su bandeja local. Antes debe resolver muestreo geográfico, punto existente, fecha, unidad y procedencia; modelos como estimados con proveedor identificable. No pasar imágenes, coordenadas de píxeles, AQI europeo o gas sin calibración a este servicio. No se ejecutó el ETL original ni se montaron `.cdsapirc`/`.adsapirc`.

`RegistrarLecturas` conserva su llamada preexistente a `EvaluarAlertas` después de guardar lecturas nuevas; no se editó Alertas ni sus transportes. Las pruebas usan cuentas sintéticas, correo controlado y HTTP bloqueado/falso. No se envió ningún aviso a personas. Si el integrador quiere suprimir evaluación para cargas históricas, el hook preciso es esa llamada dentro de la transacción de `RegistrarLecturas`; acordarlo con el agente de Alertas, sin alterar su módulo desde este lote.

## Integración exacta de rutas y bootstrap

**No hay rutas nuevas.** Este bloque ya existe en la base y debe conservarse una sola vez. Se incluye el equivalente autocontenido para verificar la integración en `web/routes/web.php`:

```php
Route::post('/sensores/importar', [\App\Http\Controllers\SensoresController::class, 'importar'])
    ->middleware(['auth', 'cuenta.activa', 'can:datos.gestionar'])
    ->name('sensores.importar');
```

Si permanece dentro de los grupos existentes `auth/cuenta.activa` y `can:datos.gestionar`, no repetir el middleware ni añadir otra definición. El cambio real está en `SensoresController::importar`, su FormRequest y la vista de operaciones. Mantener middleware web y CSRF. **`api.php`: ninguna adición. `bootstrap/app.php`: ninguna adición.** Laravel descubre los comandos de `app/Console/Commands`; todos los servicios concretos se resuelven automáticamente, sin proveedores ni bindings nuevos. No hay migraciones o dependencias para unir.

El integrador puede revisar y aplicar el commit local con `git cherry-pick HASH_DEL_COMMIT` en su checkout; este agente no fusiona `main/develop`, publica PR ni escribe en GitHub.

## Archivos del lote

- Modificados: `web/app/Console/Commands/ImportarLecturasAmbientales.php`, `web/app/Http/Controllers/SensoresController.php`, `web/app/Services/Datos/RegistrarLecturas.php`, `web/config/datos.php`, `web/resources/views/datos/operaciones.blade.php`, `docs/DATOS_AMBIENTALES.md`.
- Nuevos servicios: `web/app/Services/Datos/{ArchivoDatos,AutorizarIngestion,ImportarHistorial,LoteComprobado,ProcesarBandejaDatos}.php` y `web/app/Services/Datos/Mqtt/{ClienteMqtt,ConfiguracionMqtt,IngerirMensajeMqtt}.php`.
- Nuevos comandos: `web/app/Console/Commands/{DatosWorker,ImportarHistorialAmbiental,RecibirMqtt,ReproducirMqtt}.php`; request `web/app/Http/Requests/Datos/ImportacionRequest.php`.
- Herramientas: `herramientas/datos/{normalizar-historial.py,test_normalizar_historial.py}` y ejemplos `{export-revisado,mapa-localidades,mensajes-mqtt}.json` en `herramientas/datos/ejemplos/`.
- Pruebas: `web/tests/Feature/{IngestionDatosTest,MqttTransporteDatosTest,ConcurrenciaIngestionDatosTest}.php`, fixtures `web/tests/Fixtures/Datos/{mqtt-broker,registrar-proceso}.php`.
- Documento de integración y límites: `docs/ENTREGA_DATOS66.md`.

## Pruebas realizadas y límites externos

PHP 8.3.30 de Laragon mediante `herramientas/php.ps1`, `vendor` copiado e independiente, `.env` local creado solamente desde `.env.example`. `VIEW_COMPILED_PATH` separado en `web/storage/framework/views-pruebas`. No se copiaron credenciales, cuentas o bases de la referencia. Las bases de pruebas son SQLite en memoria o archivos nuevos bajo `tmp/` del worktree; no son bases del usuario.

- Suite Laravel completa: **413 pruebas, 3946 aserciones, sin fallos**.
- Verificación final del módulo tras los últimos ajustes de lectura MQTT y presentación: **30 pruebas, 213 aserciones, sin fallos**.
- Concurrencia incluida: cuatro procesos con una barrera de arranque, 60 identidades repetidas, modos SQLite DELETE y WAL; todos terminan y queda una copia. Dos lotes con conflicto conservan un ganador completo y revierten el perdedor.
- Transporte MQTT incluido: handshake/suscripción, publicación antes de SUBACK, fragmentación, retained/reenvío, rechazo del broker, rechazo de tema/suscripción, mensaje fuera de rango, trama truncada/excesiva, QoS 2, falta de confirmación y persistencia fallida sin PUBACK.
- Normalizador Python: **5 pruebas**, incluidos origen ausente, unidades incompatibles, fechas, valores, columnas ajenas, límite, no sobrescritura y huella reproducible.
- Pint sobre los PHP del lote y `git diff --check`: correctos.

Comandos de reproducción desde la raíz (PowerShell):

```powershell
$env:PATH = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64;' + $env:PATH
$env:VIEW_COMPILED_PATH = Join-Path (Get-Location) 'web/storage/framework/views-pruebas'
./herramientas/php.ps1 web/vendor/bin/phpunit -c web/phpunit.xml
python herramientas/datos/test_normalizar_historial.py
```

**No ejecutado:** broker/dispositivo real, TLS contra un broker externo, calibración de métricas, importación de un histórico real, NOAA/CDS, raster, despliegue y prueba PostgreSQL. PostgreSQL 16 existe, servicio `postgresql-x64-16` detenido, `pg_isready -h 127.0.0.1 -p 5432 -t 1` devuelve sin respuesta; PHP aislado dispone de PDO SQLite. No se arrancó el servicio ni se inspeccionaron/borraron bases ajenas. No declarar PostgreSQL probado ni recuperación externa completa. El alcance entregado proporciona ingestión, adaptador y tareas locales verificadas; la conexión externa depende de configuración y autorización reales.
