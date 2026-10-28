# Conexión de desarrollo entre web y móvil

Laravel en `web/` sirve la interfaz Blade y la API común. Flutter ya consume focos de calor mediante MOV-02 / PR #32, integrado en `develop`; QA-01 / PR #33 comprueba la equivalencia entre clientes. Conservar EnviroSense como referencia no exige rehacer esa integración.

## Actualizar la copia

Desde una copia sin cambios pendientes:

```powershell
git fetch origin
git switch develop
git pull --ff-only origin develop
```

Si hay trabajo local sin guardar, conservarlo antes de cambiar de rama. El corte con móvil y QA integrados es `5fd724cd1b4360e296896538f658522bfe6c8c21`; no hace falta esperar los PR documentales #27/#31 ni la propuesta académica #30 para usar la API existente.

## Arrancar el backend

Desde la raíz del repositorio, después de la [preparación de Laravel](../web/README.md):

```powershell
.\iniciar.ps1 -Direccion 0.0.0.0 -Puerto 8002
```

`iniciar.ps1` usa 8000 si no se indica puerto; aquí se elige **8002** para coincidir con la configuración actual de Flutter. `0.0.0.0` es la dirección de escucha, no una URL para el cliente. Artisan es un servidor de desarrollo local.

Probar primero en el PC:

- Salud: <http://127.0.0.1:8002/api/salud> (200 y JSON; no acredita conexión NASA).
- Catálogo: <http://127.0.0.1:8002/api/v1/territorios>.
- Visor web: <http://127.0.0.1:8002/modulos/focos-calor>.

Configurar `NASA_FIRMS_CLAVE` únicamente en `web/.env`, excluido de Git. No compartir el archivo ni copiar la clave a Flutter. Una consulta externa necesita una clave válida y disponibilidad del proveedor; una falla debe conservar su error, sin reemplazo ficticio.

## Conectar Flutter

Desde `movil/`, emulador Android estándar de Android Studio:

```powershell
flutter pub get
flutter run --dart-define=GEOCRUZ_API_BASE_URL=http://10.0.2.2:8002/api/v1/
```

Esa URL ya es el valor predeterminado en `lib/core/config/api_config.dart`. Para un teléfono físico u otro PC, reemplazar `IP_LAN_DEL_PC` por la IPv4 del equipo que ejecuta Laravel, con ambos equipos en la misma red:

```powershell
flutter run --dart-define=GEOCRUZ_API_BASE_URL=http://IP_LAN_DEL_PC:8002/api/v1/
```

Antes de abrir Flutter en el teléfono, probar `http://IP_LAN_DEL_PC:8002/api/salud` en su navegador. `localhost` y `127.0.0.1` en Android apuntan al propio dispositivo. `10.0.2.2` corresponde al emulador estándar, no al teléfono físico. Conservar `/api/v1/` en la variable de Flutter; la salud usa `/api/salud`, fuera de esa envoltura ambiental.

Si falla la salud desde el dispositivo, revisar proceso, dirección, puerto y acceso de red antes de cambiar código. El proyecto no abre reglas de firewall automáticamente. El permiso HTTP local de Android se limita a depuración. Flutter Web requiere revisar CORS por separado; no se da por probado con Android o PHP. Más detalles en [la guía móvil](../movil/README.md).

## Qué probar juntos

Usar el mismo territorio, confianza y período de hasta 168 horas, con inicio incluido y final excluido. Comparar ID del registro, fecha, coordenadas, FRP/unidad, fuente, resumen, cobertura y advertencias entre web y móvil. Mapa y lista representan la misma página; el resumen corresponde al conjunto filtrado. Distinguir resultados vacíos, falta de cobertura y error del proveedor.

Consultar [OpenAPI](api/openapi.json), [reglas de la API](api/README.md) y [matriz y evidencias QA-01](../docs/qa21/README.md). Las versiones conservan los identificadores publicados `1.0-m2-candidata` y `scz-codab-v02-1.0.0-propuesta`; su aceptación técnica no certifica límites administrativos ni sustituye la aprobación del ingeniero. Las cuatro consultas ambientales restantes siguen pendientes.

Estas instrucciones permiten repetir la prueba; no son evidencia de una ejecución nueva en un dispositivo ni de una consulta NASA nueva.
