# Evidencia MOV-03 — prueba local del 25/10/2026

## Entorno

- Rama: `feature/34-integracion-movil-laravel`.
- Código probado: sin cambios respecto al commit de `develop` usado en la verificación.
- Backend Laravel local: `http://127.0.0.1:8002`; PHP CLI 8.2.12.
- Flutter: 3.38.3; Dart: 3.10.1.
- Emulador Android: AVD Pixel_7, Android 16 / API 36.
- Consulta externa: NASA FIRMS, producto VIIRS_SNPP_NRT, conjunto 2.0NRT. No se guardan claves ni `.env` en esta evidencia.

## Pasos reproducibles

Iniciar Laravel desde la raíz del repositorio, en otra terminal:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\iniciar.ps1 -Direccion 0.0.0.0 -Puerto 8002
```

Confirmar salud y catálogo en el PC. Para este emulador, conectar el puerto con ADB y ejecutar Flutter usando la dirección local reenviada:

```powershell
adb reverse tcp:8002 tcp:8002
cd movil
flutter pub get
flutter run -d emulator-5554 --dart-define=GEOCRUZ_API_BASE_URL=http://127.0.0.1:8002/api/v1/
```

Comprobaciones ejecutadas en `movil/`:

```powershell
flutter analyze
flutter test
```

Resultado: `flutter analyze` sin incidencias y las 28 pruebas Flutter aprobadas.

## Resultado de la comparación

Consulta externa equivalente en Flutter y Blade: departamento Santa Cruz, del 24/10/2026 00:00 al 25/10/2026 00:00, hora de Bolivia (UTC−4), final excluido.

- Sin filtro de confianza: web y móvil mostraron 1.482 detecciones.
- Con `Confianza alta`: web y móvil mostraron 21 detecciones.
- La web reportó FRP máxima de 75,3 MW para el subconjunto de confianza alta.
- En el móvil comprobé lista, mapa y detalle. El detalle conserva identificador `firms-9916cdbfb5c0ced62a2222d4525d54752058f3376a0068b0445bd110c53b3244`, territorio Santa Rosa, hora observada, FRP 14,67 MW, confianza alta, NASA FIRMS, VIIRS_SNPP_NRT, versión 2.0NRT y el período consultado.
- Los registros proceden de una consulta externa al proveedor (el backend indicó `Consulta al proveedor`), no de datos simulados.

## Límite territorial y ajuste del entorno

Al ejecutar Flutter con la dirección recomendada `http://10.0.2.2:8002/api/v1/`, el emulador recibió el endpoint `GET /api/v1/territorios/BO07/limite` con HTTP 200 y `Content-Type: application/json`, pero Flutter no pudo decodificar la respuesta. En la reproducción registrada, llegaron 793.168 bytes y el analizador JSON informó `Unexpected character` en el desplazamiento 269.275. El mismo endpoint desde el PC devolvió 793.219 bytes y JSON válido.

Para este entorno, `adb reverse tcp:8002 tcp:8002` y la base `http://127.0.0.1:8002/api/v1/` resolvieron el problema: Flutter cargó y dibujó el límite territorial de Santa Cruz. La captura `04-limite-territorial.png` muestra el resultado. No cambié el código de la aplicación ni del backend; la causa exacta de la diferencia al usar `10.0.2.2` queda por investigar si se necesita esa ruta.

El inicializador también había mostrado un error de PHP en una comprobación auxiliar, aunque Laravel quedó funcionando. No afecta esta comprobación del límite, que se repitió contra el backend local que ya estaba activo.

## Capturas

- `01-mapa-1482.png`: consulta completa en el mapa y resumen móvil.
- `02-lista-confianza-alta-21.png`: resultado móvil tras filtrar por confianza alta.
- `03-detalle-y-fuente.png`: detalle y contexto de consulta del registro.
- `04-limite-territorial.png`: límite de Santa Cruz visible después de ajustar la conexión del emulador.

La verificación funcional está registrada; la revisión de Josué sigue pendiente. Esta evidencia no cierra por sí sola la tarea.
