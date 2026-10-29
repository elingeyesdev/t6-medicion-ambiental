# Ramas, revisión y aprobación de GeoSur

## Función de cada rama

`main` conserva las versiones aceptadas para entrega estable. **Todo PR hacia `main` necesita aprobación expresa del ingeniero** antes de su fusión. El registro de esa aprobación debe quedar enlazado en el PR. No se presume aprobación académica a partir de la revisión del compañero ni de comprobaciones automáticas correctas.

`develop` integra tareas revisadas por el compañero. Contiene las carpetas `web/`, `movil/` y `compartido/` del mismo proyecto. Las ramas no son carpetas anidadas: una rama `feature/numero-tarea` parte de un commit de `develop` y contiene el repositorio completo. Cada tarea modifica los archivos que le corresponden.

No hace falta mantener una rama permanente de web y otra de móvil. Josué desarrolla web/API y Jaziel móvil en ramas por tarea; ambos comparten catálogo y contrato mediante `develop`.

## Desarrollo normal

Con la copia sin cambios pendientes, descargar las referencias y actualizar la integración:

```powershell
git fetch origin
git switch develop
git pull --ff-only origin develop
```

Después crear `feature/NUMERO-DESCRIPCION` para el Issue real, implementar, probar y registrar commits que describan cambios reales. Abrir PR con base `develop`, Issue, instrucciones y evidencias; solicitar revisión al compañero. Corregir observaciones importantes y fusionar cuando la revisión y comprobaciones estén completas. No cerrar una tarea solo por subir commits.

Si se necesita avanzar en otra tarea antes de la aprobación, usar otra rama y hacer explícita su dependencia. No repetir en otro commit los cambios ajenos para simular avances.

## Borrar la rama al aceptar un PR

Cada PR de una tarea lleva su comando con el número real:

```powershell
gh pr merge NUMERO --repo elingeyesdev/t6-medicion-ambiental --merge --delete-branch
```

Se usa cuando el compañero ya aprobó el cambio y pasaron sus comprobaciones. `--delete-branch` elimina la rama al fusionar; no hay que dejarla abierta después de terminar la tarea. Para rellenar la descripción de cualquier PR nuevo, desde el repositorio:

```powershell
./herramientas/github/completar-pr.ps1 -Numero NUMERO -VistaPrevia
./herramientas/github/completar-pr.ps1 -Numero NUMERO
```

La ayuda conserva el resto del texto y se puede repetir sin duplicar su sección. También deja el comando correcto en los PR encadenados. `main` y `develop` son permanentes: cuando una de ellas sea el origen del PR, la ayuda omite `--delete-branch`. La aprobación académica para main sigue siendo obligatoria.

Pegar el comando en la descripción no lo ejecuta. El borrado también puede configurarse con el ajuste del repositorio **Automatically delete head branches**, pero requiere permisos de administración. El 28/10 GitHub no permitió activarlo con la cuenta de escritura actual; no se declara habilitado. Mientras tanto se usa el comando al fusionar o el botón Delete branch después de hacerlo desde GitHub.

Para ramas ya integradas, comprobar el PR y que la rama no haya recibido trabajo nuevo. Si sigue siendo base de un PR abierto, revisar primero esa dependencia. La limpieza #81 conserva los commits integrados y las carpetas de trabajo; no borra ramas pendientes ni sustituye las revisiones.

## Preparación de una entrega estable

Cuando los Issues de una entrega y sus pruebas estén completos, preparar un PR **desde `develop` hacia `main`**, registrar el corte/commit de la entrega y adjuntar evidencias reales. El ingeniero revisa esa versión concreta. Si cambia su contenido después de la aprobación, revisar de nuevo el alcance de la autorización antes de fusionar.

La fusión a `main` se hace después de su aprobación expresa. Un tag de versión, si se usa, identifica el commit efectivamente aceptado; no se crea una versión de producción ficticia. El despliegue en un servidor es un paso independiente: subir a `main` por sí solo no demuestra que exista producción.

## Propuesta de referencia original / MIG-01

### Publicación comprobada de M2 · 27/10/2026

PR #59 publicó el corte aprobado `e9a591c313f988d1649d3ccaa8086795491298f7` en main (`851a79f`). Se compararon ambos árboles: contienen exactamente los mismos archivos. Pasaron los once controles. Tag y Release: `v0.2.0`. El APK firmado continúa pendiente en #50; no hay despliegue público.

Esta recuperación usó `release/50-entrega-m2` fijada al corte aprobado porque develop ya contenía M3. Es una excepción registrada para publicar la entrega atrasada, sin retroceder develop, forzar el historial ni dar la aprobación de M2 a M3. Las próximas entregas vuelven al flujo normal develop → main con aprobación de su versión concreta.

La rama `feature/29-base-envirosense` parte del `main` existente en `6895946e20df0abdd1510e8daae54a553eaff4a8` y añade la referencia completa bajo `referencias/envirosense/`. Su PR a `main` se prepara **en borrador**, a la espera de confirmar y aprobar el orden académico. No incorpora automáticamente las funciones de M2.

La rama `feature/29-adaptacion-geocruz` parte del `develop` existente en `202df8fb77ace56eebc81d5b135ef6dd1d509fd7`, incluye la misma referencia y documenta su correspondencia con Laravel/Blade/Flutter. Se sincronizó posteriormente con `develop` en `5fd724cd1b4360e296896538f658522bfe6c8c21`, incluyendo consulta móvil y QA ya aceptadas. Se revisa mediante un PR independiente hacia `develop`.

Así se conserva la historia real de los trabajos ya realizados. No se reemplaza `main`, no se fuerza el historial y no se cambian fechas de commits para aparentar que el original se cargó antes. La copia original se identifica por su commit de procedencia y por el manifiesto de archivos.

Estos criterios documentan el flujo solicitado por Josué; **no son una protección técnica de GitHub**. Las protecciones de rama, permisos y revisores obligatorios deben configurarse por el propietario cuando el ingeniero confirme sus reglas. Este trabajo no altera permisos ni configura despliegues.
