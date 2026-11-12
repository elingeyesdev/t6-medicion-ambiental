# GeoSur Guía rápida de la VM

## Direcciones para la demostración

Ubuntu Server: 10.0.2.15, observada en la configuración NAT. Confirmar la IP actual dentro de Ubuntu con ip -br address.

Web desde Windows: http://127.0.0.1:8089/login

API desde Windows: http://127.0.0.1:8089/api/v1/

API desde Android Emulator: http://10.0.2.2:8089/api/v1/

127.0.0.1 es el propio equipo. En Android Emulator, 10.0.2.2 permite llegar a Windows. VirtualBox reenvía el puerto 8089 de Windows al 80 de Ubuntu.

## Rutas principales de la API

Las rutas siguientes se agregan a la base /api/v1/. GET consulta datos; POST envía una acción.

```text
POST cuenta/ingresar          Iniciar sesión
GET  cuenta                   Consultar el perfil
GET  mapa/puntos              Puntos del mapa
GET  mapa/capas               Capas ambientales
GET  tiempo                   Tiempo de una ubicación
GET  aire                     Calidad del aire
GET  focos-calor              Focos de calor
GET  sequias                  Consulta de sequías
GET  inundaciones/catalogo    Comparaciones de Agua disponibles
GET  inundaciones/consulta    Consultar una comparación de Agua
POST cuenta/salir             Cerrar sesión
```

Ejemplo desde Windows: http://127.0.0.1:8089/api/v1/mapa/puntos. Desde el emulador cambia 127.0.0.1 por 10.0.2.2.

El ingreso recibe correo y contraseña en JSON. Las consultas privadas necesitan el token devuelto al ingresar, en Authorization: Bearer TOKEN. Algunas consultas requieren filtros; abrir solo la dirección no demuestra el resultado completo.

## Comprobación rápida y SSH

Salud del servidor: http://127.0.0.1:8089/api/salud. Esta ruta no lleva v1.

```text
ssh -p 2223 jaziel@127.0.0.1
hostname
ip -br address
```

SSH usa el puerto 2223 de Windows, reenviado al 22 de Ubuntu. En PuTTY: dirección 127.0.0.1, puerto 2223 y conexión SSH.

## Qué explicar

Windows o la app envían la petición. VirtualBox la lleva a Ubuntu; Nginx la dirige a Laravel y Laravel consulta PostgreSQL dentro de Docker. La app recibe JSON y no se conecta directamente a la base de datos.