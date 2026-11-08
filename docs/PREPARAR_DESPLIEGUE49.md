# Preparación del servidor para revisar en #49

Propuesta de imagen PHP 8.3 con Apache y PostgreSQL. Se construye desde la raíz del repositorio para conservar web y compartido. El arranque lee PORT, usa el directorio público de Laravel y exige una APP_KEY persistente. El contexto excluye referencias, móvil, temporales, dependencias locales, bases SQLite y archivos .env.

## Construcción y prueba de contenedores

El 07/11/2026 pasó la [ejecución de GitHub Actions](https://github.com/elingeyesdev/t6-medicion-ambiental/actions/runs/42415348983) sobre el commit a790866. Se construyó la imagen desde el repositorio, se ejecutaron las migraciones en PostgreSQL 16 temporal y respondieron salud, login y catálogo territorial. Un archivo de prueba en storage sobrevivió al reemplazo del contenedor web con el mismo volumen.

El script herramientas/despliegue/probar-contenedor.sh reproduce esta comprobación en un equipo Linux con Docker, Bash, curl, OpenSSL y Python 3. Sus contenedores, red, volumen y credenciales son exclusivos de la prueba y se retiran al terminar. La ampliación del 08/11 añade una prueba de copia y recuperación en recursos nuevos; su resultado se registra abajo cuando termine. Aún faltan HTTPS detrás del proxy, publicaciones ambientales reales y la prueba desde Flutter en la URL final. El motor local de Docker continúa apagado.

Cuando el motor esté disponible, construir desde la raíz con `docker build -t geosur-web:revision .`. Mantener un archivo de variables local fuera de Git. Usar una base PostgreSQL y un volumen nuevos para la prueba. Ejemplo de arranque con un puerto local acotado:

`docker run --rm --name geosur-web-revision -p 127.0.0.1:8080:8080 --env-file RUTA_ENV --mount source=geosur-revision-storage,target=/var/www/geosur/web/storage geosur-web:revision`

El volumen persiste publicaciones, series, sesiones y otros archivos de storage. Preparar los datos reales mediante los comandos del proyecto. Una carpeta vacía seguirá dando producto no disponible en las consultas que necesitan publicaciones. Revisar copia y recuperación del volumen antes de usarlo para una entrega.

## Variables y base de pruebas

APP_KEY se genera una vez fuera de Git con `php artisan key:generate --show` y se conserva entre reinicios. APP_URL identifica la URL de esa prueba. DB_CONNECTION debe ser pgsql, con DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME y DB_PASSWORD del PostgreSQL de pruebas. Como alternativa, la configuración existente lee DB_URL. Indicar DB_SSLMODE conforme a la conexión del entorno. SESSION_SECURE_COOKIE se activa en HTTPS y se mantiene desactivado únicamente durante una prueba HTTP local.

La imagen usa producción con depuración desactivada. Las migraciones se ejecutan como una operación explícita, después de comprobar que las variables apuntan a la base elegida para esta prueba. Verificar `php artisan migrate --force` sobre esa base y revisar permisos y cuentas antes de abrir acceso. El arranque del contenedor conserva su función de iniciar Apache.

## Revisión para Railway

La decisión vigente es trabajar en local. Esta preparación queda para revisión en el PR #156 de #49. Para publicar faltan la cuenta/proyecto de Railway, revisión de Jaziel y el corte de código aprobado.

Configurar el Dockerfile de la raíz, PORT, las variables anteriores, PostgreSQL y un volumen montado en /var/www/geosur/web/storage. Comprobar /api/salud como salud de la aplicación, además de una consulta real que confirme la base y las publicaciones. Probar HTTPS, cookies de sesión y cabeceras del proxy de Railway con el dominio definitivo. Revisar explícitamente la política de despliegue de develop y main y la posibilidad de volver a una versión anterior.

Fuentes oficiales consultadas el 07/11/2026: [Dockerfiles](https://docs.railway.com/builds/dockerfiles), [volúmenes](https://docs.railway.com/volumes), [puerto y arranque](https://docs.railway.com/deployments/start-command). La configuración de Railway se completará al elegir el proyecto y el entorno.

## Prueba de recuperación preparada el 08/11/2026

El mismo script amplía la comprobación: crea un registro de control en PostgreSQL y un archivo cifrado con la APP_KEY de la prueba. Después de comprobar el reemplazo de la web, detiene su único escritor, genera un pg_dump en formato custom y copia storage en un archivo tar.gz. Conserva huellas SHA-256 de ambas copias en una carpeta temporal privada.

Luego elimina los contenedores y volúmenes originales que creó el propio script. Inicia otro PostgreSQL con un identificador de clúster distinto y una base vacía; restaura el dump y los archivos en volúmenes nuevos. Comprueba el registro, las migraciones, el archivo de storage y el descifrado usando la misma APP_KEY, además de salud, login y catálogo. La limpieza retira únicamente los recursos con el identificador aleatorio de esta ejecución. Las copias no se publican como artefactos ni se conservan después de la prueba.

Es un ensayo aislado con datos de control, no una copia de datos personales ni una recuperación de publicaciones ambientales reales. No conecta a un servidor existente ni necesita que esté encendido Docker en la laptop. El resultado completo se comprobará en el PR #156; la revisión de sintaxis local por sí sola no acredita recuperación.

Para una recuperación del entorno definitivo se deben conservar por separado la APP_KEY y las demás variables, la versión de aplicación y sus migraciones, la copia consistente de PostgreSQL y los archivos de storage. La clave y las contraseñas se guardan por un canal privado, nunca dentro de Git, capturas ni registros de CI. La retención, cifrado de las copias, destino externo y recuperación de datos ambientales reales se acuerdan antes de publicar; esta prueba no configura ese servicio de respaldo.
