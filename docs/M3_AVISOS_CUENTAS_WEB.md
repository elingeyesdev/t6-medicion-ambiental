# M3 · Avisos y cuentas web (#65 y #63)

Lote aislado en `tmp/m3-avisos-web`, rama `feature/65-avisos-envirosense`, iniciado en `c8b6b7a`. Solo Laravel, módulos de avisos/cuentas y documentación. No modifica móvil, las fuentes ambientales, los roles, la demo, las rutas compartidas, `User.php`, los catálogos de permisos ni `bootstrap/app.php`.

## Resultado implementado

- Los avisos internos existentes encolan sus canales externos dentro de la misma transacción. No se llama a proveedores desde la captura de lecturas ni desde una petición web. La outbox es una cola persistente propia y no depende de `QUEUE_CONNECTION=sync` ni de instalar Redis.
- Correo log/SMTP, Telegram Bot API, WhatsApp Cloud API con plantilla y Firebase Cloud Messaging HTTP v1 para navegador. Todos desactivados por defecto. No se reutiliza la sesión personal de WhatsApp del referente.
- Consentimiento explícito por destino y usuario, fecha y huella HMAC. Los flags de preferencias antiguos por sí solos no autorizan mensajes. Correo, Telegram y WhatsApp conservan el contrato de preferencias de cuenta existente. Push almacena su token cifrado en una tabla propia y admite un navegador por cuenta.
- Respeta suscripción a variables, simulados elegidos, cuenta activa, ubicación/radio y antigüedad máxima de quince minutos. Recomprueba esos datos y el destino justo antes de transportar. Una baja o cambio de destino cancela lo pendiente; no redirige avisos anteriores al contacto nuevo.
- Un envío como máximo por aviso/canal. Reclamo atómico entre procesadores, con la red fuera de cualquier transacción. Reintenta únicamente el rechazo explícito por límite del proveedor, máximo tres intentos, respetando su espera y con mínimo de 60 segundos. No reenvía automáticamente un resultado incierto.
- Bandeja propia con contador, cambios de lectura, estados y paginación actualizados por consultas cortas cada diez segundos. Se detiene al vencer la sesión, conserva el contenido ante errores y el foco de quien está usando una acción. Pestaña oculta: no consulta. Error: espera progresiva hasta un minuto. No usa SSE ni long polling que ocupen `artisan serve`.
- Cambio de contraseña desde Mi cuenta, con contraseña actual, confirmación, mínimo 12 caracteres y máximo 72 bytes. Revoca tokens, enlaces de recuperación y las otras sesiones; renueva la sesión actual. Ningún formulario modifica roles ni otra cuenta.
- Recuperación local con registro de correo dedicado, incluso si el log general filtra `debug`. Respuesta genérica ante cuentas inexistentes, suspendidas o errores del transporte. No expone mensajes privados del proveedor.

## Integración exacta

1. Incorporar los commits de esta rama desde el checkout que use el integrador. Este lote no crea PR, no publica, no integra `main`/`develop` y no cambia el checkout activo de otros trabajos.
2. Aplicar las migraciones nuevas `2026_10_29_004001_crear_outbox_avisos.php` y `2026_10_29_004002_guardar_suscripcion_push_web.php` mediante el procedimiento habitual del integrador. Aquí solo se ejecutan las migraciones de pruebas sobre SQLite en memoria; no se abre una base existente ni se ejecutan/importan SQL del original.
3. Copiar el bloque de [rutas exactas](integracion/m3-avisos-rutas.php) en `web/routes/web.php`, fuera de los otros grupos. Si se copian los `use`, unirlos con los ya existentes, sin duplicarlos. No requiere rutas API nuevas. Incluye `avisos.novedades`, `avisos.canales`, `avisos.push`, `avisos.servicio-push` y `cuenta.clave`, con sesión, cuenta activa, permisos y límites.
4. Dentro de `withExceptions(function (Exceptions $exceptions): void { ... })` de `web/bootstrap/app.php`, añadir este registro después del manejador existente:

```php
$exceptions->render(require base_path('../docs/integracion/m3-avisos-excepciones.php'));
```

