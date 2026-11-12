# Guía para demostrar GeoSur en Ubuntu Server

## Objetivo de la evaluación

Josué y Jaziel deben demostrar el recorrido completo y explicar qué equipo recibe cada petición, qué servicio la procesa y cómo se conserva la información. Esta guía usa la copia de laboratorio GeoSur Ubuntu Jaziel. No contiene contraseñas.

## Paso 1 Preparar la demostración

En Windows, abrir VirtualBox e iniciar solamente la VM que se va a evaluar. Esperar a que aparezca el acceso de Ubuntu Server. La consola sin escritorio es lo esperado. Mantener disponibles el navegador, PowerShell, PuTTY y el emulador Android. Ambos integrantes deben poder explicar todos los pasos; uno ejecuta y el otro verifica, y luego intercambian funciones.

## Paso 2 Identificar la red

En la consola de Ubuntu o en una sesión SSH, ejecutar los siguientes comandos. Solo consultan información y no requieren sudo.

```text
hostname
ip -br address
```

## Qué debe aparecer

El nombre de la copia es geosur-jaziel. La IP de Ubuntu observada en nuestra configuración NAT fue 10.0.2.15/24; hay que confirmar la dirección actual en la interfaz enp0s3 o equivalente. No confundirla con lo, docker0 o las interfaces br de Docker. Si cambia, explicar el valor que realmente aparece.

## Direcciones que debemos distinguir

127.0.0.1 significa este mismo equipo. En el navegador de Windows usamos http://127.0.0.1:8089/login. Dentro de Ubuntu, 127.0.0.1 se refiere a Ubuntu. Desde Android Emulator, 10.0.2.2 permite llegar al anfitrión Windows; la API es http://10.0.2.2:8089/api/v1/. Esa dirección especial no corresponde a un teléfono físico.

## Puertos de la configuración

Windows 8089 se reenvía al 80 de Ubuntu para web y API. Windows 2223 se reenvía al 22 de Ubuntu para SSH y PuTTY. Windows 8443 se reenvía al 443 de Ubuntu para HTTPS de laboratorio. PostgreSQL usa 5432 dentro de Docker y no publica ese puerto hacia Windows.

## Qué explicar

VirtualBox usa red NAT con reenvío de puertos. La IP interna identifica Ubuntu; el puerto reenviado nos permite acceder desde Windows. No estamos entrando directamente al servidor de desarrollo PHP de Windows.

# Demostración de la web y recorrido de la petición

## Paso 3 Abrir GeoSur desde Windows

En el navegador de Windows, abrir http://127.0.0.1:8089/login. Mostrar la barra de direcciones y explicar que Windows es el cliente y Ubuntu es el servidor. Ingresar con la cuenta de demostración autorizada. Si cambiaron su contraseña, usar la actual y no mostrarla al público.

## Qué demostrar en la aplicación

Abrir el mapa y consultar una ubicación. Mostrar el perfil para explicar que hubo autenticación. Elegir una consulta que tenga información disponible y comprobar que el resultado corresponda al lugar y período. Los 61 puntos son referencias del mapa; no significan que existan 61 mediciones actuales. No presentar una falta de cobertura como un error del servidor ni inventar resultados de Agua.

## Recorrido completo de la petición

1. El navegador envía la petición al puerto 8089 de Windows.
2. VirtualBox la reenvía al puerto 80 de Ubuntu.
3. Nginx instalado en Ubuntu recibe la petición y la reenvía a 127.0.0.1:18080.
4. Docker publica allí el puerto 8080 del contenedor web, que también utiliza Nginx.
5. El contenedor web sirve los recursos estáticos y entrega las peticiones PHP al contenedor app mediante PHP-FPM, en el puerto interno 9000.
6. Laravel procesa rutas, autenticación, permisos y lógica. Cuando hace falta, consulta el servicio db de PostgreSQL en la red interna, por el puerto 5432.
7. La respuesta vuelve por el mismo recorrido al navegador.

## Paso 4 Comprobar la API

Abrir http://127.0.0.1:8089/api/salud. Debe responder un JSON con estado disponible. Este endpoint comprueba respuesta de la aplicación; por sí solo no acredita conexión a PostgreSQL ni disponibilidad de datos ambientales. El ingreso autenticado, las consultas y las migraciones aportan comprobaciones adicionales.

## Qué decir si preguntan por Nginx

Tenemos dos niveles: el Nginx del servidor Ubuntu recibe HTTP y HTTPS, y el del contenedor web entrega el tráfico a la aplicación. Laravel ejecuta la lógica; PostgreSQL almacena los datos. Los puertos internos de Docker no son todos accesos públicos.

## HTTPS del laboratorio

Se puede mostrar https://127.0.0.1:8443. El certificado es autofirmado, por lo que el navegador puede advertir que no confía en él. Explicar esta limitación; no afirmar que tenemos un certificado público válido.

