# Web y API de GeoCruz

Base de #15: Laravel 12, Blade y SQLite local. Responsable: Josué; revisión del PR: Jaziel. `composer.lock` fija las dependencias. Composer resuelve para PHP 8.2; CI comprueba PHP 8.2 y 8.3. No se necesitan Node, NPM, Vite ni servicios externos para esta base: la hoja CSS se sirve desde `public/`.

## Requisitos

- PHP 8.2 o superior compatible con `composer.lock` y Composer 2.
- Extensiones: Ctype, cURL, DOM, Fileinfo, Filter, Hash, Mbstring, OpenSSL, PCRE, PDO, PDO SQLite, Session, Tokenizer y XML. SQLite3 se incluye en el ejecutor Windows.
- `storage/` y `bootstrap/cache/` deben permitir escritura.
- Git para obtener la rama oficial. La carpeta raíz también conserva `movil/` y `compartido/`.

## Instalación y arranque

Con PHP configurado, desde `web/`:

```sh
composer install
composer run setup
composer run dev
```

`setup` crea `.env` a partir de `.env.example` si hace falta, genera `APP_KEY` únicamente si está vacía y crea el archivo SQLite si no existe. Repetirlo conserva la clave, la configuración y la base existentes. La configuración usa sesiones y caché de archivos y cola síncrona: no necesita un worker ni un servidor SQL para arrancar. Las migraciones originales de Laravel están disponibles como scaffolding; no se habilitan cuentas, login o funciones administrativas. No son necesarias para estas páginas y la salud.

URLs por defecto:

- Inicio: http://127.0.0.1:8000
- Ejemplo de navegación: http://127.0.0.1:8000/modulos/focos-calor
- Salud JSON: http://127.0.0.1:8000/api/salud
- Diagnóstico nativo Laravel: http://127.0.0.1:8000/up

Detener con Ctrl+C. Para otro puerto: `php artisan serve --host=127.0.0.1 --port=8001` y ajustar `APP_URL` local. Cambiar `.env` requiere reiniciar el servidor; después de editar configuración ejecutar `php artisan config:clear`.

### Windows con PHP portable

Desde la raíz del repositorio:

```powershell
.\preparar.ps1
.\iniciar.ps1
```

`preparar.ps1` obtiene Composer oficial y su SHA256, descarga el almacén de certificados de curl y ejecuta la instalación fijada. Se necesita acceso HTTPS y 7-Zip en PATH o la extensión Zip para extraer las dependencias. Los binarios, certificados y la configuración local de PHP se guardan en `tmp/`, excluido de Git. El ejecutor conserva la configuración global de PHP y la variable PHPRC previa. No desactiva la validación TLS.

Para ejecutar comandos en esta modalidad, situarse en `web/`:

```powershell
..\herramientas\php.ps1 artisan test --compact
..\herramientas\php.ps1 artisan route:list --except-vendor
..\herramientas\php.ps1 vendor/bin/pint --test
```

## Preparación de series de sequías

Preparación de sequías (#36): [validador de series diarias por consola](../docs/API02_PREPARACION_SERIES.md), con ejemplos completos/incompletos y pruebas sin red. Este avance de calidad de datos mantiene la consulta HTTP de sequías en preparación mientras se revisa el método #35.

También hay un [ensayo SPI-3 en PHP](../docs/API02_ENSAYO_SPI.md), aislado como experimental y contrastado con la referencia SciPy. En este equipo se prueba desde la raíz con `./herramientas/sequias/probar-spi.ps1`. Sigue pendiente de revisión del método y no activa la API.

## Configuración y secretos

`.env.example` no contiene credenciales. `.env`, la clave de la aplicación, la base SQLite, los logs y `vendor/` quedan fuera de Git. `APP_DEBUG=false` evita exponer trazas en el arranque por defecto. `APP_KEY` es propia de cada instalación: no compartirla con Flutter ni incorporarla a commits.

Las claves NASA y de otros proveedores se añadirán solo al backend en las tareas de integración. Esta base no las solicita ni realiza consultas a proveedores. Las configuraciones de correo, almacenamiento y usuario que conserva Laravel son scaffolding, no integraciones activas.

## Salud y contrato

`GET /api/salud` es público, de lectura y sin sesión. Devuelve HTTP 200, `Content-Type: application/json` y `Cache-Control: no-store` con:

```json
{
  "servicio": "GeoCruz",
  "estado": "disponible",
  "alcance": "aplicacion",
  "fecha": "2026-10-15T20:30:00+00:00"
}
```

La fecha real se genera en UTC en cada respuesta. El ejemplo describe el formato. Esta ruta verifica que la aplicación puede responder; no comprueba SQLite, NASA, catálogos, exactitud ambiental ni disponibilidad de los módulos. `/up` es la comprobación nativa de Laravel y devuelve 500 si falla un diagnóstico registrado.

Las rutas `/api/*` devuelven errores JSON incluso sin encabezado Accept. Esta rama implementa `/api/v1/territorios`, su límite y `/api/v1/focos-calor` con NASA FIRMS en backend. Contrato candidato, filtros, paginación y errores: [API compartida](../compartido/api/README.md). El [visor de #19](../docs/WEB01_VISOR_FOCOS_CALOR.md) consume ese contrato en `/modulos/focos-calor`; los otros cuatro módulos conservan su pantalla pendiente y API 503. No se convierten fallos en simulaciones. `/api/salud` conserva su contrato operativo independiente.

## Pruebas

```sh
composer validate --strict
composer lint
composer test
```

PHPUnit fuerza entorno `testing`, SQLite `:memory:`, sesiones/caché en memoria, correo de prueba y cola síncrona. La clave fija de `phpunit.xml` es exclusivamente un fixture de pruebas. Se bloquean las solicitudes HTTP no simuladas desde el TestCase común. Ejecutar pruebas no requiere `.env` ni modificar la base local.

Se comprueban navegación, estados pendientes, 404 y 405, salud sin sesión/proveedores/base de datos, diagnóstico `/up`, escritura SQLite en memoria y bloqueo de HTTP externo. La integración continua ejecuta las verificaciones en los PR a `develop`/`main`. Una ejecución verde no acredita integración NASA o funcionamiento de Flutter.

## Conexión de Jaziel y siguientes tareas

[Desarrollo compartido](../compartido/DESARROLLO.md) explica las URLs de PC, emulador Android y dispositivo físico. Catálogo: #16; Flutter: #17; API ambiental: #18; visor web: #19. Los PR se revisan de forma cruzada antes de integrarlos a `develop`.

Referencias de Laravel 12: [instalación](https://laravel.com/docs/12.x/installation), [salud](https://laravel.com/docs/12.x/deployment#the-health-route) y [pruebas](https://laravel.com/docs/12.x/testing).
