# MQTT local reproducible — #66

Prueba de integración **LOCAL del software GeoSur**, basada en
`b994b643d1b9086abd695053eb3a27030ed238b3`. Worktree de esta entrega:
`C:/Users/cjosu/Desktop/GeoCruz-oficial/tmp/mqtt-local66`, rama
`test/66-mqtt-local`. Candidato separado para revisión de Jaziel; no se integra por preparar esta prueba.

Complementa [ENTREGA_DATOS66.md](ENTREGA_DATOS66.md): allí, la prueba TCP usa
un broker de contrato propio; aquí se ejecuta **Aedes 0.51.3**, con un
publicador **MQTT.js 5.10.4**, ambos fijados en un paquete privado y lockfile.
El broker escucha únicamente en `127.0.0.1`, puerto efímero asignado por el SO.
No se instala un servicio ni se cambia la configuración de la aplicación.

## Qué ejecuta

`herramientas/datos/mqtt-local/ejecutar.mjs` organiza la batería y arranca
PHP como proceso hijo oculto, sin shell. `laravel.php` carga el autoload, el
bootstrap y el kernel reales de Laravel y llama al comando existente
`geosur:mqtt-recibir` mediante `Kernel::call`. No reemplaza el receptor,
la validación, la autorización ni el guardado por mocks. La ruta probada es:

`MQTT.js → TCP loopback → Aedes → ClienteMqtt → IngerirMensajeMqtt → RegistrarLecturas → SQLite`.

Cada ejecución crea un directorio nuevo `tmp/mqtt-local66/runs/run-*` con
SQLite, cachés, storage y JSON de resultado propios. Ejecuta las migraciones
existentes sobre un archivo vacío, sin seeders; crea **una cuenta sintética
local** con el permiso existente de analista y dos puntos sintéticos. No
modifica el módulo de roles. La cuenta usa `mqtt66@example.invalid`; su
contraseña aleatoria nunca se imprime ni permite acceso a una cuenta externa.

Los temas fijan temperatura en `mqtt66-a` y humedad en `mqtt66-b`. Todos
los registros usan `origen=simulado` y `fuente=mqtt-local66`. La fecha base
es `2026-10-27T12:00:00Z`; el reloj local debe ser posterior a esa fecha.
El ensayo de futuro usa el año 2999.

El adaptador ignora cualquier `.env` del checkout y las cachés compartidas.
Solo hereda variables del SO necesarias para lanzar procesos. Fija SQLite y
verifica que el directorio pertenezca al harness antes de abrirla. No ofrece
un argumento para conectar una DB o un broker ajenos.

La evaluación real de alertas sigue activa; HTTP está bloqueado mediante
`Http::preventStrayRequests`, correo y notificaciones están interceptados.
No hay preferencias o suscripciones de personas. Cada snapshot comprueba
cero avisos, envíos y suscripciones. PHP tiene deshabilitados `exec`,
`shell_exec`, `system`, `passthru`, `proc_open` y `popen` durante el
harness; `COLUMNS/LINES` evitan sondeos de terminal de dependencias.

## Preparar y ejecutar sin ventanas

Requisitos: Node >=22, PHP compatible con el proyecto (verificado con 8.3.30),
extensiones mbstring, PDO SQLite y SQLite3, y el vendor del proyecto con
las dependencias de desarrollo. No se requiere Composer ni PHP en PATH.
No se usan `exec_command`, `shell_tool`, `shell_snapshot`, UI ni consolas visibles.
No se cambian ajustes globales de Codex.

Si falta vendor en este worktree, hacer una **copia física independiente solo
de vendor**. La ejecución documentada usó
`tmp/condicion-tiempo127/web/vendor`: 9685 entradas, sin enlaces simbólicos;
se verificó también que un archivo copiado tenía un único enlace físico.
No se copian `.env`, storage, cachés ni bases de esa fuente.

Ejemplo para Node REPL de Codex, con rutas de esta máquina:

