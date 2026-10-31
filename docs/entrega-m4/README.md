# PREPARACIÓN de evidencias M4 — #118

Este recolector produce `indice.md` y `resumen.json` a partir de un `snapshot.json`
suministrado. Valida el formato y señala contradicciones, faltantes y antigüedad;
las afirmaciones siguen siendo **declaradas en el snapshot**, sin verificación
remota de identidad, aprobación, implementación o CI. `delivery_status` siempre
es `not_evaluated`, incluso si todos los campos son favorables. No genera PDF,
paquete final, aceptación académica, porcentajes de avance ni cronograma.

Implementación: Python **3.11 o posterior**, solo biblioteca estándar. No usa
red, `gh`, subprocessos, tokens ni credenciales. La captura REST real la realiza
el responsable aparte. No se consulta Actions, check-runs ni CI por CLI; un enlace
de evidencia recibido se conserva y no se visita.

## Contexto comunicado al 31/10/2026

Estos son antecedentes de este encargo, no el estado actual de una captura
posterior. Las notas del informe llevan fecha y no bloquean ni completan tareas.

- M3: #39, #40 y #41 siguen abiertas. Son antecedentes y dependencias; no pasan
  a formar parte del trabajo terminado de M4. #47 debe capturarse por su relación
  con #113, sin asumir su estado a partir de documentos antiguos.
- #39: se comunicaron tres fallos Flutter y cambios solicitados en `b176111`.
  Es un antecedente; capturar el SHA completo, su PR real y la vigencia de la
  revisión. El estado REST es `CHANGES_REQUESTED`. No se modifica código móvil.
- #110 / PR #120: propuesta de inundaciones, sin producto de agua acreditado.
  HEAD cambiará con la ampliación de 24 pruebas / ocho parejas SCL y metadata de
  radar. Recapturar el SHA tras ese cambio. Una prueba o CI sobre un commit anterior
  no acredita el HEAD nuevo; los recuentos de pruebas no prueban CI.
- #116 / PR #119: forestal en revisión; sin cambios comunicados en este corte.
  El snapshot debe incluir igualmente su HEAD y revisiones reales.
- #109: planificación pendiente de Jaziel y capacidad. #114: Flutter de Jaziel.
- M4 tiene meta de planificación **05/11/2026**, sin fecha académica confirmada.
  `main` conserva M2 según el contexto suministrado. El recolector no consulta ni
  cambia ramas, issues, PR, revisiones ni milestones.

Las dependencias mínimas conservadas son:

```text
110 <- 40
111 <- 39, 110
112 <- 111
113 <- 112, 47
114 <- 112
115 <- 111, 113, 114
117 <- 115, 116
118 <- 41, 109, 115, 117
```

Se unen con las dependencias adicionales del snapshot. Omitir una arista conocida
genera un pendiente y no elimina el bloqueo. No se trasladan tareas entre fases
silenciosamente: se conserva el milestone declarado y se señala la discrepancia.

## Ejecutar

Desde la raíz del repositorio, en PowerShell:

```powershell
New-Item -ItemType Directory -Force -Path herramientas/entrega_m4/salidas | Out-Null
python -B -m herramientas.entrega_m4 `
  --snapshot herramientas/entrega_m4/snapshot.local.json `
  --output herramientas/entrega_m4/salidas/preparacion-real-01
```

`snapshot.local.json` debe construirse con una captura nueva cuando se vuelva a revisar el estado. La [captura real archivada del 31/10](evidencias/2026-10-31/snapshot.json) conserva 20 tareas críticas y sus dependencias, siete PR y las revisiones recibidas. El [índice generado](evidencias/2026-10-31/preparacion/indice.md) y su resumen corresponden a esa fecha, sin cambios remotos. El [inventario separado de M3](evidencias/2026-10-31/pendientes-m3.json) incluye todas sus issues abiertas en la consulta indicada. El padre de `--output` debe existir y el directorio final debe ser
nuevo. Cada ejecución necesita otro nombre. No hay opción `--force`.

Para reproducir únicamente el ejemplo sintético:

```powershell
python -B -m herramientas.entrega_m4 `
  --snapshot herramientas/entrega_m4/fixtures/snapshot.json `
  --output herramientas/entrega_m4/salidas/fixture-01 `
  --fixture-now 2026-10-31T01:00:00Z
