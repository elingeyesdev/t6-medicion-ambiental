# Guía GitHub del curso: estado y acciones

**Actualización del 27/10:** M2 ya está publicada mediante PR #59 y versión `v0.2.0`, con contenido idéntico al corte aprobado y once controles correctos. La afirmación histórica de que no había tags/Releases queda sustituida para M2. APK firmado, protecciones del propietario y publicación siguen pendientes. Josué pidió trabajar en local. El alcance vigente es [GeoSur con todas las funciones de EnviroSense](REINGENIERIA_GEOSUR.md); el jueves corresponde a M2 y M3 no tiene fecha confirmada.

Revisión del 26/10/2026 de las 61 páginas de `gitub para equipos en xp.pdf`. Seguimiento #50. Josué aceptó mantener GitHub Projects y ProjectLibre en lugar de añadir Microsoft Planner; el resto del flujo se toma como base del trabajo nuevo. Este registro no presenta como implementado un permiso, servidor o APK que todavía falta comprobar.

## Planificación y diseño (páginas 3-21)

- EDT, PERT, CPM, casos de uso, historias, prototipos y responsables: entregados en M1, con revisión académica comunicada por Josué después de la defensa. El ajuste de adaptación de EnviroSense está registrado para M3 y debe trasladarse al seguimiento real sin reescribir M1.
- Un repositorio con web/, movil/, docs/ y pruebas: disponible. API común y consumo móvil de focos: aceptados. Las pantallas nuevas necesitan la prueba cruzada de su versión.
- Stack A: Laravel/Blade y Flutter conservados. Configuración de PostgreSQL y publicación en Railway: #49; la instalación local anterior no acredita ese despliegue.
- Plantilla web real: Tabler en #47. Material 3 y menú móvil: #48, a cargo de Jaziel.

## Git, Issues y ramas (páginas 22-43)

- Git conserva el historial y GitHub recibe los cambios por ramas y PR. Se mantienen autores reales y no se cambian fechas o mensajes publicados para aparentar otro proceso.
- Las ramas nuevas usan tipo/número-descripción: feature para función, fix para error en develop, hotfix para urgencia real de una versión publicada desde main, docs/test/refactor/ci según el cambio. No se generan hotfix ni commits vacíos como evidencia.
- Los commits nuevos usan tipo(alcance): descripción breve en imperativo, con referencia al Issue en el cuerpo cuando no cabe en el título de 50 caracteres. Los comentarios describen pasos y resultados en lenguaje directo. Coautoría solo cuando corresponde a participación real de un integrante.
- Los Issues conservan tareas, criterios, responsable, producto, milestone y enlaces al tablero. Los nuevos #47-50 detallan la adaptación, el diseño y la publicación.
- Pendiente administrativo: la rama predeterminada sigue siendo main. Por eso un Closes en un PR hacia develop no garantiza cierre automático al integrarlo; el propietario debe adoptar develop como indica el curso. No se simula ese cambio con una modificación del README.

## Revisión, versiones y producción (páginas 44-61)

- Hay CI de Laravel, JavaScript, contrato, geometrías y Flutter. Los cambios funcionales van a develop mediante PR con prueba del compañero. Una comprobación correcta no sustituye esa aprobación.
- Pendiente administrativo: main y develop aparecen sin protección. La cuenta de Josué tiene push/triage, sin admin/maintain; el propietario debe exigir PR, una aprobación y checks correctos y bloquear force-push/borrado.
- No hay tags/Releases publicados al realizar esta revisión. La entrega aprobada se identifica primero por su corte real; no se debe incluir M3 en la aprobación de M2.
- No hay URL pública ni despliegue Railway verificados. #49 prepara construcción, PostgreSQL, variables privadas, datos persistentes y CI. Main/producción requiere la aprobación de la entrega; develop puede tener su entorno separado de pruebas.
- El workflow móvil actual compila debug y sube un artefacto temporal. Falta el APK release versionado y verificable que pide el curso, con su configuración de firma y la URL del backend. Se coordina con Jaziel en #50; no se renombra un debug como release.
- El .gitignore excluye .env, claves, dependencias y bases privadas. Los ejemplos de entorno y evidencias no deben contener secretos. Un despliegue no puede habilitar debug ni exponer publicaciones privadas por una ruta estática.

## Convención práctica desde este cambio

Actualización del 27/10, #50 / #54: las comprobaciones web y de comparación de clientes aceptan también PR con base `refactor/**`; la comparación acepta `feature/**` como la comprobación web. Esto permite revisar cambios dependientes como #54 sobre la base candidata #51 sin saltarse CI. Se conservan filtros por archivos y permisos de solo lectura. No otorga permisos administrativos ni sustituye la aprobación del compañero.

1. Issue y criterios listos, con EDT/actividad y responsable; trabajar en su rama desde develop actualizado o declarar la dependencia candidata.
2. Cambios pequeños, pruebas reales y PR a develop con qué cambió, cómo probar y evidencia de esa versión.
3. El compañero prueba y aprueba; resolver fallos antes de integrar. Borrar ramas fusionadas cuando ya no sean necesarias, sin eliminar worktrees con trabajo pendiente.
4. Publicar cada entrega aprobada mediante el flujo de main que acuerde el propietario, con tag, Release, URL y APK de la misma versión. La fecha del PDF es un ritmo de curso; se conserva el calendario de revisiones aceptado para GeoCruz.
5. Si hay un error urgente en una versión efectivamente publicada, registrar bug, partir de main con hotfix, probar/aprobar y devolver la corrección también a develop. No crear un error ficticio para practicar.
