# GeoCruz

Sistema de consulta ambiental para el departamento de Santa Cruz, sus provincias y municipios. Proyecto de Sistemas III, UNIVALLE, gestión 2026. Integrantes: Josué Padilla y Jaziel Díaz.

Repositorio oficial: https://github.com/elingeyesdev/t6-medicion-ambiental

Tablero: https://github.com/users/elingeyesdev/projects/13

La [referencia de EnviroSense](referencias/envirosense/README.md) conserva el código publicado del proyecto de origen y declara las sustituciones de credenciales. Está separada de la aplicación GeoCruz. Esta línea base no incorpora las implementaciones posteriores de `develop` ni acredita su aprobación académica.

## Organización

- `web/`: backend y API compartida con Laravel, e interfaz Blade. Responsable: Josué.
- `movil/`: aplicación Flutter. Responsable: Jaziel.
- `compartido/`: contratos, catálogo y recursos comunes revisados por ambos.
- `docs/`: planificación, instrucciones y evidencias.

La base del repositorio contiene instrucciones y estructura documental. Laravel y Flutter se incorporarán en las tareas #15 y #17; este inicio no acredita aplicaciones implementadas. Los documentos de M1 y sus evidencias están vinculados en los Issues #1 a #14.

## Ramas y revisión

`main` conserva versiones estables; `develop` integra cambios revisados. Las ramas `feature/<issue>-<tarea>` parten de `develop`. Cada PR debe enlazar su Issue, explicar el cambio, indicar cómo probarlo y aportar evidencia. El compañero revisa los cambios antes de integrarlos.

Las ramas preparadas para M2 parten de la misma base inicial y todavía no contienen sus implementaciones. Antes de empezar una tarea, actualizar `develop` y sincronizar la rama con los cambios ya integrados.

## Segunda iteración

[M2 Focos de calor en web y móvil](https://github.com/elingeyesdev/t6-medicion-ambiental/milestone/2), con entrega prevista el 22 de octubre de 2026. Tareas y dependencias: [Iteración 2](docs/ITERACION_2.md).

Los cinco módulos previstos son focos de calor, sequías, inundaciones, deforestación y calidad del aire. M2 desarrolla el primero y sus bases comunes. Las claves privadas de fuentes externas se configuran únicamente en el backend y nunca se incorporan a Git. Las demostraciones se identifican y no sustituyen consultas reales.