El [callback exacto](integracion/m3-avisos-excepciones.php) solo traduce el `ErrorConsulta` de cuenta/sesión para estas peticiones JSON web. También puede copiarse su closure directamente dentro de `$exceptions->render(...)`. Evita que una sesión vencida devuelva 500. Los tests registran exactamente ambos archivos de integración sin modificar rutas ni bootstrap.

5. Para revocar el navegador al cerrar sesión en equipos compartidos, en `AccesoController::salir`, **antes** del `Auth::logout()`/invalidación de sesión existente, insertar:

```php
app(\App\Services\Notificaciones\GuardarPushAvisos::class)
    ->ejecutar($request->user(), false);
```

Este controlador pertenece a otro alcance y no se editó. El servicio cancela la outbox pendiente y elimina el destino cifrado propio. La transferencia del mismo navegador a otra cuenta ya revoca la suscripción anterior. Las bajas no pueden retirar mensajes que un proveedor ya haya aceptado.

No hay cambios pendientes en `User.php`, permisos, providers ni dependencias Composer. Laravel descubre el comando propio en `app/Console/Commands`.

## Configuración privada por entorno

Valores por defecto; no pegar credenciales reales en documentación, comandos compartidos ni Git:

```dotenv
AVISOS_PROCESAR=false
AVISOS_EMAIL_HABILITADO=false
AVISOS_EMAIL_MAILER=log
AVISOS_TELEGRAM_HABILITADO=false
AVISOS_TELEGRAM_TOKEN=
AVISOS_WHATSAPP_HABILITADO=false
AVISOS_WHATSAPP_TOKEN=
AVISOS_WHATSAPP_TELEFONO_ID=
AVISOS_WHATSAPP_VERSION=
AVISOS_WHATSAPP_PLANTILLA=
AVISOS_WHATSAPP_IDIOMA=es
AVISOS_PUSH_HABILITADO=false
AVISOS_PUSH_PROYECTO=
AVISOS_PUSH_CLIENTE_EMAIL=
AVISOS_PUSH_CLAVE_PRIVADA=
AVISOS_PUSH_API_KEY_PUBLICA=
AVISOS_PUSH_APP_ID=
AVISOS_PUSH_SENDER_ID=
AVISOS_PUSH_VAPID_PUBLICA=
MAIL_TIMEOUT=10
```

- Correo: solo `log` o `smtp`, sin fallback que confunda registro local con envío externo. SMTP usa la configuración `MAIL_*` ya existente. `log` escribe en `web/storage/logs/correo-local.log`, con estado **Registrado en correo local** y texto explícito de que no llegará al buzón. Recuperación en local/testing o con `MAIL_MAILER=log` usa ese mismo registro. El archivo contiene destinatarios y enlaces privados: es ignorado y no se entrega.
- Telegram: token del bot y ID positivo de chat privado declarado por el usuario. Texto plano; no se interpretan HTML/Markdown del dato. No hay listener, chatbot ni polling de Telegram.
- WhatsApp: cuenta empresarial, número del emisor, versión vigente elegida por la instalación y plantilla aprobada. Contrato de plantilla: cuatro parámetros de cuerpo posicionales, en orden **origen**, **lugar**, **variable/valor/nivel**, **fecha en Bolivia**. El destino se valida con código de país. No acepta un destino libre convertido arbitrariamente en teléfono.
- Push: proyecto con Cloud Messaging, cuenta de servicio autorizada para enviar, clave privada PEM por entorno (también admite saltos `\n`), configuración pública de la app web y clave pública VAPID. OAuth JWT RS256 nativo, scope `firebase.messaging`, intercambio en Google y token temporal en almacenamiento privado de Laravel. La clave privada nunca se incluye en la página/worker. El token de navegador queda cifrado mediante `APP_KEY`; no rotarla sin el procedimiento de claves de la instalación.
- El navegador requiere HTTPS o localhost y soporte de notificaciones/service worker. El SDK oficial Firebase **13.0.0** se carga desde gstatic solo al pulsar Activar y conceder permiso. No se añade Composer/npm ni un daemon. La configuración pública Firebase se expone intencionalmente; las credenciales de servicio son privadas.