# Demostración de móvil y acceso por terminal

## Paso 5 Conectar la app a la VM

Usar el APK que Jaziel comprobó contra Ubuntu. Para ejecutar desde el código, abrir PowerShell en la carpeta movil de la copia oficial y usar el comando siguiente. Lo ejecuta Flutter en Windows, no dentro de Ubuntu. No modifica el código fuente; configura la URL para esa ejecución.

```text
flutter run --dart-define=GEOCRUZ_API_BASE_URL=http://10.0.2.2:8089/api/v1/
```

## Qué mostrar y explicar

Iniciar sesión y cargar el mapa con los 61 puntos, como en las evidencias de Jaziel. La app envía peticiones HTTP a la API de Laravel y recibe JSON. No accede directamente a PostgreSQL. En Android, 127.0.0.1 sería el propio emulador; por eso usamos 10.0.2.2. No basta con mostrar una app instalada: hay que demostrar acceso al servidor.

## Paso 6 Entrar por SSH desde PowerShell

Abrir otra pestaña de PowerShell en Windows y ejecutar:

```text
ssh -p 2223 jaziel@127.0.0.1
```

## Qué revisar

Comprobar la huella del servidor con la guía de la copia. Usar la contraseña actual de jaziel. Al escribirla no aparecen caracteres: es normal. Una vez conectado, los comandos siguientes se ejecutan en Ubuntu.

```text
hostname
id
```

## Qué explicar sobre permisos

jaziel es un usuario Linux limitado: no pertenece a sudo ni docker. jaziel-admin es una cuenta distinta para administrar la copia y debe proporcionar su contraseña al usar sudo. No confundir estos usuarios Linux con las cuentas y roles de GeoSur. La denegación de administración para jaziel es un resultado esperado.

## Paso 7 Repetir la conexión en PuTTY

Abrir PuTTY en Windows. Elegir SSH, dirección 127.0.0.1 y puerto 2223. Entrar como jaziel, verificar la huella y repetir hostname e id. PuTTY y el comando ssh permiten administrar el mismo servidor por terminal; no son el acceso a la web.

## Salir de la sesión limitada

Usar exit para volver a PowerShell. Para las comprobaciones administrativas del siguiente paso, entrar con jaziel-admin. Las contraseñas modificadas en la copia de Jaziel pueden ser distintas de las temporales del OVA original.

# Servicios contenedores y protección del acceso

## Paso 8 Entrar como administrador

Desde PowerShell en Windows, conectarse con la cuenta administrativa. Después ejecutar sudo -i dentro de Ubuntu y escribir la contraseña de jaziel-admin. Este comando abre una sesión root; revisar cada instrucción antes de ejecutarla.

```text
ssh -p 2223 jaziel-admin@127.0.0.1
```

## En Ubuntu

Ejecutar los comandos siguientes uno por uno. Los primeros consultan servicios, firewall y contenedores; no eliminan datos.

```text
sudo -i
systemctl is-active docker nginx ssh
ufw status
cd /home/josue/laboratorio-docker
docker compose ps
```

## Qué debe aparecer

Docker, Nginx y SSH deben responder active. UFW permite SSH, HTTP y HTTPS. Compose debe listar app, web y db; web y db cuentan con comprobaciones de salud. La publicación del contenedor web debe estar limitada a 127.0.0.1:18080. db puede mostrar 5432/tcp sin una dirección del anfitrión: eso no significa que se haya publicado hacia Windows.

## Paso 9 Comprobar Laravel y PostgreSQL

Conservar la sesión administrativa y la carpeta del laboratorio. Ejecutar:

```text
docker compose exec -T app geosur-entrada php artisan migrate:status
```

## Qué explicar

Deben aparecer las 13 migraciones del laboratorio como Ran. geosur-entrada carga los secretos internos antes de ejecutar PHP; omitirlo en esta configuración puede producir una prueba de conexión incorrecta. El comando consulta el estado de las migraciones, no crea ni elimina tablas.

## Reinicio y conservación de datos

Los servicios del sistema están habilitados y los contenedores usan restart: unless-stopped. PostgreSQL guarda los datos en un volumen, separado de la imagen del contenedor. Estas configuraciones ayudan a recuperar el servicio tras reiniciar, pero deben contrastarse con una prueba real; si se pide reiniciar, esperar el arranque y repetir web, API y compose ps.

## Acciones que debemos evitar

No desactivar UFW ni abrir todos los puertos para resolver un fallo. No ejecutar docker compose down -v: elimina los volúmenes y puede borrar los datos. No mostrar .env, claves privadas ni tokens. Al terminar las operaciones administrativas usar exit.

# Respaldo restauración y diagnóstico de un fallo

## Paso 10 Crear un respaldo

Esta parte se ejecuta como root dentro de Ubuntu, después de sudo -i y cd /home/josue/laboratorio-docker. umask restringe los permisos de los archivos nuevos de esta sesión. pg_dump lee la base principal y escribe un respaldo privado.

