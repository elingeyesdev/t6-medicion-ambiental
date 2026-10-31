# PREPARACIÓN #118 — índice de evidencias M4

Observaciones declaradas en un snapshot; no verificadas contra GitHub. No certifica M4, CI, aprobación ni entrega.

- Fuente: `provided_snapshot`.
- Captura UTC: 2026-10-31T06:27:50Z; evaluación UTC: 2026-10-31T06:28:38Z.
- Vigencia: `within_limit` (máximo 24 horas).
- SHA-256 del snapshot recibido: `3c60b91bd65cd6770304e528946be85948f7b906e6fcfad73ffc487217d487c5`.
- Resumen legible por herramientas: [resumen.json](resumen.json).

- Este informe no certifica producto de agua ni entrega final.
- M3 no se incorpora como trabajo terminado de M4.
- No se infiere cronograma ni capacidad.
- Sin PDF ni paquete final.

Contexto comunicado al 31/10/2026: M2 según el contexto comunicado; no determina el estado de main en capturas posteriores.
Las notas fechadas de tareas son antecedentes, no estados actuales de una captura posterior.

## Metas de milestones declaradas

- M3, milestone #3: meta `2026-10-29`; fecha académica desconocida. No se calcula cronograma.
- M4, milestone #4: meta `2026-11-05`; fecha académica no confirmada. No se calcula cronograma.

## M3 — antecedentes y dependencias, fuera de la entrega M4

### [#34](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/34) — MOV-03 - Integrar y verificar Flutter con el backend Laravel actual

- Estado declarado: `closed`; implementación: `unknown`.
- Aceptación del ingeniero declarada: `unknown`; no verificada aquí.
- Milestone declarado: 3; fase declarada: M3.
- Dependencias consideradas: ninguna declarada.
- Evaluación de dependencias: `no_local_gaps_detected`.
- Dependencias pendientes: sin brechas locales detectadas; no acredita entrega.
- PR declarados: sin evidencia.
- Pendientes técnicos: `implementation_not_confirmed`, `pull_request_evidence_missing`.
- Advertencias independientes de entrega: `engineer_acceptance_not_confirmed`, `closed_issue_is_not_delivery`.

### [#35](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/35) — MET-01 - Acordar fuente método y contrato del módulo de sequías

- Estado declarado: `closed`; implementación: `unknown`.
- Aceptación del ingeniero declarada: `unknown`; no verificada aquí.
- Milestone declarado: 3; fase declarada: M3.
- Dependencias consideradas: ninguna declarada.
- Evaluación de dependencias: `no_local_gaps_detected`.
- Dependencias pendientes: sin brechas locales detectadas; no acredita entrega.
- PR declarados: sin evidencia.
- Pendientes técnicos: `implementation_not_confirmed`, `pull_request_evidence_missing`.
- Advertencias independientes de entrega: `engineer_acceptance_not_confirmed`, `closed_issue_is_not_delivery`.

### [#36](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/36) — API-02 - Implementar consulta y cálculo de sequías en Laravel

