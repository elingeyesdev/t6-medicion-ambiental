# Segunda iteración

M2: Focos de calor en web y móvil. Entrega prevista: 22/10/2026, conforme al cronograma de la primera entrega.

## Tareas

- #15 SET-01: base Laravel/Blade y API. Josué. EDT 3.1, actividad C. Rama `feature/15-base-web-api`.
- #16 GEO-01: catálogo territorial y límites acordados. Josué y Jaziel. EDT 3.1, actividad C. Rama `feature/16-catalogo-territorial`.
- #17 MOV-01: base Flutter y navegación. Jaziel. EDT 3.2, actividad D. Rama `feature/17-base-flutter-navegacion`.
- #18 API-01: consulta de focos y contrato compartido. Josué. EDT 4.1, actividad E. Depende de #15 y #16. Rama `feature/18-api-focos-calor`.
- #19 WEB-01: mapa, filtros, detalle y resumen web. Josué. EDT 4.1, actividad E. Depende de #15 y #18. Rama `feature/19-web-focos-calor`.
- #20 MOV-02: consulta de focos en Flutter. Jaziel. EDT 4.1, actividad E. Depende de #17 y #18. Rama `feature/20-flutter-focos-calor`.
- #21 QA-01: integración y equivalencia de interfaces. Jaziel con participación de Josué. EDT 4.1, actividad E. Depende de #19 y #20. Rama `feature/21-pruebas-integracion-focos`.
- #22 ENT-02: evidencias y revisión de la entrega. Josué, revisa Jaziel. Seguimiento transversal de C/D/E. Depende de #21 para el cierre. Rama `feature/22-evidencias-entrega-m2`.

Las tareas detallan las actividades existentes del PERT; no añaden duraciones independientes ni modifican la línea base. Los Issues #15, #16 y #17 pueden prepararse en paralelo; los demás conservan sus dependencias. Crear la rama no significa iniciar o completar la implementación.

## Revisión y evidencia

Los PR se abren contra `develop` y enlazan el Issue correspondiente. Jaziel revisa cambios de Josué y Josué revisa cambios de Jaziel. Solo se mueve a In review cuando existe un resultado ejecutado con evidencia; Done requiere la revisión correspondiente.

La consulta real de NASA FIRMS se comprueba desde el backend. Las pruebas con fixtures, los prototipos y las simulaciones no sustituyen esa evidencia. Un foco térmico no confirma incendio; FRP en MW no mide superficie quemada. El catálogo, los límites y las restricciones se mantienen trazables a #14.

La aprobación del milestone M2 por el compañero y la validación académica permanecen pendientes. No se incorpora la práctica de estaciones ni los PR de demostración del repositorio de prácticas.