```

La [fixture completa](../../herramientas/entrega_m4/fixtures/snapshot.json) sirve
como plantilla de estructura y prueba, **no como captura real**. Sus números de
milestone, títulos, estados y SHA son sintéticos. No basta cambiar `source` para
convertirla en evidencia. El reloj fijo solo está permitido para
`source: controlled_fixture`; las capturas reales usan el reloj UTC del equipo.

Opciones: `--max-age-hours 24` limita la antigüedad (mayor que cero, máximo 8760 h);
`--fail-on-pending` devuelve código 3 tras publicar si hay observaciones. Sin esa
opción, los pendientes no impiden guardar el informe. Códigos: **0** ejecución
correcta, **2** entrada/argumentos/archivos inválidos, **3** informe publicado con
pendientes cuando se pidió ese control. Un código 0 no significa M4 terminada.

## Contrato de `snapshot.json`, versión 1

JSON UTF-8, con o sin BOM de PowerShell; máximo 5 MiB. Todas las claves enumeradas
son obligatorias y no se admiten claves adicionales. Listas: hasta 500 elementos.
Identificadores: enteros positivos, nunca booleanos; sin números/IDs duplicados
dentro de una misma colección. Fechas con hora: UTC `YYYY-MM-DDTHH:MM:SSZ`.
SHA: **40 caracteres hexadecimales en minúscula**, sin abreviaturas ni SHA inferido.

Raíz:

```json
{
  "schema_version": 1,
  "repository": "elingeyesdev/t6-medicion-ambiental",
  "source": "provided_snapshot",
  "captured_at": "2026-10-31T01:00:00Z",
  "validation": {"by": null, "at": null},
  "milestones": [],
  "issues": [],
  "pull_requests": []
}
```

El ejemplo es estructural e incompleto: las colecciones vacías generan faltantes.
`captured_at` debe ser el instante real de cierre de la captura, después de leer
los registros. `source` admite `provided_snapshot` o `controlled_fixture`.
`validation.by` es el login de quien revisó la captura y `validation.at` su fecha
UTC, entre `captured_at` y la evaluación. Usar ambos `null` si no se validó: el
informe preserva esa incertidumbre. No se autentica la identidad declarada.

Cada elemento de `milestones`:

```json
{
  "number": 4,
  "phase": "M4",
  "title": "Título real del milestone",
  "state": "open",
  "updated_at": "2026-10-31T00:00:00Z",
  "target_date": "2026-11-05",
  "academic_deadline_confirmed": false
}
```

Incluir uno para M3 y otro para M4, con sus números reales. `phase`: `M3` / `M4`;
`state`: `open` / `closed`; `target_date`: fecha `YYYY-MM-DD` o `null`;
`academic_deadline_confirmed`: booleano o `null` si se desconoce. Una fecha objetivo
del milestone no implica aceptación ni fecha académica: para la meta M4
comunicada, usar `2026-11-05` y `false`. Estas fechas se conservan, no se calculan.

Cada elemento de `issues`:

```json
{
  "number": 110,
  "title": "Título real de la tarea",
  "milestone": 4,
  "state": "open",
  "updated_at": "2026-10-31T00:00:00Z",
  "dependencies": [40],
  "pull_requests": [120],
  "implementation": "proposal",
  "engineer_acceptance": {
    "status": "unknown",
    "by": null,
    "at": null,
    "evidence_url": null
  }
}
```

- `milestone`: número del milestone capturado o `null` si está sin asignar.
  Un milestone no capturado queda desconocido. No se adivina la fase por el título.
- `dependencies`: números de issues que bloquean esta tarea. `null` significa
  captura incompleta/desconocida; `[]`, lista comprobada sin dependencias.
  Registrar al menos las aristas de la línea base, sin eliminar otras reales.
- `pull_requests`: números de PR asociados o `null` si no se capturaron. `[]`
  significa que se comprobó la ausencia de PR; no prueba integración.
- `implementation`: `unknown`, `proposal`, `in_progress` o `implemented`.
  GitHub no determina esto por cerrar una issue: debe ser una afirmación revisada
  por el responsable del snapshot. Usar `unknown` si no hay fundamento.
- `engineer_acceptance.status`: `unknown`, `pending`, `accepted` o `rejected`.
  Es un dato independiente de compatibilidad, no un requisito técnico por tarea.
  `accepted` requiere `by`, `at` y `evidence_url`, con fecha no posterior a la
  captura, para quitar su advertencia individual de entrega. Se conserva como
  aceptación **declarada**, sin certificar identidad/rol ni entrega final.
  El ingeniero aprueba el corte a `main`, no cada dependencia técnica; Jaziel
  realiza la revisión técnica. La aceptación académica de M3/M4 no se infiere
  por issue: los estados `pending`/`unknown` individuales no bloquean dependencias.
  La aprobación del corte se verifica fuera de este recolector de preparación.

Cobertura mínima: #39, #40, #41, #47 y #109–118. Incluir también todo issue
referenciado por dependencias o PR. La ausencia se informa, no se interpreta
como issue cerrada, inexistente o trabajo entregado.

Cada elemento de `pull_requests`:

```json
{
  "number": 120,
  "title": "Título real del PR",
  "issues": [110],
  "state": "open",
  "merged": false,
  "draft": false,
  "head_sha": "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa",
  "updated_at": "2026-10-31T00:00:00Z",
  "merged_at": null,
  "reviews_complete": false,
  "reviews": [],
  "checks": {"status": "unknown", "sha": null, "evidence_url": null}
}
```

El SHA de este ejemplo es sintético. En la captura real, copiar literalmente
`head.sha` actual; recapturar #120 después de su ampliación. `state` REST es
`open` / `closed`; la fusión se declara aparte con `merged` y `merged_at`. No usar
`merge_commit_sha` como HEAD: ese campo no acredita por sí solo una fusión.
`merged_at` es fecha UTC o `null`; `merged`, `draft` y `reviews_complete` son
booleanos. Las asociaciones `issues` ↔ `pull_requests` deben ser recíprocas.
Incluir PR #119, #120 y los restantes PR asociados, incluido el real de #39.

Cada elemento de `reviews`:

```json
{
  "id": 123,
  "by": "login-real",
  "state": "CHANGES_REQUESTED",
  "commit_sha": "bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb",
  "submitted_at": "2026-10-31T00:00:00Z"
}
```

De nuevo, ID/login/SHA son ejemplos. Mapear `id`, `user.login`, `state`,
`commit_id`, `submitted_at` del registro real. Estados admitidos: `APPROVED`,
`CHANGES_REQUESTED`, `COMMENTED`, `DISMISSED`, `PENDING`. `submitted_at` puede ser
`null`, pero una decisión sin fecha conserva incertidumbre. Poner
`reviews_complete: true` únicamente si se capturó todo el historial paginado.
La última decisión de cada revisor cuenta; un comentario no revoca una
aprobación, una solicitud de cambios o desestimación posterior sí la afecta.
Una aprobación de otro SHA no aprueba HEAD; cambios solicitados de otro revisor
siguen pendientes. Una fecha posterior a la captura invalida la inferencia.

`checks` siempre tiene `status`, `sha` y `evidence_url`. Estados: `unknown`,
`pending`, `success`, `failure`. Si solo se dispone de metadata de issues/PR y
reviews, usar **`unknown`, `null`, `null`**. Para declarar otro estado, aportar
el SHA al que pertenece y una referencia externa obtenida por un medio permitido;
sin referencia o con otro SHA, la observación vuelve a `unknown`. Se conserva el
valor original para auditar la contradicción. No se trasladan resultados de
commits anteriores ni se infiere éxito por tener tests escritos o ejecutados.

Los títulos se limitan a 300 caracteres sin controles. Los autores/validadores
son logins, no correos ni credenciales. `evidence_url`: `https://github.com/OWNER/REPO/`
del mismo repositorio, rutas `issues/N`, `pull/N`, `pull/N/reviews/N`,
`actions/runs/N[/job/N]` o `commit/SHA`, con fragmento alfanumérico/guion/guion
bajo opcional; sin query, usuario, contraseña ni host alternativo. Las URLs de
Actions solo son referencias suministradas; el programa no consulta esos recursos.