```text
umask 077
docker compose exec -T db pg_dump -U postgres -d geosur -Fc > .privado/defensa.dump
ls -l .privado/defensa.dump
```

## Resultado esperado

El archivo debe existir, tener tamaño mayor que cero y permisos restringidos. Este nombre corresponde a la práctica de defensa; el comando sobrescribe una copia anterior con ese mismo nombre, no la base principal. No mostrar ni enviar el contenido del respaldo.

## Paso 11 Restaurar en una base separada

Crear un nombre nuevo para la prueba y restaurar allí. Se crean una base y sus tablas; no se sustituye geosur. Si ya existe ese nombre, usar otro.

```text
defensa_db="geosur_defensa_$(date +%Y%m%d_%H%M%S)"
docker compose exec -T db createdb -U postgres -O geosur "$defensa_db"
docker compose exec -T db pg_restore -U postgres -d "$defensa_db" --exit-on-error < .privado/defensa.dump
docker compose exec -T -e DB_DATABASE="$defensa_db" app geosur-entrada php artisan migrate:status
```

## Qué demostrar

Laravel debe reconocer las migraciones en la base restaurada. Explicar la diferencia entre crear un respaldo y probar su restauración. Jaziel ya aportó un registro de restauración con 13 migraciones; durante la defensa se puede reproducir sin tocar la base principal.

## Paso 12 Diagnosticar antes de cambiar cosas

Si la web no responde, comprobar que la VM esté encendida, el reenvío de puertos sea correcto, Nginx esté activo y los contenedores estén funcionando. Revisar registros con los comandos siguientes; el primero consulta Docker y el segundo el servicio del sistema.

```text
docker compose logs --tail 30 app web
journalctl -u nginx --no-pager -n 30
```

## Cómo explicar el incidente documentado

Las evidencias recibidas muestran un rechazo inicial de credenciales en móvil y una captura posterior con el mapa cargado. No se documentó la causa exacta: no afirmar que se corrigió código. La red, la URL de la API y las credenciales se deben comprobar por separado.

# Repaso antes de defender y preguntas frecuentes

## Recorrido que debemos poder repetir

1. Iniciar Ubuntu y mostrar hostname e IP.
2. Abrir la web desde Windows e ingresar.
3. Explicar el recorrido por VirtualBox, Nginx, Laravel y PostgreSQL.
4. Abrir la app, autenticar y cargar el mapa.
5. Entrar como usuario limitado por SSH y PuTTY.
6. Revisar servicios, firewall, contenedores y migraciones con la cuenta administrativa.
7. Mostrar el respaldo y su restauración en otra base.
8. Cerrar las sesiones administrativas y conservar evidencias sin secretos.

## Por qué usamos 127 0 0 1 y no la IP de Ubuntu

Porque accedemos al reenvío de puertos de VirtualBox en el anfitrión Windows. La IP interna de Ubuntu se comprueba con ip -br address. No son la misma dirección ni tienen la misma función.

## Por qué la app usa 10 0 2 2

Es la dirección especial con la que Android Emulator llega al anfitrión. El puerto 8089 de Windows se reenvía a Ubuntu. El móvil consume la API y recibe JSON.

## Para qué sirve Docker Compose

Define y administra los servicios app, web y db con sus redes, volúmenes, dependencias y políticas de reinicio. Una imagen es la base para crear el contenedor; un volumen conserva datos fuera de esa imagen.

## Por qué PostgreSQL no está abierto hacia Windows

Solo la aplicación necesita consultarlo por la red interna de Docker. Evitamos exponer un acceso de base de datos que el cliente web o móvil no necesita.

## Cómo sabemos que el sistema funciona

Combinamos pruebas: web desde Windows, ingreso API, móvil conectado, servicios activos, contenedores, migraciones y restauración. Una captura de la página de salud no demuestra por sí sola todo el flujo.

## Qué todavía no debemos afirmar

No presentar las referencias del mapa como mediciones actuales. No decir que hay un producto real de Agua validado ni que la tarea 115 está terminada por haber desplegado la VM. No afirmar que el HTTPS autofirmado equivale a un certificado público.

## Explicación breve para comenzar

Tenemos GeoSur en Ubuntu Server dentro de VirtualBox. Windows y el emulador acceden mediante puertos reenviados. Nginx recibe las peticiones, Laravel procesa la lógica y consulta PostgreSQL dentro de Docker. Administramos Ubuntu por SSH y PuTTY, con cuentas separadas. También comprobamos un respaldo restaurándolo en otra base.

## Qué estudiar cada integrante

Ambos deben conocer las direcciones, los puertos y el recorrido completo. Antes de la evaluación, ensayar alternando quién ejecuta y quién explica. Usar la IP y los resultados que aparezcan en la VM real, no memorizar una salida que pudo cambiar.