El comando no se ejecuta durante las pruebas con proveedores reales ni se instala ninguna tarea automática. Una instalación que habilite expresamente sus canales y autorice el procesamiento puede ejecutar en una consola separada:

```powershell
./herramientas/php.ps1 web/artisan avisos:procesar --procesar --limite=50
```

Además requiere `AVISOS_PROCESAR=true`. El límite admite 1–100 registros; el comando procesa un solo lote y termina. La cadencia de operación corresponde al integrador. No ocupa el proceso que atiende peticiones web. No se activó un scheduler en esta entrega.

## Estados y límites de confirmación

- `pendiente`, `en_proceso`, `reintento`: esperando procesamiento/confirmación.
- `registrado_local`: correo escrito en el log local, sin recepción en buzón.
- `aceptado`: el transporte SMTP o la API del proveedor lo aceptó. **No prueba recepción ni lectura**.
- `fallido`: rechazo definitivo, autorización fallida o límite de intentos agotado.
- `cancelado`: preferencias, baja, destino, ubicación, cuenta o vigencia ya no permiten el envío.
- `incierto`: timeout, respuesta inconsistente, error 5xx o proceso interrumpido después de reclamar un envío. No se repite automáticamente para evitar duplicados. Un `en_proceso` de más de dos minutos pasa a este estado al ejecutar el comando.

Se guardan códigos de error acotados; nunca la URL con token de Telegram, el cuerpo privado de la respuesta ni el mensaje de la excepción del proveedor. La outbox no duplica contactos ni tokens. No hay reenvío manual que eluda el consentimiento ni afirmación de entrega exactamente una vez fuera del sistema.

## Verificación y límites restantes

Las pruebas usan `.env.example` copiado solo en este worktree, SQLite `:memory:`, fakes HTTP que prohíben tráfico no declarado, transporte SMTP sustituido por `array`, log de correo local y clave RSA efímera generada únicamente en memoria. `vendor` es una copia ignorada independiente del checkout fuente. Las vistas se compilan en `web/storage/framework/views-pruebas`, separadas de los otros agentes.

Comando reproducible desde este worktree, con PHP disponible en PATH:

```powershell
$env:VIEW_COMPILED_PATH = (Resolve-Path web/storage/framework/views-pruebas).Path
./herramientas/php.ps1 web/vendor/phpunit/phpunit/phpunit --configuration web/phpunit.xml
node --test web/tests/Js/avisos.test.mjs web/tests/Js/push-avisos.test.mjs
```

Cobertura específica: contratos de los cuatro canales; firma JWT y cache de OAuth; consentimiento; destinos inválidos/faltantes; revalidación antes de transportar; rollback; deduplicación; segundo procesador concurrente; límites y reintentos; estados locales/inciertos; respuestas privadas; cuenta activa y propiedad; CSRF; permisos sin autoelevación; sesiones revocadas; lectura; cambios de contraseña; fallos de recuperación; JS sin permisos automáticos, errores, foco y sesión vencida.

Resultado final del 29/10/2026 en PHP 8.3.30: **410 pruebas PHP, 3927 comprobaciones, sin fallos** (suite completa); **7 pruebas JS, sin fallos**; **Pint pasa** para todos los PHP del lote y `git diff --check` limpio. Se comprueba además que `movil/`, rutas web/API, bootstrap, `User.php` y el referente no tienen diferencias respecto del commit de partida. Los resultados son de este worktree; no se atribuyen al checkout activo ni a conexiones reales.

**Conexiones no ejecutadas:** SMTP real, Telegram real, WhatsApp real, intercambio OAuth real y recepción push real. No se tienen ni se incorporaron credenciales de proveedores. No se envió un aviso a personas, no se importaron contactos/cuentas/bases del referente y no se probó una recepción en un dispositivo real. Los tests prueban los contratos con transporte controlado; la prueba externa autorizada sigue a cargo de la instalación.

