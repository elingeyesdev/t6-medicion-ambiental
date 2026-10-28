# GeoSur

**GeoSur es la adaptación del sistema completo de EnviroSense**; focos, sequías y los otros análisis de la pareja se incorporan dentro de ese software. Sus cuentas, mapa, datos, simulaciones, reportes, alertas, notificaciones y asistente son la base del producto. La migración está incompleta y no se acredita por conservar una copia del original.

El mapa principal de esta rama tiene países/territorios y puntos ambientales; focos y sequías conservan su cobertura probada de Santa Cruz. Se mantiene la tecnología comprobada en el Planner: Laravel/Blade, JavaScript y API compartida, con Flutter para móvil. La plantilla web es Tabler 1.7.1. Las funciones originales se adaptan a esa tecnología.

- [Corrección de enfoque, alcance geográfico y funciones originales que faltan](docs/ENVIROSENSE_COMO_BASE.md).

- [Alcance vigente, funciones originales y orden de recuperación](docs/REINGENIERIA_GEOSUR.md).
- [Backlog para replanificar, sin fechas ni duraciones inventadas](planificacion/Backlog_GeoSur.csv).

El jueves 29/10 corresponde a la revisión de **M2**, ya publicada en `main` como `v0.2.0` mediante #59. M3 no tiene fecha confirmada. Se trabaja en local; Railway está pospuesto. Los documentos anteriores conservan la historia y no reemplazan este ajuste.

- [Dónde está cada parte del código y la plantilla](docs/ORGANIZACION_CODIGO.md).
- [Inicio, registro, acceso y cómo probar el mapa](docs/ACCESO_ENVIROSENSE.md).
- [Marca GeoCruz, navegación y cuenta demo local](docs/MARCA_Y_DEMO.md).
- [Acceso con cubierta de Tabler y mapa base Mapbox](docs/ACCESO_Y_MAPBOX.md).
- [Antecedente de la adaptación M3; sus exclusiones anteriores ya no son el alcance vigente](docs/REINGENIERIA_ENVIROSENSE_M3.md).
- [Revisión de la guía GitHub del curso y pendientes reales](docs/GUIA_CURSO_ESTADO.md).

Los apartados siguientes conservan instrucciones y resultados de la base integrada; el estado vigente de adaptación se sigue en #29, #47-50 y #53.

La primera implementación y M2 se centraron en el departamento de Santa Cruz, sus provincias y municipios; ese corte histórico no limita el software completo de EnviroSense. Proyecto de Sistemas III, UNIVALLE, gestión 2026. Integrantes: Josué Padilla y Jaziel Díaz.

Repositorio oficial: https://github.com/elingeyesdev/t6-medicion-ambiental

Tablero: https://github.com/users/elingeyesdev/projects/13

La [referencia de EnviroSense](referencias/envirosense/README.md) conserva el código publicado del proyecto de origen y declara las sustituciones de credenciales. Su [adaptación a GeoCruz](docs/MIGRACION_ENVIROSENSE.md) relaciona las funciones del referente con Laravel 12, Blade, la API común y Flutter, e identifica lo que sigue pendiente.

## Organización

- `web/`: backend y API compartida con Laravel, e interfaz Blade. Responsable: Josué.
- `movil/`: aplicación Flutter. Responsable: Jaziel.
- `compartido/`: contratos, catálogo y recursos comunes revisados por ambos.
- `docs/`: planificación, instrucciones y evidencias.
- `referencias/envirosense/`: código de origen conservado para comparación; no forma parte del arranque de GeoCruz.

La base Laravel/Blade, el catálogo, la API y el visor web están integrados mediante PR #23 a #26. Flutter y su consulta de focos se integraron mediante PR #28 y #32. QA-01 se revisó e integró mediante PR #33; sus 11 casos por cliente y comparación de 25 registros están en [QA-01](docs/qa21/README.md). M1 y sus evidencias están vinculados en los Issues #1 a #14. Josué confirmó la aceptación académica de M1 y M2 el 26/10; la revisión de los cambios de M3 se realiza aparte.

