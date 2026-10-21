# MOV-02 — evidencia local

Fecha: 2026-10-20

## Comprobaciones realizadas

- `dart format lib test`: correcto.
- `flutter analyze --no-pub`: sin problemas.
- `flutter test --no-pub`: 13 pruebas aprobadas.
- `flutter build apk --debug --split-per-abi --target-platform android-x64`: compilación correcta.
- Se reinició el emulador Pixel 7 cuando Android dejó de responder a ADB. Después se instaló `app-debug.apk` correctamente y se abrió GeoCruz; la pantalla de inicio quedó visible. Captura: `mov02-emulador-inicio.png`.

## API

La comprobación de `http://127.0.0.1:8002/api/salud` no respondió dentro del tiempo de espera durante esta ejecución. Por eso se verificó el arranque de la app, pero no se acredita aquí una consulta en vivo contra FIRMS. Para esa comprobación se necesita una instancia accesible del backend con `NASA_FIRMS_CLAVE` configurada únicamente en el backend.

Las pruebas automatizadas del repositorio móvil usan respuestas de prueba y verifican el contrato, filtros, paginación, expiración de conjunto, valores faltantes y conservación del contexto. No sustituyen la comprobación en vivo de la API.

## Capturas existentes

- `mov02-inicio.png`: inicio de la aplicación.
- `mov02-emulador-inicio.png`: captura del Pixel 7 tras instalar y abrir el APK actual.
- `mov02-modulos.png`: catálogo móvil de módulos.
- `mov02-focos-sin-api.png`: captura anterior al ajuste del tiempo de espera; no acredita el estado final de error y reintento.