Push conserva un navegador por cuenta y necesita reactivación si el navegador rota/elimina su token. La baja en servidor impide futuros envíos; no cambia el permiso del sistema operativo. El consentimiento de Telegram/WhatsApp es declarado por el usuario, sin verificación OTP de titularidad. No se añade un cliente Flutter ni cambios en `movil/`. La integración de rutas/bootstrap/logout y la habilitación operativa están deliberadamente reservadas al integrador.

## Referencias de contratos

Se leyó el código original inmutable de `notifications/notification.service.js`, `notificaciones/notificaciones.service.js`, `notificaciones.controller.js`, `telegram.listener.js`, `utils/telegram.js`, `utils/mailer.js`, `utils/whatsapp.js` y los módulos de usuarios/cuenta. Sin ejecutar su SQL ni copiar sus datos.

Contratos oficiales consultados: [Telegram sendMessage](https://core.telegram.org/bots/api#sendmessage), [componentes de plantilla WhatsApp de Meta](https://whatsapp.github.io/WhatsApp-Nodejs-SDK/api-reference/types/component_object/), [FCM HTTP v1](https://firebase.google.com/docs/cloud-messaging/send/v1-api), [FCM web](https://firebase.google.com/docs/cloud-messaging/web/get-started), [recepción y worker web](https://firebase.google.com/docs/cloud-messaging/web/receive-messages).

## Archivos del lote

- `docs/ACCESO_ENVIROSENSE.md`
- `docs/ALERTAS_ENVIROSENSE.md`
- `docs/integracion/m3-avisos-excepciones.php`
- `docs/integracion/m3-avisos-rutas.php`
- `docs/M3_AVISOS_CUENTAS_WEB.md`
- `web/app/Console/Commands/ProcesarAvisos.php`
- `web/app/Http/Controllers/AvisosController.php`
- `web/app/Http/Controllers/CuentaController.php`
- `web/app/Http/Controllers/RecuperacionController.php`
- `web/app/Http/Requests/Cuentas/CanalesAvisosRequest.php`
- `web/app/Http/Requests/Cuentas/ClaveRequest.php`
- `web/app/Http/Requests/Cuentas/PushAvisosRequest.php`
- `web/app/Models/DestinoPushAviso.php`
- `web/app/Models/EnvioAviso.php`
- `web/app/Models/SuscripcionCanalAviso.php`
- `web/app/Notifications/Canales/RecuperacionCorreoLocal.php`
- `web/app/Notifications/RecuperarAcceso.php`
- `web/app/Services/Alertas/PrepararAvisos.php`
- `web/app/Services/Cuentas/CambiarClave.php`
- `web/app/Services/Cuentas/GuardarCanalesAvisos.php`
- `web/app/Services/Notificaciones/AccesoFirebaseAvisos.php`
- `web/app/Services/Notificaciones/CanalesAvisos.php`
- `web/app/Services/Notificaciones/CorreoLocal.php`
- `web/app/Services/Notificaciones/EncolarAvisos.php`
- `web/app/Services/Notificaciones/GuardarPushAvisos.php`
- `web/app/Services/Notificaciones/ProcesarOutboxAvisos.php`
- `web/app/Services/Notificaciones/TransportarAviso.php`
- `web/config/avisos.php`
- `web/config/mail.php`
- `web/database/migrations/2026_10_29_004001_crear_outbox_avisos.php`
- `web/database/migrations/2026_10_29_004002_guardar_suscripcion_push_web.php`
- `web/public/js/avisos/actualizar.js`
- `web/public/js/avisos/push.js`
- `web/resources/views/alertas/avisos.blade.php`
- `web/resources/views/alertas/canales.blade.php`
- `web/resources/views/alertas/lista-avisos.blade.php`
- `web/resources/views/alertas/push.blade.php`
- `web/resources/views/cuentas/perfil.blade.php`
- `web/tests/Feature/AvisosExternosTest.php`
- `web/tests/Feature/PerfilClaveTest.php`
- `web/tests/Feature/PushWebAvisosTest.php`
- `web/tests/Feature/RecuperacionAccesoTest.php`
- `web/tests/Fixtures/avisos-openssl.cnf`
- `web/tests/Js/avisos.test.mjs`
- `web/tests/Js/push-avisos.test.mjs`
