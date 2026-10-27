# Inicio, acceso y mapa de EnviroSense

Seguimiento #53 y #47, PR #51. Cambio solicitado por Josué el 27/10/2026: conservar el recorrido de EnviroSense y añadir los módulos de GeoCruz dentro de él. La aprobación de Jaziel para #45 ya quedó integrada en develop; esta ampliación necesita su propia revisión.

## Recorrido

`/` es la portada de EnviroSense. Desde **Entrar al mapa**, una visita sin sesión pasa a `/login`; puede crear su cuenta en `/register`. Al entrar se recupera la consulta que intentaba abrir o se llega a `/mapa`. La cabecera muestra la cuenta y permite **Cerrar sesión**. Focos y sequías mantienen sus direcciones y consultas.

El sistema conserva el nombre, símbolo, tipografía, turquesa, cabecera y navegación del referente. La portada adapta LandingHeader/LandingHero y el acceso adapta AuthHero/Auth.css. Se usa **Tabler 1.7.1** para formularios, botones, tarjetas y diseño responsive; sus archivos originales, versión y licencia están en `web/public/vendor/tabler/`. Los ajustes propios están separados. No se presentan cifras globales, funciones vacías ni resultados de demostración del proyecto anterior.

La imagen de la portada se construye con las quince provincias del catálogo territorial. Es un SVG de contexto geográfico, sin datos ambientales ni imágenes generadas. Fuente y reconstrucción: `web/public/img/envirosense/LEEME.md`.

## Código

- `web/resources/views/layouts/publico.blade.php`: recursos comunes de portada y acceso.
- `web/resources/views/paginas/inicio.blade.php`: portada breve y módulos disponibles.
- `web/resources/views/layouts/acceso.blade.php`: distribución de dos columnas del acceso.
- `web/resources/views/acceso/`: formularios de ingreso y registro.
- `web/resources/views/components/panel/marca.blade.php`: la misma marca en todas las pantallas.
- `web/public/css/paginas/publico.css`: estilos de portada/acceso sobre Tabler.
- `web/app/Http/Controllers/AccesoController.php`: ingreso, registro y salida.
- `web/app/Http/Requests/Acceso/`: validación y normalización de formularios.
- `web/routes/web.php`: portada pública, acceso de invitados y consultas con sesión.
- `web/tests/Feature/AccesoTest.php`: protección de rutas, cuentas, sesiones, CSRF y límite de intentos.

Las contraseñas se guardan con el hash del modelo User de Laravel. No se guardan tokens de cuenta en localStorage. El ingreso renueva la sesión; salir la invalida y renueva CSRF. Se limitan intentos y el error de credenciales no distingue si el correo existe. El registro conserva una cuenta común, sin aceptar roles enviados desde el formulario. No se reutilizan usuarios, claves ni bases privadas del referente.

La API común de datos que consume Flutter conserva su contrato público y sus límites. El nuevo acceso protege las pantallas web; no introduce un token obligatorio para los endpoints aceptados ni cambia el cálculo ambiental. Los detalles extensos de fuente y método siguen disponibles en **Fuente y detalles**, con los avisos de error o consulta anterior visibles.

## Arranque y prueba

En este PC, desde `C:\Users\cjosu\Desktop\GeoCruz-oficial\tmp\panel-envirosense`, ejecutar `./iniciar-web.ps1 -Puerto 5791` y abrir http://127.0.0.1:5791/. El iniciador prepara las tablas con `artisan migrate`; no elimina ni reinicia datos. Otra instalación requiere dependencias, .env y base configurados según el README. El servidor PHP alternativo no prepara las tablas: ejecutar primero las migraciones.

1. Abrir la portada y pulsar **Entrar al mapa**: debe pedir el acceso.
2. Crear una cuenta de prueba. Debe entrar al mapa; la contraseña requiere al menos 12 caracteres.
3. Probar focos: territorio, fechas, consulta, selección y ficha. Comprobar la fuente en el desplegable.
4. Abrir sequías y su piloto histórico; la publicación debe estar disponible y vigente en esa instalación.
5. Cerrar sesión; intentar abrir `/mapa` o un módulo y comprobar que vuelve al acceso.
6. Probar una contraseña incorrecta, luego la correcta. Repetir el recorrido en pantalla pequeña.

No se incluyen cuentas ni contraseñas comunes en el repositorio. El piloto y la clave de NASA continúan como configuración/datos privados del backend. Evidencia de esta copia: `docs/evidencias/acc53/`.

Referencia técnica: [autenticación de Laravel 12](https://laravel.com/docs/12.x/authentication), [validación](https://laravel.com/docs/12.x/validation) y [límite de intentos](https://laravel.com/docs/12.x/rate-limiting).
