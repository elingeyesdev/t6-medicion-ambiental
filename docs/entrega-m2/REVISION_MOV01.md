# Revisión de MOV-01 · #17 / PR #28

Revisor: Josué Padilla. Autor de la base Flutter: Jaziel. Fecha: 19/10/2026, hora Bolivia.

## Versión y alcance

Código de Jaziel en `7e18f5015c48257f399f3b0c737f0927fdce527f`; CI añadido con la cuenta de Josué en `ca37392afec9a09ca4a1c1b839f5c6b41cc43648`. Se revisaron navegación Inicio/Módulos/detalle, cinco módulos próximos sin datos ambientales inventados, contexto de territorio/período/ID, cliente JSON y URL base configurable sin claves de proveedores.

Las pantallas de lista/detalle de registros son componentes preparados para MOV-02. Su prueba usa un registro controlado exclusivamente en `test/`. La base #17 no ofrece aún una consulta ambiental real; esa implementación pertenece a #20.

## Verificación independiente

[Ejecución de GitHub Actions 39352882869](https://github.com/elingeyesdev/t6-medicion-ambiental/actions/runs/39352882869), Ubuntu, Flutter 3.38.3 y Java 17:

- Dependencias instaladas con `flutter pub get --enforce-lockfile`.
- `flutter analyze --fatal-infos`: sin incidencias.
- `flutter test --reporter expanded`: **9 pruebas aprobadas** de configuración/cliente HTTP, navegación, estados y conservación de contexto.
- `flutter build apk --debug`: **APK compilado correctamente**.
- Artefacto del trabajo: `geocruz-mov01-debug`, con el APK de esta versión. Su retención en Actions es de siete días; no es una publicación de producción ni una prueba de NASA desde Flutter.

Los registros de esta ejecución son del 20/10/2026 UTC y del 19/10/2026 en Bolivia. La revisión local comprendió el código y las [capturas de emulador aportadas por Jaziel](../MOV01_BASE_FLUTTER.md). No se afirma haber repetido el recorrido en un emulador local del revisor: esta PC no tenía Flutter disponible y la verificación independiente se realizó en CI.

## Resultado y siguiente trabajo

Sin hallazgos importantes que impidan integrar el alcance de MOV-01. [Aprobación de Josué](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/28#pullrequestreview-5579742012) sobre el commit verificado; PR #28 fusionado en `develop` mediante `202df8fb77ace56eebc81d5b135ef6dd1d509fd7`. La revisión no acredita cumplimiento de MOV-02 ni de QA-01. Jaziel continúa con #20; ambos ejecutan después el [recorrido preparado de #21](PRUEBA_CONJUNTA.md). #22 incorpora las evidencias móviles/conjuntas y espera revisión final de Jaziel antes de cerrar M2.

## Cómo probar la base

Descargar el [artefacto geocruz-mov01-debug](https://github.com/elingeyesdev/t6-medicion-ambiental/actions/runs/39352882869/artifacts/12233857467), extraer `app-debug.apk` e instalarlo en un emulador o dispositivo Android de prueba. Su ZIP tiene SHA256 `a17f22df97969ffbb17e4adaed09623ed65db624716ec189510d3523de630228`. Abrir Inicio, entrar en Módulos, recorrer los cinco módulos y volver desde el detalle. Deben indicar “Próximamente” y no mostrar detecciones ficticias. Este recorrido inicial no necesita la clave NASA ni una consulta del backend.

Con Flutter instalado, desde `movil/`: `flutter pub get`, `flutter test` y `flutter run`. El [README móvil](../../movil/README.md) documenta destinos y configuración del backend; el código móvil consulta por defecto el puerto 8000 del emulador Android, configurable para usar otro servidor como el visor/API del puerto 8003.