- Estado declarado: `closed`; implementación: `unknown`.
- Aceptación del ingeniero declarada: `unknown`; no verificada aquí.
- Milestone declarado: 3; fase declarada: M3.
- Dependencias consideradas: [#35](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/35).
- Evaluación de dependencias: `pending`.
- Dependencias pendientes: #35.
- PR declarados: sin evidencia.
- Pendientes técnicos: `implementation_not_confirmed`, `pull_request_evidence_missing`, `dependency_unresolved`.
- Advertencias independientes de entrega: `engineer_acceptance_not_confirmed`, `closed_issue_is_not_delivery`.

### [#37](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/37) — WEB-02 - Implementar consulta mapa detalle y resumen de sequías

- Estado declarado: `closed`; implementación: `unknown`.
- Aceptación del ingeniero declarada: `unknown`; no verificada aquí.
- Milestone declarado: 3; fase declarada: M3.
- Dependencias consideradas: [#36](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/36).
- Evaluación de dependencias: `pending`.
- Dependencias pendientes: #36.
- PR declarados: sin evidencia.
- Pendientes técnicos: `implementation_not_confirmed`, `pull_request_evidence_missing`, `dependency_unresolved`.
- Advertencias independientes de entrega: `engineer_acceptance_not_confirmed`, `closed_issue_is_not_delivery`.

### [#38](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/38) — MOV-04 - Consultar sequías en Flutter mediante la API común

- Estado declarado: `closed`; implementación: `unknown`.
- Aceptación del ingeniero declarada: `unknown`; no verificada aquí.
- Milestone declarado: 3; fase declarada: M3.
- Dependencias consideradas: [#34](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/34), [#36](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/36).
- Evaluación de dependencias: `pending`.
- Dependencias pendientes: #34, #36.
- PR declarados: sin evidencia.
- Pendientes técnicos: `implementation_not_confirmed`, `pull_request_evidence_missing`, `dependency_unresolved`.
- Advertencias independientes de entrega: `engineer_acceptance_not_confirmed`, `closed_issue_is_not_delivery`.

### [#39](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/39) — QA-02 - Verificar cálculo e integración de sequías en web y móvil

- Estado declarado: `open`; implementación: `in_progress`.
- Aceptación del ingeniero declarada: `unknown`; no verificada aquí.
- Milestone declarado: 3; fase declarada: M3.
- Contexto comunicado al 31/10/2026: Se comunicaron tres fallos Flutter y cambios solicitados en b176111; confirmar SHA completo y vigencia.
- Dependencias consideradas: [#37](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/37), [#38](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/38).
- Evaluación de dependencias: `pending`.
- Dependencias pendientes: #37, #38.
- PR declarados: [#88](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/88).
- Pendientes técnicos: `issue_open`, `implementation_not_confirmed`, `pull_request_pending`, `dependency_unresolved`.
- Advertencias independientes de entrega: `engineer_acceptance_not_confirmed`.

### [#40](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/40) — ANA-01 - Preparar método y avance de análisis de inundaciones

- Estado declarado: `open`; implementación: `proposal`.
- Aceptación del ingeniero declarada: `unknown`; no verificada aquí.
- Milestone declarado: 3; fase declarada: M3.
- Dependencias consideradas: [#39](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/39).
- Evaluación de dependencias: `pending`.
- Dependencias pendientes: #39.
- PR declarados: [#46](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/46).
- Pendientes técnicos: `issue_open`, `implementation_not_confirmed`, `pull_request_pending`, `dependency_unresolved`.
- Advertencias independientes de entrega: `engineer_acceptance_not_confirmed`.

### [#41](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/41) — ENT-03 - Consolidar evidencias y revisión de la tercera entrega

- Estado declarado: `open`; implementación: `in_progress`.
- Aceptación del ingeniero declarada: `unknown`; no verificada aquí.
- Milestone declarado: 3; fase declarada: M3.
- Dependencias consideradas: [#34](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/34), [#39](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/39), [#40](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/40).
- Evaluación de dependencias: `pending`.
- Dependencias pendientes: #34, #39, #40.
- PR declarados: [#96](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/96).
- Pendientes técnicos: `issue_open`, `implementation_not_confirmed`, `pull_request_pending`, `dependency_unresolved`.
- Advertencias independientes de entrega: `engineer_acceptance_not_confirmed`.

### [#47](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/47) — REF-WEB - Mantener EnviroSense como base web de GeoSur

- Estado declarado: `open`; implementación: `in_progress`.
- Aceptación del ingeniero declarada: `unknown`; no verificada aquí.
- Milestone declarado: 3; fase declarada: M3.
- Dependencias consideradas: ninguna declarada.
- Evaluación de dependencias: `no_local_gaps_detected`.
- Dependencias pendientes: sin brechas locales detectadas; no acredita entrega.
- PR declarados: [#98](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/98).
- Pendientes técnicos: `issue_open`, `implementation_not_confirmed`, `pull_request_pending`.
- Advertencias independientes de entrega: `engineer_acceptance_not_confirmed`.

### [#64](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/64) — REP-01 - Recuperar reportes plantillas y gráficos de EnviroSense

- Estado declarado: `open`; implementación: `in_progress`.
- Aceptación del ingeniero declarada: `unknown`; no verificada aquí.
- Milestone declarado: 3; fase declarada: M3.
- Dependencias consideradas: ninguna declarada.
- Evaluación de dependencias: `no_local_gaps_detected`.
- Dependencias pendientes: sin brechas locales detectadas; no acredita entrega.
- PR declarados: [#121](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/121).
- Pendientes técnicos: `issue_open`, `implementation_not_confirmed`, `pull_request_pending`.
- Advertencias independientes de entrega: `engineer_acceptance_not_confirmed`.


## M4 — preparación y pendientes

### [#109](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/109) — PLAN-02 - Ajustar el cronograma al alcance actual de GeoSur

- Estado declarado: `open`; implementación: `unknown`.
- Aceptación del ingeniero declarada: `unknown`; no verificada aquí.
- Milestone declarado: 4; fase declarada: M4.
- Contexto comunicado al 31/10/2026: Planificación pendiente de Jaziel y capacidad; sin cronograma confirmado.
- Dependencias consideradas: ninguna declarada.
- Evaluación de dependencias: `no_local_gaps_detected`.
- Dependencias pendientes: sin brechas locales detectadas; no acredita entrega.
- PR declarados: sin evidencia.
- Pendientes técnicos: `issue_open`, `implementation_not_confirmed`, `pull_request_evidence_missing`.
- Advertencias independientes de entrega: `engineer_acceptance_not_confirmed`.

### [#110](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/110) — MET-02 - Elegir el caso y cerrar el método de agua e inundaciones

- Estado declarado: `open`; implementación: `proposal`.
- Aceptación del ingeniero declarada: `unknown`; no verificada aquí.
- Milestone declarado: 4; fase declarada: M4.
- Contexto comunicado al 31/10/2026: Propuesta de inundaciones / PR #120; recapturar HEAD tras ampliar pruebas SCL y metadata radar. No acredita producto de agua ni CI actual.
- Dependencias consideradas: [#40](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/40).
- Evaluación de dependencias: `pending`.
- Dependencias pendientes: #40.
- PR declarados: [#120](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/120).
- Pendientes técnicos: `issue_open`, `implementation_not_confirmed`, `pull_request_pending`, `dependency_unresolved`.
- Advertencias independientes de entrega: `engineer_acceptance_not_confirmed`.

### [#111](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/111) — DAT-05 - Procesar y comprobar los cambios de agua del caso elegido

- Estado declarado: `open`; implementación: `unknown`.
- Aceptación del ingeniero declarada: `unknown`; no verificada aquí.
- Milestone declarado: 4; fase declarada: M4.
- Dependencias consideradas: [#39](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/39), [#110](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/110).
- Evaluación de dependencias: `pending`.
- Dependencias pendientes: #39, #110.
- PR declarados: sin evidencia.
- Pendientes técnicos: `issue_open`, `implementation_not_confirmed`, `pull_request_evidence_missing`, `dependency_unresolved`.
- Advertencias independientes de entrega: `engineer_acceptance_not_confirmed`.

### [#112](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/112) — API-03 - Compartir la consulta de agua e inundaciones con web y móvil

- Estado declarado: `open`; implementación: `unknown`.
- Aceptación del ingeniero declarada: `unknown`; no verificada aquí.
- Milestone declarado: 4; fase declarada: M4.
- Dependencias consideradas: [#111](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/111).
- Evaluación de dependencias: `pending`.
- Dependencias pendientes: #111.
- PR declarados: sin evidencia.
- Pendientes técnicos: `issue_open`, `implementation_not_confirmed`, `pull_request_evidence_missing`, `dependency_unresolved`.
- Advertencias independientes de entrega: `engineer_acceptance_not_confirmed`.

### [#113](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/113) — WEB-04 - Incorporar agua e inundaciones al mapa de GeoSur

- Estado declarado: `open`; implementación: `unknown`.
- Aceptación del ingeniero declarada: `unknown`; no verificada aquí.
- Milestone declarado: 4; fase declarada: M4.
- Dependencias consideradas: [#47](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/47), [#112](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/112).
- Evaluación de dependencias: `pending`.
- Dependencias pendientes: #47, #112.
- PR declarados: sin evidencia.
- Pendientes técnicos: `issue_open`, `implementation_not_confirmed`, `pull_request_evidence_missing`, `dependency_unresolved`.
- Advertencias independientes de entrega: `engineer_acceptance_not_confirmed`.

### [#114](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/114) — MOV-05 - Consultar agua e inundaciones desde Flutter

- Estado declarado: `open`; implementación: `unknown`.
- Aceptación del ingeniero declarada: `unknown`; no verificada aquí.
- Milestone declarado: 4; fase declarada: M4.
- Contexto comunicado al 31/10/2026: Flutter a cargo de Jaziel; requiere su implementación y evidencia propia.
- Dependencias consideradas: [#112](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/112).
- Evaluación de dependencias: `pending`.
- Dependencias pendientes: #112.
- PR declarados: sin evidencia.
- Pendientes técnicos: `issue_open`, `implementation_not_confirmed`, `pull_request_evidence_missing`, `dependency_unresolved`.
- Advertencias independientes de entrega: `engineer_acceptance_not_confirmed`.

### [#115](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/115) — QA-03 - Contrastar el análisis de agua y su consulta en ambos clientes

- Estado declarado: `open`; implementación: `unknown`.
- Aceptación del ingeniero declarada: `unknown`; no verificada aquí.
- Milestone declarado: 4; fase declarada: M4.
- Dependencias consideradas: [#111](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/111), [#113](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/113), [#114](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/114).
- Evaluación de dependencias: `pending`.
- Dependencias pendientes: #111, #113, #114.
- PR declarados: sin evidencia.
- Pendientes técnicos: `issue_open`, `implementation_not_confirmed`, `pull_request_evidence_missing`, `dependency_unresolved`.
- Advertencias independientes de entrega: `engineer_acceptance_not_confirmed`.

### [#116](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/116) — MET-03 - Acordar la fuente y el método de pérdida de cobertura forestal

- Estado declarado: `open`; implementación: `proposal`.
- Aceptación del ingeniero declarada: `unknown`; no verificada aquí.
- Milestone declarado: 4; fase declarada: M4.
- Contexto comunicado al 31/10/2026: Forestal / PR #119 en revisión; no acredita aprobación ni integración.
- Dependencias consideradas: ninguna declarada.
- Evaluación de dependencias: `no_local_gaps_detected`.
- Dependencias pendientes: sin brechas locales detectadas; no acredita entrega.
- PR declarados: [#119](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/119).
- Pendientes técnicos: `issue_open`, `implementation_not_confirmed`, `pull_request_pending`.
- Advertencias independientes de entrega: `engineer_acceptance_not_confirmed`.

### [#117](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/117) — ANA-02 - Preparar el primer resultado verificable de cobertura forestal

- Estado declarado: `open`; implementación: `unknown`.
- Aceptación del ingeniero declarada: `unknown`; no verificada aquí.
- Milestone declarado: 4; fase declarada: M4.
- Dependencias consideradas: [#115](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/115), [#116](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/116).
- Evaluación de dependencias: `pending`.
- Dependencias pendientes: #115, #116.
- PR declarados: sin evidencia.
- Pendientes técnicos: `issue_open`, `implementation_not_confirmed`, `pull_request_evidence_missing`, `dependency_unresolved`.
- Advertencias independientes de entrega: `engineer_acceptance_not_confirmed`.

### [#118](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/118) — ENT-04 - Reunir evidencias y preparar la revisión de M4

- Estado declarado: `open`; implementación: `proposal`.
- Aceptación del ingeniero declarada: `unknown`; no verificada aquí.
- Milestone declarado: 4; fase declarada: M4.
- Contexto comunicado al 31/10/2026: Preparar documentos no completa M4 ni la entrega al ingeniero.
- Dependencias consideradas: [#41](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/41), [#109](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/109), [#115](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/115), [#117](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/117).
- Evaluación de dependencias: `pending`.
- Dependencias pendientes: #41, #109, #115, #117.
- PR declarados: sin evidencia.
- Pendientes técnicos: `issue_open`, `implementation_not_confirmed`, `pull_request_evidence_missing`, `dependency_unresolved`.
- Advertencias independientes de entrega: `engineer_acceptance_not_confirmed`.


## Sin milestone confirmado

Sin registros capturados; no implica ausencia de pendientes.
## PR, revisión y checks declarados

### [PR #46](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/46)

- HEAD: `fba0319696bd5fe707bf71b68315c23ffecfb191`; fusión: `open`.
- Revisión: `no_current_approval`; checks: `unknown`.
- Aprobar otro SHA no aprueba HEAD. Los checks proceden solo del snapshot; no se consultaron por CLI.

### [PR #88](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/88)

- HEAD: `b1761113b161bd5d8d9634cd2396a4120fa2a12d`; fusión: `open`.
- Revisión: `changes_requested`; checks: `success`.
- Aprobar otro SHA no aprueba HEAD. Los checks proceden solo del snapshot; no se consultaron por CLI.
- Review 5703829084, JosuePadillaUnivalle: `COMMENTED` sobre `1604a1aa685018c950a99de6e547808445b59236`; 2026-10-29T19:33:10Z.
- Review 5721392681, JosuePadillaUnivalle: `CHANGES_REQUESTED` sobre `b1761113b161bd5d8d9634cd2396a4120fa2a12d`; 2026-10-31T05:05:19Z.

### [PR #96](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/96)

- HEAD: `f74bb7d0decb15644e1666b90fc6910b50b22995`; fusión: `open`.
- Revisión: `no_current_approval`; checks: `unknown`.
- Aprobar otro SHA no aprueba HEAD. Los checks proceden solo del snapshot; no se consultaron por CLI.

### [PR #98](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/98)

- HEAD: `d8b89140fd3bf0e84314211a27a952db448b7d74`; fusión: `open`.
- Revisión: `no_current_approval`; checks: `unknown`.
- Aprobar otro SHA no aprueba HEAD. Los checks proceden solo del snapshot; no se consultaron por CLI.

### [PR #119](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/119)

- HEAD: `09cffb9c72154a42b624c44238dbde1f6db632d9`; fusión: `open`.
- Revisión: `no_current_approval`; checks: `success`.
- Aprobar otro SHA no aprueba HEAD. Los checks proceden solo del snapshot; no se consultaron por CLI.

### [PR #120](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/120)

- HEAD: `c2f6ed6d0e468d039c3d91db16c725356cc37917`; fusión: `open`.
- Revisión: `no_current_approval`; checks: `success`.
- Aprobar otro SHA no aprueba HEAD. Los checks proceden solo del snapshot; no se consultaron por CLI.

### [PR #121](https://github.com/elingeyesdev/t6-medicion-ambiental/pull/121)

- HEAD: `fb3a42b988cf87d64ecd0e2c14d76aed8f3f4c19`; fusión: `open`.
- Revisión: `no_current_approval`; checks: `unknown`.
- Aprobar otro SHA no aprueba HEAD. Los checks proceden solo del snapshot; no se consultaron por CLI.

## Datos faltantes y observaciones pendientes

- `merge_not_confirmed` (technical) PR #46: PR con evidencia pendiente: merge\_not\_confirmed.
- `current_sha_approval_missing` (technical) PR #46: PR con evidencia pendiente: current\_sha\_approval\_missing.
- `checks_not_confirmed_success` (technical) PR #46: PR con evidencia pendiente: checks\_not\_confirmed\_success.
- `merge_not_confirmed` (technical) PR #88: PR con evidencia pendiente: merge\_not\_confirmed.
- `changes_requested` (technical) PR #88: PR con evidencia pendiente: changes\_requested.
- `merge_not_confirmed` (technical) PR #96: PR con evidencia pendiente: merge\_not\_confirmed.
- `draft_pull_request` (technical) PR #96: PR con evidencia pendiente: draft\_pull\_request.
- `current_sha_approval_missing` (technical) PR #96: PR con evidencia pendiente: current\_sha\_approval\_missing.
- `checks_not_confirmed_success` (technical) PR #96: PR con evidencia pendiente: checks\_not\_confirmed\_success.
- `merge_not_confirmed` (technical) PR #98: PR con evidencia pendiente: merge\_not\_confirmed.
- `current_sha_approval_missing` (technical) PR #98: PR con evidencia pendiente: current\_sha\_approval\_missing.
- `checks_not_confirmed_success` (technical) PR #98: PR con evidencia pendiente: checks\_not\_confirmed\_success.
- `merge_not_confirmed` (technical) PR #119: PR con evidencia pendiente: merge\_not\_confirmed.
- `current_sha_approval_missing` (technical) PR #119: PR con evidencia pendiente: current\_sha\_approval\_missing.
- `merge_not_confirmed` (technical) PR #120: PR con evidencia pendiente: merge\_not\_confirmed.
- `current_sha_approval_missing` (technical) PR #120: PR con evidencia pendiente: current\_sha\_approval\_missing.
- `merge_not_confirmed` (technical) PR #121: PR con evidencia pendiente: merge\_not\_confirmed.
- `current_sha_approval_missing` (technical) PR #121: PR con evidencia pendiente: current\_sha\_approval\_missing.
- `checks_not_confirmed_success` (technical) PR #121: PR con evidencia pendiente: checks\_not\_confirmed\_success.
- `implementation_not_confirmed` (technical) #34: No hay implementación declarada completa.
- `engineer_acceptance_not_confirmed` (delivery_warning) #34: Sin aceptación individual declarada del ingeniero; dato de entrega, no bloqueo técnico.
- `closed_issue_is_not_delivery` (delivery_warning) #34: Cerrar una issue no acredita aceptación académica del corte main.
- `pull_request_evidence_missing` (technical) #34: No hay lista de PR con evidencia suficiente; no inferir integración.
- `implementation_not_confirmed` (technical) #35: No hay implementación declarada completa.
- `engineer_acceptance_not_confirmed` (delivery_warning) #35: Sin aceptación individual declarada del ingeniero; dato de entrega, no bloqueo técnico.
- `closed_issue_is_not_delivery` (delivery_warning) #35: Cerrar una issue no acredita aceptación académica del corte main.
- `pull_request_evidence_missing` (technical) #35: No hay lista de PR con evidencia suficiente; no inferir integración.
- `implementation_not_confirmed` (technical) #36: No hay implementación declarada completa.
- `engineer_acceptance_not_confirmed` (delivery_warning) #36: Sin aceptación individual declarada del ingeniero; dato de entrega, no bloqueo técnico.
- `closed_issue_is_not_delivery` (delivery_warning) #36: Cerrar una issue no acredita aceptación académica del corte main.
- `pull_request_evidence_missing` (technical) #36: No hay lista de PR con evidencia suficiente; no inferir integración.
- `implementation_not_confirmed` (technical) #37: No hay implementación declarada completa.
- `engineer_acceptance_not_confirmed` (delivery_warning) #37: Sin aceptación individual declarada del ingeniero; dato de entrega, no bloqueo técnico.
- `closed_issue_is_not_delivery` (delivery_warning) #37: Cerrar una issue no acredita aceptación académica del corte main.
- `pull_request_evidence_missing` (technical) #37: No hay lista de PR con evidencia suficiente; no inferir integración.
- `implementation_not_confirmed` (technical) #38: No hay implementación declarada completa.
- `engineer_acceptance_not_confirmed` (delivery_warning) #38: Sin aceptación individual declarada del ingeniero; dato de entrega, no bloqueo técnico.
- `closed_issue_is_not_delivery` (delivery_warning) #38: Cerrar una issue no acredita aceptación académica del corte main.
- `pull_request_evidence_missing` (technical) #38: No hay lista de PR con evidencia suficiente; no inferir integración.
- `issue_open` (technical) #39: La tarea sigue abierta según el snapshot.
- `implementation_not_confirmed` (technical) #39: No hay implementación declarada completa.
- `engineer_acceptance_not_confirmed` (delivery_warning) #39: Sin aceptación individual declarada del ingeniero; dato de entrega, no bloqueo técnico.
- `pull_request_pending` (technical) #39: PR asociado con integración, revisión o checks pendientes.
- `issue_open` (technical) #40: La tarea sigue abierta según el snapshot.
- `implementation_not_confirmed` (technical) #40: No hay implementación declarada completa.
- `engineer_acceptance_not_confirmed` (delivery_warning) #40: Sin aceptación individual declarada del ingeniero; dato de entrega, no bloqueo técnico.
- `pull_request_pending` (technical) #40: PR asociado con integración, revisión o checks pendientes.
- `issue_open` (technical) #41: La tarea sigue abierta según el snapshot.
- `implementation_not_confirmed` (technical) #41: No hay implementación declarada completa.
- `engineer_acceptance_not_confirmed` (delivery_warning) #41: Sin aceptación individual declarada del ingeniero; dato de entrega, no bloqueo técnico.
- `pull_request_pending` (technical) #41: PR asociado con integración, revisión o checks pendientes.
- `issue_open` (technical) #47: La tarea sigue abierta según el snapshot.
- `implementation_not_confirmed` (technical) #47: No hay implementación declarada completa.
- `engineer_acceptance_not_confirmed` (delivery_warning) #47: Sin aceptación individual declarada del ingeniero; dato de entrega, no bloqueo técnico.
- `pull_request_pending` (technical) #47: PR asociado con integración, revisión o checks pendientes.
- `issue_open` (technical) #64: La tarea sigue abierta según el snapshot.
- `implementation_not_confirmed` (technical) #64: No hay implementación declarada completa.
- `engineer_acceptance_not_confirmed` (delivery_warning) #64: Sin aceptación individual declarada del ingeniero; dato de entrega, no bloqueo técnico.
- `pull_request_pending` (technical) #64: PR asociado con integración, revisión o checks pendientes.
- `issue_open` (technical) #109: La tarea sigue abierta según el snapshot.
- `implementation_not_confirmed` (technical) #109: No hay implementación declarada completa.
- `engineer_acceptance_not_confirmed` (delivery_warning) #109: Sin aceptación individual declarada del ingeniero; dato de entrega, no bloqueo técnico.
- `pull_request_evidence_missing` (technical) #109: No hay lista de PR con evidencia suficiente; no inferir integración.
- `issue_open` (technical) #110: La tarea sigue abierta según el snapshot.
- `implementation_not_confirmed` (technical) #110: No hay implementación declarada completa.
- `engineer_acceptance_not_confirmed` (delivery_warning) #110: Sin aceptación individual declarada del ingeniero; dato de entrega, no bloqueo técnico.
- `pull_request_pending` (technical) #110: PR asociado con integración, revisión o checks pendientes.
- `issue_open` (technical) #111: La tarea sigue abierta según el snapshot.
- `implementation_not_confirmed` (technical) #111: No hay implementación declarada completa.
- `engineer_acceptance_not_confirmed` (delivery_warning) #111: Sin aceptación individual declarada del ingeniero; dato de entrega, no bloqueo técnico.
- `pull_request_evidence_missing` (technical) #111: No hay lista de PR con evidencia suficiente; no inferir integración.
- `issue_open` (technical) #112: La tarea sigue abierta según el snapshot.
- `implementation_not_confirmed` (technical) #112: No hay implementación declarada completa.
- `engineer_acceptance_not_confirmed` (delivery_warning) #112: Sin aceptación individual declarada del ingeniero; dato de entrega, no bloqueo técnico.
- `pull_request_evidence_missing` (technical) #112: No hay lista de PR con evidencia suficiente; no inferir integración.
- `issue_open` (technical) #113: La tarea sigue abierta según el snapshot.
- `implementation_not_confirmed` (technical) #113: No hay implementación declarada completa.
- `engineer_acceptance_not_confirmed` (delivery_warning) #113: Sin aceptación individual declarada del ingeniero; dato de entrega, no bloqueo técnico.
- `pull_request_evidence_missing` (technical) #113: No hay lista de PR con evidencia suficiente; no inferir integración.
- `issue_open` (technical) #114: La tarea sigue abierta según el snapshot.
- `implementation_not_confirmed` (technical) #114: No hay implementación declarada completa.
- `engineer_acceptance_not_confirmed` (delivery_warning) #114: Sin aceptación individual declarada del ingeniero; dato de entrega, no bloqueo técnico.
- `pull_request_evidence_missing` (technical) #114: No hay lista de PR con evidencia suficiente; no inferir integración.
- `issue_open` (technical) #115: La tarea sigue abierta según el snapshot.
- `implementation_not_confirmed` (technical) #115: No hay implementación declarada completa.
- `engineer_acceptance_not_confirmed` (delivery_warning) #115: Sin aceptación individual declarada del ingeniero; dato de entrega, no bloqueo técnico.
- `pull_request_evidence_missing` (technical) #115: No hay lista de PR con evidencia suficiente; no inferir integración.
- `issue_open` (technical) #116: La tarea sigue abierta según el snapshot.
- `implementation_not_confirmed` (technical) #116: No hay implementación declarada completa.
- `engineer_acceptance_not_confirmed` (delivery_warning) #116: Sin aceptación individual declarada del ingeniero; dato de entrega, no bloqueo técnico.
- `pull_request_pending` (technical) #116: PR asociado con integración, revisión o checks pendientes.
- `issue_open` (technical) #117: La tarea sigue abierta según el snapshot.
- `implementation_not_confirmed` (technical) #117: No hay implementación declarada completa.
- `engineer_acceptance_not_confirmed` (delivery_warning) #117: Sin aceptación individual declarada del ingeniero; dato de entrega, no bloqueo técnico.
- `pull_request_evidence_missing` (technical) #117: No hay lista de PR con evidencia suficiente; no inferir integración.
- `issue_open` (technical) #118: La tarea sigue abierta según el snapshot.
- `implementation_not_confirmed` (technical) #118: No hay implementación declarada completa.
- `engineer_acceptance_not_confirmed` (delivery_warning) #118: Sin aceptación individual declarada del ingeniero; dato de entrega, no bloqueo técnico.
- `pull_request_evidence_missing` (technical) #118: No hay lista de PR con evidencia suficiente; no inferir integración.
- `dependency_unresolved` (technical) #36: Dependencias ausentes o con evidencia pendiente.
- `dependency_unresolved` (technical) #37: Dependencias ausentes o con evidencia pendiente.
- `dependency_unresolved` (technical) #38: Dependencias ausentes o con evidencia pendiente.
- `dependency_unresolved` (technical) #39: Dependencias ausentes o con evidencia pendiente.
- `dependency_unresolved` (technical) #40: Dependencias ausentes o con evidencia pendiente.
- `dependency_unresolved` (technical) #41: Dependencias ausentes o con evidencia pendiente.
- `dependency_unresolved` (technical) #110: Dependencias ausentes o con evidencia pendiente.
- `dependency_unresolved` (technical) #111: Dependencias ausentes o con evidencia pendiente.
- `dependency_unresolved` (technical) #112: Dependencias ausentes o con evidencia pendiente.
- `dependency_unresolved` (technical) #113: Dependencias ausentes o con evidencia pendiente.
- `dependency_unresolved` (technical) #114: Dependencias ausentes o con evidencia pendiente.
- `dependency_unresolved` (technical) #115: Dependencias ausentes o con evidencia pendiente.
- `dependency_unresolved` (technical) #117: Dependencias ausentes o con evidencia pendiente.
- `dependency_unresolved` (technical) #118: Dependencias ausentes o con evidencia pendiente.