~~~javascript
var fs66 = await import('node:fs/promises');
var hidden66 = await import('file:///C:/Users/cjosu/.codex/scripts/run-hidden.mjs?v=windowstyle-20261101');
var root66 = 'C:/Users/cjosu/Desktop/GeoCruz-oficial/tmp/mqtt-local66';
var tmp66 = root66 + '/tmp/mqtt-local66';
await fs66.mkdir(tmp66, { recursive: true });
// Solo si aún no existe web/vendor:
// await fs66.cp('C:/Users/cjosu/Desktop/GeoCruz-oficial/tmp/condicion-tiempo127/web/vendor',
//   root66 + '/web/vendor', { recursive: true, errorOnExist: true, force: false });
await fs66.writeFile(tmp66 + '/php.ini', [
  'extension_dir="C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/ext"',
  'extension=mbstring', 'extension=pdo_sqlite', 'extension=sqlite3',
  'extension=openssl', 'date.timezone=UTC', 'memory_limit=512M', ''
].join('\n'));
var result66 = await hidden66.runHidden('C:/Program Files/nodejs/node.exe', [
  root66 + '/herramientas/datos/mqtt-local/ejecutar.mjs',
  '--php', 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe',
  '--php-ini', tmp66 + '/php.ini', '--instalar'
], {
  cwd: root66, timeout: 60000,
  env: { SystemRoot: 'C:/Windows', WINDIR: 'C:/Windows',
    TEMP: nodeRepl.tmpDir, TMP: nodeRepl.tmpDir }
});
nodeRepl.write(result66);
~~~

`--instalar` copia los manifiestos a `tmp/mqtt-local66/npm` y ejecuta
`node.exe npm-cli.js ci --ignore-scripts --no-audit --no-fund` con prefix,
caché y configuración npm privados y el registro oficial explícito. Solo
esta preparación descarga paquetes; la batería posterior usa solo loopback.
No lee las credenciales de npm del usuario. Se puede indicar la ruta absoluta
de `npm-cli.js` con `--npm-cli`. No usa envoltorios `.cmd`.

Para repetir sin descargar, omitir `--instalar`. El harness verifica las
versiones directas y que el lockfile privado sea idéntico al versionado.
Devuelve código 0 solo si todos los escenarios y la limpieza pasan; código 1
si falla la integración. Cada hijo PHP tiene un límite de 20 segundos y cada
recepción un límite de 10 segundos (2 segundos en la prueba sin captura).

Si una máquina necesita más tiempo para la batería completa, lanzar el mismo
Node con `spawn`, `windowsHide:true`, `shell:false`, argumentos separados y
stdout/stderr dirigidos a archivos bajo `tmp/mqtt-local66/`; consultar sus
logs y su evento `close` sin bloquear la conversación durante más de 60 s.
No ampliar el límite usando una consola visible. El tiempo del harness no
es una medición de rendimiento de producción.

## Batería y resultado obtenido

Ejecución final del **1 de noviembre de 2026 UTC**: **19/19 casos correctos**,
Node 26.2.0, PHP 8.3.30 y Laravel 12.70.0. Se repitió con `npm ci` desde el
lockfile. La batería cubre:

1. Captura QoS 1 de temperatura 25.5 y humedad 65, puntos distintos, coordenadas,
   instante/hora UTC, origen, fuente y actor persistidos.
2. Duplicados y fecha equivalente con zona -04:00, sin añadir filas.
3. Retained en dos arranques, preservando instante y deduplicando.
4. Conflicto con valor distinto: código 2, valor anterior intacto, sin PUBACK
   por el conflicto; permanece el commit del mensaje válido anterior.
5. JSON roto.
6. Temperatura fuera de rango.
7. Fecha futura.
8. Fecha sin zona.
9. Campos extra que intentan elegir el destino.
10. Contenido mayor a 4096 bytes.
11. Punto inexistente, sin crearlo implícitamente.
12. Punto inactivo, sin persistencia ni PUBACK.
13. Cuenta desactivada entre mensajes, revalidación y conservación del primero.
14. MQTT deshabilitado, sin conectar al broker.
15. Falta de `--conectar`, sin conectar al broker.
16. Tema ajeno no entregado; finalización acotada con cero capturas.
17. Captura QoS 0, sin PUBACK ni garantía de recuperación.
18. Sesión persistente: publicación mientras el receptor está desconectado,
    entrega al reiniciar el mismo cliente y guardado único.
19. Rechazo de conexión por el broker, sin cambios en SQLite.

Los casos de rechazo de contenido verifican código 2, estado sin nuevas filas
y ausencia de ACK. El conflicto es **atómico por mensaje**; una recepción de
varios mensajes no es una única transacción. El error no revierte commits
anteriores ni implica reconexión automática.

Resultado final: seis filas sintéticas y 12 eventos ACK de Aedes. Dos de esas
filas son la captura inicial; las otras cuatro proceden de los escenarios que
introducen lecturas nuevas. Los ACK se registran desde el evento público
`ack` del broker, separados de los PUBACK del publicador. En Aedes 0.51.3 el
paquete asociado a un ACK retained puede ser indefinido: se registra como
`null` sin inventar tema o identificador. El conteo sigue asociado al ID del
receptor; el estado se comprueba directamente en SQLite.

