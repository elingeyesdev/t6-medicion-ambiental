# GeoCruz

Sistema de consulta ambiental para el departamento de Santa Cruz, sus provincias y municipios. Proyecto de Sistemas III, UNIVALLE, gestión 2026. Integrantes: Josué Padilla y Jaziel Díaz.

Repositorio oficial: https://github.com/elingeyesdev/t6-medicion-ambiental

Tablero: https://github.com/users/elingeyesdev/projects/13

## Organización

- `web/`: backend y API compartida con Laravel, e interfaz Blade. Responsable: Josué.
- `movil/`: aplicación Flutter. Responsable: Jaziel.
- `compartido/`: contratos, catálogo y recursos comunes revisados por ambos.
- `docs/`: planificación, instrucciones y evidencias.

La base Laravel/Blade y la comprobación de salud se incorporan en #15. Las consultas ambientales y Flutter permanecen pendientes de sus tareas. Los documentos de M1 y sus evidencias están vinculados en los Issues #1 a #14.

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

`main` conserva versiones estables; `develop` integra cambios revisados. Las ramas `feature/<issue>-<tarea>` parten de `develop`. Cada PR debe enlazar su Issue, explicar el cambio, indicar cómo probarlo y aportar evidencia. El compañero revisa los cambios antes de integrarlos.

Las ramas preparadas para M2 parten de la misma base inicial. Cada implementación se incorpora mediante su PR. Antes de empezar una tarea, actualizar `develop` y sincronizar la rama con los cambios ya integrados.

## Segunda iteración

[M2 Focos de calor en web y móvil](https://github.com/elingeyesdev/t6-medicion-ambiental/milestone/2), con entrega prevista el 22 de octubre de 2026. Tareas y dependencias: [Iteración 2](docs/ITERACION_2.md).

Los cinco módulos previstos son focos de calor, sequías, inundaciones, deforestación y calidad del aire. M2 desarrolla el primero y sus bases comunes. Las claves privadas de fuentes externas se configuran únicamente en el backend y nunca se incorporan a Git. Las demostraciones se identifican y no sustituyen consultas reales.
