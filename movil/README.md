# GeoCruz móvil

Aplicación Flutter del sistema GeoCruz. La navegación y las plantillas iniciales
están preparadas para los cinco módulos ambientales del departamento de Santa
Cruz. Focos de calor consume la misma API que Blade; sequías, inundaciones,
deforestación y calidad del aire siguen como módulos próximos. No se presentan
registros de ejemplo ni se sustituyen consultas por datos simulados.

## Requisitos

- Flutter 3.38.3 o compatible con Dart 3.10.1.
- Android Studio con un emulador Android, o un dispositivo Android con depuración
  USB habilitada.
- PHP 8.2+ y la configuración local del backend si se ejecutará la API en esta
  computadora. Para consultar FIRMS, el backend debe tener `NASA_FIRMS_CLAVE`
  configurada de forma privada en `web/.env`; la clave nunca va en Flutter.

Las versiones de paquetes quedan fijadas por `pubspec.lock`.

## Preparar y ejecutar

Desde la raíz del repositorio:

```powershell
cd movil
flutter pub get
flutter run
```

La primera entrada a Focos de calor carga el catálogo territorial y consulta el
período de las últimas 24 horas del calendario de Bolivia. El visor usa una
página de 25 registros, filtros de provincia/municipio, confianza y período,
mapa vinculado al listado, detalle y resumen devuelto por la API. Las páginas
siguientes reutilizan `versionConjunto`; si vence, se ofrece reiniciar la
consulta. Los resultados anteriores permanecen identificados con su período y
fecha de recuperación cuando una consulta nueva falla.

El destino predeterminado de la API es `http://10.0.2.2:8002/api/v1/`, la
dirección del emulador Android para la API de desarrollo del equipo. En este
repositorio, la web y la API pertenecen a la misma aplicación Laravel; no hay un
contenedor Docker definido. Si Laravel está instalado y configurado, se puede
iniciar desde otra terminal, en la raíz del repositorio:

```powershell
./iniciar.ps1 -Direccion 0.0.0.0 -Puerto 8002
```

La aplicación Flutter se ejecuta por separado en el emulador o teléfono. Si el
backend se inicia con Docker, el contenedor debe publicar un puerto accesible
desde el dispositivo; configura ese puerto con `GEOCRUZ_API_BASE_URL`. Sin un
servidor API disponible, Flutter puede abrir, pero los datos reales no cargarán
y se mostrará el estado de fallo con opción de reintento.

Para un teléfono físico, ambos dispositivos deben estar en la misma red. Usa la
dirección local del computador y pasa la base al compilar/ejecutar:

```powershell
flutter run --dart-define=GEOCRUZ_API_BASE_URL=http://192.168.1.20:8002/api/v1/
```

Reemplaza `192.168.1.20` por la dirección real del computador. Para iOS
Simulator o un dispositivo iOS, usa una API HTTPS o prepara una excepción ATS
local solo para desarrollo. Para un despliegue, configura una URL HTTPS. La
excepción HTTP de Android se limita a la variante debug.

`GEOCRUZ_API_BASE_URL` configura únicamente la dirección del backend. No pongas
claves de NASA FIRMS, OpenAQ ni otros proveedores en Flutter: esas credenciales
pertenecen al backend. En Android debug se permite HTTP para el servidor local;
la configuración de despliegue debe usar HTTPS.

El mapa de focos muestra la página recibida, los límites devueltos por la API y
puntos en sus coordenadas originales. El mapa base usa teselas de OpenStreetMap,
con atribución visible y nombre de aplicación en las solicitudes. Si fallan las
teselas o el límite, el listado y el detalle siguen disponibles.

## Comprobaciones locales

```powershell
flutter analyze
flutter test
flutter build apk --debug
```

Las pruebas de widgets usan un registro de prueba solo dentro del entorno de
test para comprobar que territorio, período e identificador pasan del listado
al detalle. Ese registro no aparece en la aplicación.

## Estructura

- `lib/app/`: composición y arranque de la aplicación.
- `lib/core/`: URL configurable, cliente HTTP, formatos y tema compartidos.
- `lib/features/<funcionalidad>/model/`: modelos y repositorios que manejan
  datos.
- `lib/features/<funcionalidad>/view_model/`: estado y acciones que conectan
  datos con la interfaz (ViewModel).
- `lib/features/<funcionalidad>/view/`: pantallas y componentes (View).
- `lib/features/modules/`: los cinco módulos y su disponibilidad.
- `lib/features/hotspots/`: consulta real de focos, mapa, filtros, resumen y
  detalle.
