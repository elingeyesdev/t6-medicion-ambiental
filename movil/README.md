# GeoCruz móvil

Aplicación Flutter del sistema GeoCruz. La navegación y las plantillas iniciales
están preparadas para los cinco módulos ambientales del departamento de Santa
Cruz. La consulta móvil de focos de calor pertenece a MOV-02; los otros cuatro
módulos se muestran como pendientes. Esta versión no presenta registros de
ejemplo ni sustituye una consulta por datos simulados.

## Requisitos

- Flutter 3.38.3 o compatible con Dart 3.10.1.
- Android Studio con un emulador Android, o un dispositivo Android con depuración
  USB habilitada.
- PHP 8.2+ para iniciar el backend local de GeoCruz cuando se integre una
  consulta móvil.

Las versiones de paquetes quedan fijadas por `pubspec.lock`.

## Preparar y ejecutar

Desde la raíz del repositorio:

```powershell
cd movil
flutter pub get
flutter run
```

El destino predeterminado de la API es `http://10.0.2.2:8000/api/v1/`, la
dirección del emulador Android para el servidor de desarrollo del equipo. El
servidor se puede iniciar desde otra terminal, en la raíz del repositorio:

```powershell
./iniciar.ps1 -Direccion 0.0.0.0 -Puerto 8000
```

Para un teléfono físico, ambos dispositivos deben estar en la misma red. Usa la
dirección local del computador y pasa la base al compilar/ejecutar:

```powershell
flutter run --dart-define=GEOCRUZ_API_BASE_URL=http://192.168.1.20:8000/api/v1/
```

Reemplaza `192.168.1.20` por la dirección real del computador. Para iOS
Simulator o un dispositivo iOS, usa una API HTTPS o prepara una excepción ATS
local solo para desarrollo. Para un despliegue, configura una URL HTTPS. La
excepción HTTP de Android se limita a la variante debug.

`GEOCRUZ_API_BASE_URL` configura únicamente la dirección del backend. No pongas
claves de NASA FIRMS, OpenAQ ni otros proveedores en Flutter: esas credenciales
pertenecen al backend. El cliente HTTP está preparado, pero esta versión de
MOV-01 no hace consultas ambientales.

## Comprobaciones locales

```powershell
flutter analyze
flutter test
```

Las pruebas de widgets usan un registro de prueba solo dentro del entorno de
test para comprobar que territorio, período e identificador pasan del listado
al detalle. Ese registro no aparece en la aplicación.

## Estructura

- `lib/core/config/`: URL configurable del backend.
- `lib/core/network/`: cliente HTTP JSON compartido.
- `lib/core/theme/`: tema y colores alineados con la web GeoCruz.
- `lib/features/home/`: inicio y navegación inferior.
- `lib/features/modules/`: catálogo de los cinco módulos y estados de
  disponibilidad móvil.
- `lib/features/consultation/`: contexto de consulta y plantillas reutilizables
  de lista/detalle, listas para recibir resultados reales en MOV-02.
