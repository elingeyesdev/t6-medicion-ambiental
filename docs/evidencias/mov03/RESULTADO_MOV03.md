# Evidencia MOV-03 — prueba local del 25/10/2026

## Entorno

- Rama: `feature/34-integracion-movil-laravel`.
- Commit probado: `9ef48cf` (mismo commit que `origin/develop` al probar).
- Backend Laravel local: `http://127.0.0.1:8002`; dispositivo Android lo alcanzó mediante `10.0.2.2:8002`.
- Salud y catálogo territorial: HTTP 200.
- PHP CLI: 8.2.12.
- Flutter: 3.38.3; Dart: 3.10.1.
- Emulador Android: AVD Pixel_7, Android 16 / API 36.
- Consulta externa: NASA FIRMS, producto VIIRS_SNPP_NRT, conjunto 2.0NRT. No se guardan claves ni `.env` en esta evidencia.

## Pasos reproducibles

Desde la raíz del repositorio, iniciar Laravel en otra terminal:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\iniciar.ps1 -Direccion 0.0.0.0 -Puerto 8002
```

En la raíz del proyecto Flutter:

```powershell
cd movil
flutter pub get
flutter run -d emulator-5554 --dart-define=GEOCRUZ_API_BASE_URL=http://10.0.2.2:8002/api/v1/
```

Comprobaciones ejecutadas en `movil/`:

```powershell
flutter analyze
flutter test
```

Resultado: `flutter analyze` sin incidencias y todas las pruebas Flutter aprobadas (28).

## Resultado de la comparación

Consulta externa equivalente en Flutter y Blade: departamento Santa Cruz, del 24/10/2026 00:00 al 25/10/2026 00:00, hora de Bolivia (UTC−4), final excluido.

- Sin filtro de confianza: web y móvil mostraron 1.482 detecciones.
- Con `Confianza alta`: web y móvil mostraron 21 detecciones.
- La web reportó FRP máxima de 75,3 MW para el subconjunto de confianza alta.
- En el móvil comprobé lista, mapa y detalle. El detalle conserva identificador `firms-9916cdbfb5c0ced62a2222d4525d54752058f3376a0068b0445bd110c53b3244`, territorio Santa Rosa, hora observada, FRP 14,67 MW, confianza alta, NASA FIRMS, VIIRS_SNPP_NRT, versión 2.0NRT y el período consultado.
- Los registros proceden de una consulta externa al proveedor (el backend indicó `Consulta al proveedor`), no de datos simulados.

## Incidencia observada

En el móvil, el mapa dibuja las detecciones, pero muestra “Límite territorial no disponible”. Flutter registró una respuesta HTTP 200 del límite de `BO07` que no pudo decodificar como JSON (793.184 bytes). La misma URL consultada directamente desde el PC devolvió HTTP 200 y pasó la validación JSON de PowerShell y Dart. Por tanto, queda pendiente reproducir la diferencia de respuesta que recibió el emulador; no afirmo que el límite territorial esté corregido ni que la tarea esté cerrada.

El servidor local también imprimió un `PHP Parse error` al ejecutar una comprobación auxiliar del script, aunque Laravel terminó iniciando y salud, catálogo y focos respondieron. No bloqueó las consultas, pero conviene revisar el mensaje del inicializador antes de usarlo como guía final.

## Capturas

- `01-mapa-1482.png`: consulta completa en el mapa y resumen móvil.
- `02-lista-confianza-alta-21.png`: resultado móvil tras filtrar por confianza alta.
- `03-detalle-y-fuente.png`: detalle y contexto de consulta del registro.

La revisión de Josué sigue pendiente. Esta evidencia no cierra por sí sola la tarea.