No incluir bodies, comentarios completos, headers, respuestas crudas, variables
de entorno ni archivos de autenticación. El contrato rechaza campos extra y
patrones habituales de credenciales, con errores que no reproducen los valores.
El responsable debe suministrar únicamente metadata saneada; una clave privada
desconocida no puede detectarse con certeza mediante patrones de texto.

## Lectura de los resultados y publicación

`indice.md` separa M3, M4 y registros sin fase confirmada, y enlaza las tareas,
PR y resumen. Expone HEAD, SHA revisado, revisión, checks y pendientes. M3 conserva
su clasificación de línea base aunque un snapshot contradictorio diga M4.

`resumen.json` contiene procedencia, SHA-256 de los **bytes originales** del
snapshot, fecha de evaluación, vigencia, milestones declarados, tareas por grupo,
dependencias declaradas/consideradas/no resueltas, observaciones de PR y `pending`.
`pending_count` cuenta observaciones, **no tareas ni porcentaje de avance**.
Los sufijos `reported` identifican afirmaciones recibidas; `unknown` no es OK.
`pending` distingue las categorías `technical`, `delivery_warning` y `snapshot`.
Por tarea, `technical_pending_codes` determina los bloqueos técnicos;
`delivery_warning_codes` conserva advertencias independientes. `pending_codes`
reúne ambas listas. `--fail-on-pending` considera todas las observaciones.

