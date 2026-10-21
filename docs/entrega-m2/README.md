# Segunda entrega de GeoCruz - M2

Corte: **21/10/2026**, después de integrar QA-01 mediante [PR #33](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/33), merge `5fd724cd1b4360e296896538f658522bfe6c8c21`. Entrega prevista: **22/10/2026**, sin alterar la planificación.

**Estado:** implementación y QA integrados en `develop`; paquete ENT-02 / #22 preparado para revisión de Jaziel. Su revisión final, la aceptación de M2 y la validación académica siguen pendientes. `main` no se modifica.

## Qué presentar

El documento único [GeoCruz - Segunda iteración](../../output/pdf/GeoCruz_M2.pdf) resume alcance, calidad, capturas y demostración. Este índice enlaza la evidencia técnica completa sin adjuntar múltiples documentos.

Se exporta una copia al Escritorio en `2da iteracion/GeoCruz_M2.pdf`, idéntica en SHA256. La entrega al docente no se considera realizada por generar el archivo.

## Avance integrado

- #15 / PR #23: base Laravel 12, Blade y salud de API.
- #16 / PR #24: catálogo y geometrías versionadas de Santa Cruz.
- #17 / PR #28: base Flutter, navegación y cliente de API.
- #18 / PR #25: NASA FIRMS, normalización y contrato común.
- #19 / PR #26: visor web, filtros, mapa/listado, detalle y resumen.
- #20 / PR #32: visor Flutter y contexto; correcciones de salida durante consultas y aviso de cobertura. [Revisión](REVISION_MOV02.md).
- #21 / PR #33: capturas externas y matriz ejecutada. [QA completo](../qa21/README.md).

Sequías, inundaciones, deforestación y calidad del aire conservan sus accesos y requisitos; sus consultas están pendientes. Las versiones publicadas de catálogo/contrato se mantienen y la aceptación técnica no certifica límites legales.

## Evidencia y pruebas

- Consulta externa de Jaziel: Warnes, 14/10/2026 00:00 a 21/10/2026 00:00 Bolivia; una detección, mismo ID, FRP 3,43 MW, confianza nominal y NASA FIRMS / VIIRS_SNPP_NRT. [Capturas originales](../QA-01%20-%20Verificar%20integraci%C3%B3n%20web%20m%C3%B3vil%20y%20fuente%20de%20focos%20de%20calor/).
- Replay controlado: **11 casos por cliente y 25 registros comparados**. [Matriz](../qa21/matriz.json), [resultado](../qa21/resultados/comparacion.json) y [CI](https://github.com/elingeyesdev/t6-medicion-ambiental/actions/runs/39681981989).
- **32 pruebas JavaScript**; Laravel PHP 8.2/8.3 con **35 casos y 263 aserciones**, con avisos del entorno de pruebas. [CI web/API](https://github.com/elingeyesdev/t6-medicion-ambiental/actions/runs/39681982057).
- Flutter: análisis correcto, **28 pruebas y APK debug compilado**. [CI Flutter](https://github.com/elingeyesdev/t6-medicion-ambiental/actions/runs/39681981850).
- [Corte técnico](cierre_tecnico_20261021.json): versiones, resultados y pendientes.

Las capturas son la ejecución externa aportada por Jaziel. Las nuevas pruebas son replay/fixtures; no una consulta NASA nueva ni una ejecución de todos los estados en emulador por el revisor. La comparación se limita al conjunto y campos comprobados. [Alcance y restricciones](../qa21/README.md).

## Arranque y demostración

Requiere PHP 8.2+, dependencias Laravel y, para móvil, Flutter 3.38.3 y dispositivo/emulador. Claves únicamente en `web/.env`.

Desde la raíz oficial:

```powershell
.\preparar.ps1
.\iniciar.ps1 -Direccion 0.0.0.0 -Puerto 8002
```

Web: **http://127.0.0.1:8002/modulos/focos-calor**. Salud: http://127.0.0.1:8002/api/salud.

En otra terminal, dentro de `movil/`: `flutter pub get` y `flutter run`. El emulador usa `http://10.0.2.2:8002/api/v1/`; en un teléfono configurar IP LAN mediante `GEOCRUZ_API_BASE_URL`. [Guía móvil](../../movil/README.md).

Mostrar iguales territorio, período y confianza, abrir el mismo ID y comparar fuente, fecha y FRP/unidad. Usar casos controlados identificados para vacío, cobertura, fallo/reintento y expiración. La consulta histórica puede dejar de estar disponible; para una nueva demostración elegir un período admitido.

## Repetir QA sin proveedor

Desde `web/`: `node --test tests/Js/qa21-compartida.test.mjs`. Desde `movil/`: `flutter test test/qa21_compartida_test.dart --reporter expanded`. Desde la raíz: `python herramientas/verificar_qa21.py`.

## Revisión final pendiente

Jaziel debe revisar el paquete y PR #27. Después se registra la aceptación de #22 y M2. Cualquier promoción a `main` requiere autorización del ingeniero. La referencia EnviroSense #30/#31 tiene revisión separada.

[Historial del índice](HISTORIAL_INDICE_M2.md), [corte previo](integracion_20261021.json), [decisiones](../DECISIONES_M2.md) y [guía de repetición](PRUEBA_CONJUNTA.md) se conservan con sus fechas.
