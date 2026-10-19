# MOV-01 · Base Flutter y navegación común

Issue #17 · Responsable de la tarea: Jaziel · Revisor solicitado: Josué.
Rama: `feature/17-base-flutter-navegacion` · Base sincronizada con `develop`
al commit `9bebd53` antes de iniciar los cambios.

## Resultado

Se generó `movil/` como aplicación Flutter Android/iOS con dependencias
registradas en `pubspec.lock`. La plantilla visual nativa sigue la paleta,
navegación por módulos, territorio y lenguaje de la web GeoCruz. No usa imágenes
remotas o generadas.

La navegación común tiene Inicio y Módulos; cada módulo abre una pantalla de
estado. Los cinco módulos móviles se muestran como próximos. Focos de calor ya
existe en web, pero su consulta en Flutter corresponde a MOV-02. No se
presentan registros ambientales ficticios ni se interpreta la falta de consulta
móvil como resultado vacío o falta de cobertura.

Se añadió el modelo de contexto de consulta y las plantillas reutilizables de
lista/detalle. El paso al detalle conserva ID, territorio y período; las fechas
se presentan en hora de Bolivia (UTC−4), con inicio incluido y fin excluido.
Una prueba de widget usa un registro controlado únicamente en `test/` para
comprobar ese paso; no forma parte de la aplicación.

El cliente HTTP usa `GEOCRUZ_API_BASE_URL`, valida la URL base, codifica
parámetros de consulta y trata respuestas JSON/errores. La URL predeterminada es
la dirección del emulador Android para el backend local. No se añadieron claves
de proveedores en Flutter. MOV-01 no ejecuta consultas ambientales; el consumo
de focos de calor queda para MOV-02.

## Verificación realizada

- `flutter analyze`: sin incidencias.
- `flutter test`: 9 pruebas aprobadas, de cliente/configuración HTTP, estados de
  disponibilidad, navegación entre módulos y conservación de contexto lista →
  detalle.
- Emulador Android `sdk gphone64 x86 64`, Android 16 / API 36: APK debug
  compilado e instalado; aplicación abierta y recorrida. Flutter perdió la
  conexión de depuración durante el primer arranque del emulador, pero la
  aplicación se volvió a abrir y se capturaron las pantallas siguientes.
- Capturas: [inicio](evidencias/mov01/inicio_emulador.png),
  [cinco módulos](evidencias/mov01/modulos_emulador.png) y
  [detalle de focos](evidencias/mov01/detalle_modulo_emulador.png).

## Criterios de MOV-01

| Criterio | Estado en esta rama |
|---|---|
| Aplicación Flutter bajo `movil/`, dependencias reproducibles y pasos de ejecución | Implementado; `pubspec.lock` y `movil/README.md` incluidos. |
| Navegación según el diseño de GeoCruz; conservar territorio, período e ID lista→detalle | Implementado; plantillas y prueba de widget. Revisión de diseño por Josué pendiente. |
| Cliente HTTP y URL configurable, sin claves de proveedor en Flutter | Implementado; integración con datos reales pendiente de MOV-02. |
| Mostrar módulos futuros sin simular implementación ni reemplazar datos | Implementado; los cinco módulos muestran “Próximamente” y no se presentan datos ficticios. |
| Ejecución real, pruebas iniciales y evidencia | Registrado arriba y capturado en `docs/evidencias/mov01/`. |
| Pull request a `develop` revisado por Josué | La revisión humana no se presume; requiere PR y aprobación explícita del compañero. |

## Cómo ejecutar

Los requisitos, comandos, destinos y configuración de URL están en
[`movil/README.md`](../movil/README.md). La configuración Flutter de esta
estación quedó apuntando al JDK 17 instalado, necesario porque el Android
Gradle Plugin del proyecto usa Gradle 8.14.
