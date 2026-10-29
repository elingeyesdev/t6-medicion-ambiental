# GeoSur móvil

Aplicación Flutter de GeoSur. La app comparte con Laravel la cuenta, permisos,
consulta del mapa ambiental y análisis de focos de calor. El mapa conserva
filtros, fechas, procedencia, fuente, unidad y coordenadas de cada lectura; los
puntos sin lecturas no se convierten en ceros. La información del mapa se
consulta con la cuenta iniciada. El mapa base usa OpenStreetMap.

## Requisitos

- Flutter 3.38.3 con Dart 3.10.1 o compatible.
- Android Studio con emulador Android o teléfono Android con depuración USB.
- API Laravel de este repositorio ejecutándose y accesible desde el dispositivo.
- Una cuenta GeoSur activa. Registro e inicio de sesión se validan en Laravel.

NASA FIRMS y otros proveedores se consultan únicamente desde el backend. No
incluyas sus claves en Flutter. La sesión móvil guarda su token en el almacén
seguro del sistema y el token no aparece en las URLs.

## Preparar y ejecutar

Desde la raíz del repositorio, en PowerShell:

```powershell
cd movil
flutter pub get
flutter run
```

Por defecto el emulador Android usa `http://10.0.2.2:8002/api/v1/`. Inicia
Laravel en otra terminal desde la raíz del repositorio:

```powershell
cd ..
./iniciar.ps1 -Direccion 0.0.0.0 -Puerto 8002
```

Si el script no puede ejecutarse por la política de PowerShell, ejecuta el
comando equivalente desde `web/`:

```powershell
php artisan serve --host=0.0.0.0 --port=8002
```

Para un teléfono físico, conecta ambos equipos a la misma red y configura la
dirección LAN del computador:

```powershell
cd movil
flutter run --dart-define=GEOCRUZ_API_BASE_URL=http://192.168.1.20:8002/api/v1/
```

Reemplaza `192.168.1.20` por la IP real del computador. No hace falta Docker
salvo que la instalación local del backend lo use. En caso de usarlo, publica
el puerto y configura `GEOCRUZ_API_BASE_URL` con una dirección accesible desde
el dispositivo. Para publicar la app, usa una API HTTPS.

Al abrir, GeoSur comprueba si hay una sesión guardada. Si no la hay, muestra el
inicio de sesión; también permite crear una cuenta. Tras ingresar aparecen el
Mapa, Focos y Cuenta. La primera consulta del mapa muestra los últimos siete
días. En el móvil, el mapa general es el único mapa; Focos presenta una lista
de detecciones y su detalle. La API limita las consultas a siete días y 250
puntos por página. El historial mantiene el período y la variable elegidos.

La app solo presenta recorridos móviles integrados. Sequías se añadirá con el
trabajo de Flutter #38. Simulaciones, reportes, alertas y cargas todavía no
tienen una sección móvil; se añadirán cuando se complete su integración móvil.

## Comprobaciones

```powershell
flutter analyze
flutter test
flutter build apk --debug
```

Las pruebas comprueban filtros, autenticación de solicitudes, catálogos y
preservación de lecturas vacías, ceros, origen y fuente. La comprobación visual
final se hace en un emulador o teléfono conectado al backend.