El [visor de sequías #37](docs/WEB02_VISOR_SEQUIAS.md) y la API #36 ya están integrados en develop mediante los PR #45 y #43. Muestra el piloto histórico preparado, sus faltantes y cobertura; no acredita las otras 55 series ni la integración móvil. El recorrido de inicio y acceso de esta rama sigue en revisión mediante PR #51. En esta copia, iniciar `./iniciar-web.ps1 -Puerto 5791` y abrir <http://127.0.0.1:5791/>; crear una cuenta para entrar al mapa y a las consultas. Los módulos de inundaciones, deforestación y calidad del aire siguen pendientes.

API de #18: [contrato y pruebas manuales](compartido/api/README.md). En esta copia de trabajo puede iniciarse con `./iniciar-api.ps1` y probarse en http://127.0.0.1:8002/api/v1/territorios. La configuración privada está en `web/.env`, excluida de Git. [Resultados y evidencia](docs/API01_FOCOS_CALOR.md).

Visor web de #19: `./iniciar-web.ps1` y abrir http://127.0.0.1:8003/modulos/focos-calor. Mapa/lista por página, filtros, ficha y resumen del conjunto: [guía, organización del código y evidencia](docs/WEB01_VISOR_FOCOS_CALOR.md). El PR #26 fue aprobado por Jaziel y fusionado el 19/10/2026.

[Demostración sencilla de la consulta NASA](docs/API01_DEMOSTRACION_NASA.md): con el servidor iniciado, ejecutar `./demostrar-api.ps1 -FechaUtc 2026-10-17 -Confianza nominal`. Explica en terminal la respuesta externa y sus límites para la revisión de #18; no añade una interfaz de mapa ni modifica el contrato.

## Arranque de la base web

Requiere PHP 8.2 o superior, sus extensiones y Composer 2. Instrucciones completas: [Web y API](web/README.md).

En Windows, desde la raíz de la copia oficial, con PHP disponible en PATH:

```powershell
.\preparar.ps1
.\iniciar.ps1
```

La preparación descarga Composer de su sitio oficial, verifica SHA256 y usa `web/composer.lock`. Si PHP es portable y no tiene `php.ini`, el ejecutor crea una configuración local en `tmp/`; no cambia la instalación global. La aplicación abre en http://127.0.0.1:8000 y su salud JSON en http://127.0.0.1:8000/api/salud. Detener con Ctrl+C.

En otros sistemas, dentro de `web/`:

```sh
composer install
composer run setup
composer run dev
```

Pruebas aisladas: `composer test` dentro de `web/`. La conexión de Flutter y los ejemplos de URL están documentados en [Desarrollo compartido](compartido/DESARROLLO.md).

## Ramas y revisión

`main` conserva versiones estables y requiere aprobación expresa del ingeniero para nuevas fusiones; `develop` integra cambios revisados. Las ramas `feature/<issue>-<tarea>` parten de `develop`. Cada PR debe enlazar su Issue, explicar el cambio, indicar cómo probarlo y aportar evidencia. El compañero revisa los cambios antes de integrarlos. [Flujo y propuesta de línea base](docs/FLUJO_GIT.md).

Las ramas preparadas para M2 parten de la misma base inicial. Cada implementación se incorpora mediante su PR. Antes de empezar una tarea, actualizar `develop` y sincronizar la rama con los cambios ya integrados.

## Segunda iteración

[M2 Focos de calor en web y móvil](https://github.com/elingeyesdev/t6-medicion-ambiental/milestone/2), con entrega prevista el 22 de octubre de 2026. Tareas y dependencias: [Iteración 2](docs/ITERACION_2.md).

[Índice de demostración y evidencias M2](docs/entrega-m2/README.md): versiones, PR, arranque, recorrido web y pendientes de móvil/revisión. Preparación parcial de #22; no acredita entrega final ni aceptación del milestone.

Los cinco módulos previstos son focos de calor, sequías, inundaciones, deforestación y calidad del aire. M2 desarrolla el primero y sus bases comunes. Las claves privadas de fuentes externas se configuran únicamente en el backend y nunca se incorporan a Git. Las demostraciones se identifican y no sustituyen consultas reales.
