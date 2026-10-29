# Cuentas de GeoSur — #63

Recuperación de `Backend/Src/modules/auth/`, `modules/usuarios/`, sus esquemas y `Frontend/src/pages/Usuarios/`. El referente permanece intacto. Las cuentas de prueba son nuevas; no se importan usuarios ni contraseñas del original.

## Recorridos disponibles

- Registro, ingreso y salida en Blade y API. Toda cuenta nueva comienza como **visualizador**, aunque intente mandar otro rol o estado.
- **Mi cuenta** permite cambiar el nombre y guardar o quitar una ubicación de referencia en Mapbox, también con el teclado. El correo permanece como identificador de acceso.
- **Usuarios** permite al administrador buscar, listar, asignar roles y suspender/reactivar. Laravel rechaza las peticiones directas sin permiso. Otro administrador debe cambiar la cuenta propia; no es posible quitarse la administración.
- Cambiar rol o estado invalida sesiones web y revoca tokens móviles anteriores. Suspender conserva la cuenta y su historial.
- Recuperación con enlace de un solo uso, válido **15 minutos**. Cambiar la contraseña revoca accesos previos y obliga a ingresar otra vez. El enlace usa `APP_URL`, no el host enviado por el cliente.

Roles del origen: `admin`, `analista`, `visualizador`. Administrador gestiona usuarios; administrador y analista pueden gestionar simulaciones, alertas y crear reportes; visualizador consulta mapa y reportes. Declarar esos permisos **no completa** #52/#64/#65: sus rutas deberán comprobarlos antes de habilitar las funciones.

## Instalación local

1. Respaldar la base y configuración privada. Desde `web/`, ejecutar `composer install` y `php artisan migrate`. Son dos migraciones nuevas; **no** usar `migrate:fresh` sobre una instalación existente.
2. Las cuentas existentes conservan datos y comienzan como visualizador. No se promueve ninguna automáticamente.
3. Para iniciar administración local: registrar una cuenta propia y ejecutar `php artisan geosur:primer-admin correo-de-la-cuenta --confirmar`. Solo funciona en local/testing y se niega si ya hay un administrador activo. No crea cuentas ni cambia contraseñas; obliga a volver a ingresar.
4. Configurar `APP_URL` con la dirección correcta de esa instalación.

En `local` y `testing`, recuperación usa obligatoriamente el correo **log**: el mensaje queda en el almacenamiento privado de Laravel, sin enviarse a nadie. Contiene un enlace privado: no adjuntarlo a capturas, PR o comentarios. Transporte real y acceso desde otro dispositivo quedan para publicación.

PHPUnit crea usuarios con fábricas sobre SQLite en memoria. Las capturas usan otra SQLite temporal con cuatro cuentas sintéticas. Esa base y sus accesos no se guardan en Git.

## Rutas para Flutter

Base `/api/v1`, cuerpos JSON, `Accept: application/json`. Las rutas privadas requieren `Authorization: Bearer <token>` y responden con `Cache-Control: no-store`. La sesión de Blade conserva CSRF y no sustituye ese token.

- `POST /cuenta/registrar`: `name`, `email`, `password`, `password_confirmation`; devuelve 201, usuario y autorización móvil.
- `POST /cuenta/ingresar`: `email`, `password`; devuelve `usuario`, `token`, `tipoToken`, `venceEn`. El token se muestra solo al emitirlo, se guarda protegido en la base y vence en 24 horas.
- `GET /cuenta`: `usuario` y `preferencias` propios. Usuario incluye `id`, `nombre`, `email`, `rol`, `rolNombre`, `activo`, `permisos`.
- `PUT /cuenta/nombre`: `name`; rechaza cambiar correo, estado o rol.
- `PUT /cuenta/preferencias`: `latitud` y `longitud` juntas, o ambas `null` para quitar ubicación. Conserva opciones no enviadas. También conserva los campos originales `notif_email`, `notif_whatsapp`, `whatsapp_destino`, `notif_telegram`, `telegram_destino`. Guardarlos **no envía avisos**; sin ubicación se desactivan los canales. Los canales y sus controles visibles se completan en #65.
- `POST /cuenta/salir`: revoca solo el token usado, sin cerrar otros dispositivos.
- `POST /cuenta/recuperar`: `email`; respuesta genérica, sin devolver enlace ni token.
- `POST /cuenta/restablecer`: `email`, `token` recibido por correo, `password`, `password_confirmation`; no inicia sesión. También puede completarse con el formulario web del enlace.
- Solo administrador: `GET /usuarios?pagina=1`, `PATCH /usuarios/{id}/rol` con `rol`, `PATCH /usuarios/{id}/estado` con `activo` booleano. La lista devuelve `datos`, `pagina`, `total`, `ultimaPagina`, `roles`; nunca contraseñas, tokens ni preferencias ajenas.

Errores usan el sobre `error`: 401 acceso inválido/vencido, 403 sin permiso, 422 con `campos` para corregir el formulario y 429 para esperar. Cinco fallos de ingreso por correo/IP bloquean un minuto, compartido entre web y móvil. Hay límites adicionales por IP, separados por operación para evitar contar dos veces una petición de API.

Flutter debe guardar el token en almacenamiento seguro y retirarlo al salir o recibir 401. Los permisos sirven para presentar opciones; Laravel decide si una operación se permite. **No se modificó Flutter** aquí: faltan conexión y pruebas con Jaziel en #48/#63.

## Código y pruebas

Validaciones: `Http/Requests/Cuentas`. Reglas compartidas: `Services/Cuentas`. Roles: `Enums/RolCuenta`. Pantallas: `resources/views/cuentas`. CSS: `public/css/paginas/cuentas.css`. Mapa del perfil: `public/js/cuentas/ubicacion.js`. Usa componentes y controles de **Tabler 1.7.1**, sin modificar vendor.

`CuentasTest`, `ApiCuentasTest`, `RecuperacionAccesoTest` y las pruebas anteriores de acceso comprueban permisos directos, registro sin privilegios, CSRF, invalidación de sesiones, tokens vencidos/revocados, ubicación propia, enlace vencido/reutilizado, límites y ausencia de envíos. Administración, emisión de tokens y recuperación usan transacciones. Falta prueba de concurrencia sobre PostgreSQL; las pruebas locales usan SQLite.

Base técnica: [Laravel Sanctum](https://laravel.com/framework/docs/12.x/sanctum) y [recuperación de contraseñas de Laravel](https://laravel.com/framework/docs/12.x/passwords). Se usa el broker persistente del framework en lugar del código temporal en memoria del referente.

Pendientes: revisión de Jaziel, integración Flutter, correo real al publicar y canales/avisos #65. No completa toda la recuperación de EnviroSense ni cambia M2 publicada.
