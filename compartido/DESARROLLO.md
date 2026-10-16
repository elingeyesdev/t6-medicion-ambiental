# Conexión de desarrollo entre web y móvil

La aplicación Laravel de `web/` es el backend común. Su puerto predeterminado es **8000**. Flutter se incorporará en #17; el consumo ambiental, en #18 y #20. Los ejemplos siguientes son instrucciones de configuración, no evidencia de ejecución en un dispositivo.

## Direcciones

- Navegador del mismo PC: origen `http://127.0.0.1:8000`; base API `http://127.0.0.1:8000/api`.
- Emulador Android estándar de Android Studio: origen `http://10.0.2.2:8000`; base API `http://10.0.2.2:8000/api`.
- Android físico u otro PC en la misma red: origen `http://IP_LAN_DEL_PC:8000`; base API `http://IP_LAN_DEL_PC:8000/api`. Reemplazar IP_LAN_DEL_PC por la IPv4 real del PC que ejecuta Laravel.

`localhost` y `127.0.0.1` en Android apuntan al propio emulador o teléfono, no al PC. La dirección especial 10.0.2.2 corresponde al emulador estándar; no se presume que sirva en todos los emuladores.

Para recibir desde otro dispositivo, iniciar explícitamente en las interfaces de red del PC:

```powershell
# Desde la raíz, Windows
.\iniciar.ps1 -Direccion 0.0.0.0 -Puerto 8000
```

O, dentro de `web/`, con PHP configurado:

```sh
php artisan serve --host=0.0.0.0 --port=8000
```

`0.0.0.0` es la dirección de escucha, no una URL para abrir en el cliente. Usar la IP LAN, mantener ambos equipos en la misma red y comprobar que el firewall permite la conexión local. El proyecto no abre reglas de firewall automáticamente. El servidor de Artisan es para desarrollo local.

## Primera comprobación de conexión

Probar `GET BASE_API/salud`. Ejemplo Android Studio: `http://10.0.2.2:8000/api/salud`. Debe devolver 200 y JSON con servicio GeoCruz, estado disponible, alcance aplicacion y fecha UTC.

Usar una URL base configurable en Flutter; nunca una clave NASA. Si se emplea HTTP local en Android, habilitarlo solo en la configuración de depuración de #17 según sus requisitos de plataforma. Flutter Web añade restricciones CORS del navegador y requiere una comprobación específica; no se da por probado con las pruebas PHP.

La salud no afirma que los módulos, la base de datos o los proveedores estén operativos. Si no responde, revisar primero proceso, dirección, puerto y acceso de red. No sustituir un fallo por un JSON inventado o datos de ejemplo.

## Contrato ambiental

La ruta de salud es operativa y queda fuera de la envoltura ambiental de DOC-12. El prefijo/versionado y los modelos de las consultas ambientales se incorporan en #18, conservando las decisiones pendientes y la revisión de ambos integrantes. El catálogo y los límites pertenecen a #16: no se crean códigos territoriales ficticios para completar esta base.

Referencia del emulador: [direcciones de red de Android Emulator](https://developer.android.com/studio/run/emulator-networking-address).
