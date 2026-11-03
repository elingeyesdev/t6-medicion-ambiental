# Correo SMTP local · #65 y #132

## Cambio y resultado

Cuando el servidor rechazaba definitivamente un correo, GeoSur lo mostraba como «Sin confirmar». La corrección distingue ese rechazo de una conexión que se corta sin confirmar el resultado. No reenvía automáticamente ninguno de esos mensajes.

Base de la prueba: b994b643d1b9086abd695053eb3a27030ed238b3, rama fix/132-rechazo-smtp. La ejecución final del 01/11/2026 pasó **8 pruebas SMTP / 345 comprobaciones**, **17 pruebas PHP / 109 comprobaciones** de avisos existentes y **7 pruebas JavaScript**. Pint pasó en el servicio modificado y la suite SMTP. Los resultados pertenecen a este corte; no se suman a los de otros PR.

Solo cambia un servicio de la aplicación: [TransportarAviso.php](../web/app/Services/Notificaciones/TransportarAviso.php). No requiere migraciones, habilitar envíos ni cambiar las cuentas de la demo. El resto de los archivos son el ejecutor, sus pruebas y evidencia.

## Casos comprobados

Se usa Laravel como cliente real de un servidor SMTP local, sin sustituir Mail ni el transporte. La suite recorre consentimiento y baja mediante las rutas web de pruebas, y procesa la cola con el comando existente.

- Sin consentimiento, baja y cambio de correo: no se abre una conexión SMTP para ese aviso.
- Aceptación normal: el servidor responde 250; queda aceptado y no se duplica al procesarlo otra vez.
- Segundo procesador durante el envío: no reclama de nuevo la fila. Es una llamada reentrante en un proceso PHP, no una prueba de concurrencia entre procesos del sistema.
- Rechazo definitivo 550 durante RCPT: no se recibe DATA; queda fallido / PROVEEDOR_RECHAZO.
- Rechazo temporal 450: conserva incierto, sin reintento automático.
- DATA recibido sin respuesta final: vence el tiempo de espera; queda incierto y no se repite.
- Reconexión rechazada con 554 después de aceptar DATA: conserva incierto. Ese rechazo posterior no demuestra que el mensaje anterior haya fallado.

Se capturaron tres DATA sintéticos; dos recibieron respuesta 250. Esto demuestra protocolo y estados locales, **no llegada a un buzón externo ni lectura**. No se prueban TLS, AUTH, reputación, SPF/DKIM/DMARC o proveedores externos.

## Clasificación conservadora

Solo se clasifica como rechazo definitivo una UnexpectedResponseException de Symfony, con código 500–599 y marco activo SmtpTransport::doSend, dentro del canal SMTP. El servicio inspecciona tipo, código, clase y función; no interpreta ni guarda el texto privado del proveedor.

Con Symfony Mailer 7.4.19, send ejecuta doSend antes de comprobar el reinicio de la conexión. Un error durante ese reinicio puede ocurrir después de que DATA haya sido aceptado. Por eso no basta con clasificar todos los errores 5xx como fallidos. La suite reproduce esa diferencia. Si cambia esa estructura del transporte, una traza desconocida conserva incierto y se deben repetir estos casos.

## Aislamiento y cierre

El servidor escucha en 127.0.0.1, en un puerto asignado por el sistema. Solo acepta destinos sintéticos @example.test; no consulta MX ni reenvía mensajes. No usa credenciales externas.

Cada ejecución genera su propio SQLite, entorno efímero y cachés dentro de tmp/smtp-local65/run-*. Antes de reconstruir esa base desechable se verifica su ruta exacta. No se importa un entorno ni una base de otra instalación. El cierre se ejecuta también si falla PHPUnit; la evidencia confirma puerto cerrado y cero sesiones abiertas.

El ejecutor es autónomo y usa ejecutables reales con argumentos separados, windowsHide: true y shell: false. Todos los procesos PHP reciben COLUMNS=100, LINES=40 y tienen deshabilitadas exec, shell_exec, system, passthru, proc_open y popen. La prueba verifica estas guardas antes de iniciar Laravel. La salida se redirige a archivos; no se abre CMD ni PowerShell.

Dependencias del servidor: smtp-server 3.21.3 y nodemailer 10.3.1 fijadas en el lockfile propio. Se instalan en tmp con npm ci --ignore-scripts; no se cambian las dependencias de la aplicación. El cliente es Laravel/Symfony.

## Reproducción en Windows sin ventanas

Requisitos: Node >=20, PHP con mbstring, pdo_sqlite, sqlite3, curl y openssl; Git y web/vendor correspondiente al composer.lock. No copiar entornos ni bases para preparar vendor. La instalación npm usa el registro público; los mensajes usan solo loopback.

Desde un proceso Node en segundo plano, con rutas ajustadas al equipo:

~~~javascript
const out = await fs.open(root + '/tmp/smtp-stdout.log', 'w');
const err = await fs.open(root + '/tmp/smtp-stderr.log', 'w');
const child = spawn(nodeExe, [
  root + '/herramientas/avisos/smtp-local/run.mjs',
  '--php', phpExe, '--git', gitExe, '--regresion'
], { cwd: root, windowsHide: true, shell: false,
  stdio: ['ignore', out.fd, err.fd] });
child.once('close', code => { /* revisar código y logs */ });
await out.close();
await err.close();
~~~

fs es node:fs/promises y spawn pertenece a node:child_process. --npm-cli permite indicar el archivo JavaScript real de npm; no se invoca npm.cmd. --regresion exige que este checkout no tenga web/.env ni web/.env.testing y ejecuta los avisos existentes después de cerrar SMTP.

El INI propio usa el directorio ext situado junto a php.exe. Este procedimiento se comprobó en Windows con PHP 8.3.30, Laravel 12.70.0 y Symfony Mailer 7.4.19. No se declara compatibilidad con Linux de este ejecutor.

## Evidencia

[Índice y resultados](evidencias/smtp65/README.md), [resultado estructurado](evidencias/smtp65/resultado.json), [salida SMTP](evidencias/smtp65/phpunit.txt) y [captura sintética del protocolo](evidencias/smtp65/smtp.json).

Se conservan aparte la prueba original de seis casos, la prueba roja de #132 y el corte previo a las guardas PHP. El resultado vigente está en la raíz de smtp65. Los archivos efímeros, claves y bases permanecen fuera de Git.

Fuentes primarias: [SMTP Server](https://nodemailer.com/extras/smtp-server), [paquete fijado](https://registry.npmjs.org/smtp-server/3.21.3) y código Symfony instalado según composer.lock.