## Evidencia y regresión

- [Integración completa](evidencias/mqtt66/2026-11-01-integracion.json): casos,
  salidas reales del comando, suscripciones, ACK, estado final, versiones,
  huellas SHA-256 de herramientas/runtime y comprobación de cierre.
- [Fallo controlado](evidencias/mqtt66/2026-11-01-fallo-controlado.json): PHP sin
  el driver SQLite, salida de error y cierre correcto de broker, sockets,
  procesos propios y puerto. Es una prueba negativa esperada.
- [Regresión](evidencias/mqtt66/2026-11-01-regresion.json):
  `IngestionDatosTest`, **16 pruebas y 92 aserciones**, sin fallos; consulta
  posterior de procesos del harness: lista vacía. No se relanzaron las
  fixtures TCP con subprocesos PHP del lote anterior.

Comandos de validación usados, todos mediante el ejecutor Node oculto, con
`COLUMNS=100`, `LINES=40` y las funciones de lanzamiento PHP deshabilitadas:

~~~text
php.exe -c <ini-privado> -d disable_functions=exec,shell_exec,system,passthru,proc_open,popen web/vendor/bin/phpunit -c web/phpunit.xml web/tests/Feature/IngestionDatosTest.php --colors=never --do-not-cache-result
php.exe -c <ini-privado> -d disable_functions=exec,shell_exec,system,passthru,proc_open,popen web/vendor/bin/pint herramientas/datos/mqtt-local/laravel.php
node.exe --check herramientas/datos/mqtt-local/ejecutar.mjs
php.exe -c <ini-privado> -l herramientas/datos/mqtt-local/laravel.php
git.exe diff --check
~~~

PHPUnit se lanzó desde `web/` usando rutas absolutas para los archivos del
comando anterior; usó el `phpunit.xml` existente (SQLite en memoria) y
`VIEW_COMPILED_PATH` y cachés separados en `tmp/mqtt-local66/regresion/`.
Pint y las comprobaciones de sintaxis fueron correctos. No se atribuyen aquí
los resultados históricos de la suite completa de ENTREGA_DATOS66.

El broker y publicador viven solo durante la ejecución. El bloque `finally`
cierra clientes, termina hijos propios, cierra el servidor y Aedes y verifica
rechazo de conexión al puerto que acaba de usar. **No quedan servicios ni
procesos propios en ejecución.** Se conservan los archivos privados bajo
`tmp/` para inspección; no se borran datos ajenos ni se incluyen bases en Git.

## Alcance exacto para revisión

Se añaden únicamente herramientas de prueba y estos documentos/evidencias.
No se encontró un fallo del runtime GeoSur que justificara modificarlo.
No se editan dependencias de producción, migraciones, app/, móvil, referencia,
demo, roles, prod/live5791 ni otros checkouts. No hay avisos a personas,
cuentas externas, API keys, escrituras GitHub, commit o push.

**Esta evidencia acredita interoperabilidad LOCAL con un broker de uso común
y el camino Laravel/SQLite real usando mensajes sintéticos. No acredita
sensores físicos, calibración, un broker externo, TLS/certificados, ACL o
credenciales reales, PostgreSQL, tolerancia a pérdida de red, durabilidad del
broker tras reiniciarlo, despliegue, interfaz de navegador ni Flutter.**
La persistencia de Aedes usada aquí es en memoria y termina con el proceso;
solo se ensaya reiniciar el receptor mientras ese mismo broker sigue vivo.
La prueba previa de contrato TCP sigue siendo la que cubre tramas corruptas,
QoS 2 y fragmentación; no atribuir esas comprobaciones a esta batería Aedes.

Referencias oficiales consultadas solo para herramientas de prueba:
[API Aedes 0.51.3](https://github.com/moscajs/aedes/blob/v0.51.3/docs/Aedes.md),
[registro Aedes 0.51.3](https://registry.npmjs.org/aedes/0.51.3) y
[registro MQTT.js 5.10.4](https://registry.npmjs.org/mqtt/5.10.4).

## Comprobación automática

El workflow MQTT local real repite los 19 escenarios con Node 22 y PHP 8.3 en Linux y Windows. Usa las dependencias fijadas y un broker efímero de loopback; publica solo el informe saneado como artefacto. No sube la base ni el entorno privados. El resultado remoto se comprueba por separado en la interfaz de GitHub.
