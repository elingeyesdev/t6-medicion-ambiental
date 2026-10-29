# Guía GitHub del curso: estado y acciones

**Actualización del 29/10:** revisé de nuevo las 61 páginas y el estado real del repositorio. M2 está en `main`, versión `v0.2.0`, mediante #59. Josué pidió terminar hoy el candidato M3 y toda la recuperación web; Jaziel conserva el desarrollo móvil. La revisión del ingeniero para promover M3 a `main` sigue pendiente. Se trabaja en local; publicar un tag no acredita una URL funcionando.

Se añadieron formularios de historia y error en `.github/ISSUE_TEMPLATE/`. La plantilla de PR y el helper `herramientas/github/completar-pr.ps1` ya incluyen el comando de fusión y borrado de la rama temporal. Al 29/10 la cuenta tiene permiso de escritura, sin administración: `main` sigue siendo la predeterminada y el ajuste global de borrar ramas está desactivado. Hace falta que el propietario configure protecciones y esos ajustes; no los puede activar un documento.

La guía queda contrastada en estos bloques: páginas 1-2 (propósito/índice), 3-11 (EDT, PERT, CPM y tablero), 12-21 (diseño, revisión cruzada, repositorio y stack), 22-32 (Git y comandos), 33-43 (convenciones e Issues), 44-52 (versiones, XP y PR), 53-61 (producción, APK, secretos y entrega). Los ejemplos de tienda son ejemplos del curso, no funciones que debamos añadir al sistema ambiental.

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
- M2 tiene tag y Release `v0.2.0`. M3 todavía no se etiqueta como entrega aceptada ni se incorpora a `main` con la aprobación de M2.
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

## Pendientes que no resuelve la revisión del PDF

- Propietario: proteger `main` y `develop`, exigir una aprobación y CI, bloquear force-push/borrado, elegir `develop` como predeterminada y activar el borrado automático de ramas temporales.
- Jaziel: APK release identificable e instalado, con su prueba y versión. Los artefactos debug de Actions siguen siendo pruebas de desarrollo.
- Pareja: estimar el alcance ampliado de EnviroSense y recalcular el cronograma de ProjectLibre; conservar la línea base M1 y separar el nuevo pronóstico. El backlog ampliado no sustituye ese cálculo.
- Publicación #49: pospuesta por Josué; no hay URL de producción verificada, CI/CD desplegando ni prueba PostgreSQL de producción. SQLite local permite trabajar, pero no acredita el requisito PostgreSQL del stack del curso.
- Revisión cruzada del paquete M3 y aprobación académica: se registran cuando ocurran. La generación del PDF o las pruebas automáticas no sustituyen ninguna de las dos.
