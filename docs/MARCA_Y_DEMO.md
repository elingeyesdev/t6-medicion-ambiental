# Marca GeoCruz y demostración local

Trabajo de #54. Josué eligió el segundo emblema ambiental el 27/10/2026: Santa Cruz con vegetación, un curso de agua y suelo seco. La marca visible pasa a **GeoCruz**. EnviroSense sigue identificado como origen de la adaptación, con su referencia histórica intacta.

El inicio usa **Explorar** y **Qué quieres consultar**; el menú del mapa usa **Explorar**, Focos de calor y Sequías. No se cambian las rutas `/modulos/...`, el contrato de Flutter ni los nombres internos de las carpetas por cambiar un rótulo.

## Archivos

- `web/public/img/marca/geocruz-logotipo.png`: propuesta horizontal elegida.
- `web/public/img/marca/geocruz-simbolo.png`: símbolo preparado con transparencia exterior y canales blancos opacos.
- `web/resources/views/components/panel/marca.blade.php`: símbolo y nombre accesible compartidos entre inicio, acceso y mapa.
- `web/public/css/panel/marca.css`: tamaño y adaptación de la marca, separado del proveedor Tabler.
- `web/app/Console/Commands/CrearCuentaDemo.php`: preparación local del acceso demo.

Los recursos son imágenes PNG, no vectores. El contorno del logo es ilustrativo; los límites de las consultas siguen siendo los del catálogo territorial. Procedencia y preparación del recurso: `web/public/img/marca/LEEME.md`.

## Crear la cuenta demo en otra copia

Con `APP_ENV=local`, base configurada y migraciones aplicadas, desde `web/`:

```powershell
..\herramientas\php.ps1 artisan geocruz:crear-demo
```

El comando pide una contraseña oculta: al menos 12 caracteres y hasta 72 bytes. Crea **Demo GeoCruz**, correo **demo@geocruz.test**, con la contraseña protegida por el mismo hash de Laravel que usa el registro. No se incluye una contraseña predeterminada en Git ni se crea la cuenta al arrancar o desplegar. Si ya existe, conserva sus datos y no cambia su contraseña. El comando rechaza producción y otros entornos distintos de local/testing.

Para una preparación automatizada local admite `GEOCRUZ_DEMO_CLAVE` como variable temporal del proceso; no pasar la contraseña como argumento ni guardarla en el repositorio. La cuenta tiene el mismo acceso de consulta que un usuario común, sin permisos de administración. La base local y las credenciales se comparten fuera de Git cuando corresponde.

## Probar en este PC

Desde `C:\Users\cjosu\Desktop\GeoCruz-oficial\tmp\panel-envirosense`, iniciar `./iniciar-web.ps1 -Puerto 5791`. Abrir http://127.0.0.1:5791/ y comprobar:

1. GeoCruz y el emblema en el inicio; Explorar lleva a las consultas.
2. Ingresar con la cuenta demo por `/login`; aparece el mapa.
3. Elegir Focos de calor o Sequías desde Explorar. Las fuentes, períodos y faltantes conservan sus reglas anteriores.
4. Contraer el menú y cambiar de tema. Repetir en celular, sin cortar el logo.
5. Cerrar sesión y verificar que `/mapa` vuelve a pedir el acceso.

El PR de #54 depende de la base candidata #51. Hace falta la revisión de Jaziel antes de integrar. La cuenta preparada aquí no demuestra que exista un despliegue Railway ni una demo pública.
