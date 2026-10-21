# EnviroSense: referencia del proyecto original

Esta carpeta conserva los **352 archivos publicados** en el commit [ab4f93a48c2b8a082909feb3310db35296f6cbaa](https://github.com/elingeyesdev/equipo11/tree/ab4f93a48c2b8a082909feb3310db35296f6cbaa) de [elingeyesdev/equipo11](https://github.com/elingeyesdev/equipo11). Importación: 20/10/2026. Seguimiento: [MIG-01 / #29](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/29).

El código, recursos y documentación de origen están en [`codigo/`](codigo/). Su autoría corresponde al proyecto original y a sus colaboradores; esta importación no atribuye su desarrollo a Josué o Jaziel. El repositorio de origen no declara una licencia general en el commit consultado: no se añade una licencia propia ni se sustituye la atribución. Las licencias de dependencias siguen perteneciendo a sus titulares.

## Integridad y sustituciones

`manifiesto.json` relaciona cada ruta con el identificador Git del archivo original y el SHA256 de la copia conservada. **350 archivos conservan exactamente sus bytes**; dos tienen sustituciones explícitas:

- `Backend/Src/modules/sensores/mqtt.service.js`: usuario y contraseña escritos en el código se reemplazaron por `process.env.MQTT_USERNAME` y `process.env.MQTT_PASSWORD`.
- `Frontend/public/firebase-messaging-sw.js`: se retiró el valor de `apiKey`; queda vacío. El resto de la configuración de ese servicio conserva su contenido de origen.

No se reproducen esos valores en el manifiesto, comentarios ni evidencias. Esta es una **copia con sustituciones declaradas**, no una copia idéntica del proyecto original ni una revisión completa de su seguridad. Sus configuraciones y dependencias históricas no se consideran aprobadas para producción.

Se tomó el archivo descargado directamente del commit, no la instalación local `EnviroSense-equipo11`. Por ello no se incluyen sus cambios posteriores de arranque, instalaciones, archivos privados `.env`, sesiones, credenciales locales, bases locales ni cachés. Sí se conservan los esquemas, semillas y ejemplos que ya forman parte del código publicado original. No se importan datos de la instalación local.

Desde la raíz del repositorio, con Python 3.10 o superior:

```powershell
python referencias/envirosense/verificar.py
```

Comprueba archivos faltantes/adicionales, SHA256 y los identificadores originales de los archivos sin cambios. Para contrastar además los 352 archivos con el ZIP descargado del commit:

```powershell
python referencias/envirosense/verificar.py --original-zip RUTA_AL_ZIP_ORIGINAL
```

El ZIP esperado es [el archivo del commit](https://codeload.github.com/elingeyesdev/equipo11/zip/ab4f93a48c2b8a082909feb3310db35296f6cbaa), SHA256 `0154ceb18fca3e5c4e7a102701b8ace610ad6fe61cecaec26d5b85d181cad398`. Se usa únicamente como entrada local de contraste; no debe adjuntarse para redistribuir las credenciales retiradas. El verificador no ejecuta EnviroSense, no consulta proveedores ni imprime valores de credenciales.

## Relación con GeoCruz

EnviroSense emplea React, Node/Express, servicios y base PostgreSQL/TimescaleDB. **No se convirtió JSX en PHP automáticamente.** GeoCruz adapta los patrones útiles mediante Laravel 12, Blade, JavaScript y una API común; Flutter es una aplicación separada. El código activo de GeoCruz vive en `web/`, `movil/` y `compartido/`, fuera de esta referencia.

No ejecutar `docker compose up` ni instalar servicios desde esta carpeta para arrancar GeoCruz. La copia se conserva para comparar y revisar. Los scripts de arranque y las pruebas de GeoCruz siguen apuntando a sus carpetas activas.

La promoción a `main` requiere aprobación expresa del ingeniero. Preparar o fusionar esta referencia no convierte el código original en una aplicación Laravel, no certifica los cinco módulos, no despliega un servidor y no cambia las tareas pendientes de M2. La propuesta de línea base se prepara mediante PR; no se reescribe el historial existente.