Una dependencia conserva pendientes técnicos por issue abierta, implementación
sin confirmar, PR sin integrar/revisar o checks vigentes sin confirmar,
relaciones contradictorias o dependencias transitivas sin resolver. Una issue
cerrada e implementada con PR integrado, revisión técnica y checks del SHA
actual puede desbloquear dependientes aunque la aprobación académica siga
pendiente. `engineer_acceptance_not_confirmed` y `closed_issue_is_not_delivery`
son advertencias de entrega: **no forman parte del bloqueo técnico**.
Ciclos y registros posteriores a la captura se señalan.
Una captura vencida, futura, sintética o sin validación impide presentar las
dependencias como resueltas. Cerrar milestones tampoco certifica M4.

Los dos archivos se escriben primero en un staging hermano y se publican con
rename atómico **sin reemplazo**, incluso ante una colisión con un directorio
vacío. Soporte: Windows y Linux con `renameat2(RENAME_NOREPLACE)`; si el sistema
no lo permite, falla sin publicar. Ante error normal se limpia solo el staging
propio. Una terminación forzada puede dejar staging oculto sin publicar: no es
una salida válida. No se copia el snapshot bruto ni se adjuntan credenciales.
`salidas/` y `snapshot.local.json` están ignorados localmente por Git.

## Verificación

```powershell
python -B -m unittest discover -s herramientas/entrega_m4/tests -p 'test_*.py' -v
```

Los escenarios comprueban contradicciones entre apertura/fusión, aprobación de
otro SHA, cambios solicitados y desestimación, reviews incompletas, checks
desconocidos o de otro commit, cierre sin aceptación del ingeniero, dependencias
faltantes/omitidas/cíclicas, separación M3/M4, antigüedad, fechas futuras,
credenciales, publicación interrumpida y colisiones. Un caso favorable completo
comprueba que tampoco se certifique la entrega. Otro avanza HEAD de #120 tras las
pruebas SCL y comprueba que no herede aprobación ni CI anterior. Se comprueba
también que una dependencia técnicamente completa sin aceptación académica
desbloquee al dependiente, sin certificar entrega, y que una captura posterior
use sus estados actuales sin adoptar las notas históricas como estado vigente.

El [workflow](../../.github/workflows/entrega-m4.yml) ejecuta esta suite y genera
solo un artefacto de **fixture de preparación**, en Windows y Linux. Un resultado
verde de ese workflow prueba el recolector, no el producto M4 ni el CI vigente
de #119/#120. Esta rama no consulta ni ejecuta CI remoto por CLI.


## Corte real de preparación · 31/10/2026

- #110 / #120: ocho parejas ópticas verificadas; la mejor conserva 9,49 % de la ventana comparable. La alternativa RTC tiene metadatos disponibles, pero lectura raster pendiente de cuenta/acceso. No hay producto de inundación. [Comprobación propuesta](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/120).
- #116 / #119: fuente forestal y contrato propuestos, con consulta pequeña real y pruebas. Falta acuerdo y revisión; #117 conserva sus dependencias. [Propuesta](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/119).
- #64 / #121: corrección web de faltantes en PDF, con 505 pruebas PHP correctas. Continúa #108; falta revisión e integración de su cadena. [Corrección](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/121).
- #39 / #88: siguen pendientes tres casos de interfaz Flutter y la revisión; Jaziel mantiene la implementación móvil. #40 y #41 siguen en M3. El inventario enlazado conserva también los otros pendientes de recuperación.
- #109: confirmar horas disponibles, estimar juntos y recalcular ProjectLibre. La meta 05/11 no se presenta como fecha de evaluación confirmada.

Para continuar #111 hacen falta #39 y el método revisado de #110; después siguen #112 y #113. El piloto forestal #117 espera #115 y #116. Las revisiones y acuerdos pendientes no se sustituyen por crear este índice. Las advertencias de aceptación académica quedan separadas de los bloqueos técnicos: el ingeniero aprueba el corte hacia main.

Las verificaciones CI de la captura identifican el SHA observado desde el navegador. `unknown` se conserva donde no se comprobó; generar este documento no consulta CI ni acredita entrega. La captura es un corte histórico: si cambian los PR o las tareas, se toma otra y se genera otro directorio.
