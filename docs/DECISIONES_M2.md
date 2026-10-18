# Seguimiento de decisiones de M2

## Propuesta territorial GEO-01 / #16

Preparación: Josué Padilla. Revisión y acuerdo de adopción: **pendientes de Jaziel**. Antecedente: DOC-14 / #14, cerrado documentalmente sin resolver automáticamente la decisión de catálogo y límites.

Se propone OCHA/HDX Bolivia COD-AB v02 para las geometrías y PCODE, con contraste nominal de las 56 unidades de Santa Cruz en el portal INE Censo 2024. La correspondencia completa y las 72 geometrías se preparan en `compartido/territorios/`. Se conservaron las coordenadas, las fechas reales declaradas y las denominaciones de autonomía de Charagua/Gutiérrez. La licencia de geometrías es CC BY 3.0 IGO con atribución.

El INE del mapa público aporta nombres para el contraste. Sus números ordinales no se convierten en códigos oficiales. Los PCODE tampoco se convierten a IDs de GADM; la muestra GFW `BOL/8` y el polígono de FIRMS de DOC-13 tienen una procedencia distinta.

Se propone conservar tres niveles de filtro y resolver padre-hijo por los campos publicados. Un municipio incompatible con la provincia se rechaza, y uno sin provincia explícita la obtiene del catálogo. Falta acordar con Jaziel la adopción de los PCODE, el significado de nivel municipal con autonomías y el tratamiento final de puntos exactamente en fronteras en #18.

**Efecto:** #18 no debe declarar adoptado este catálogo ni sus reglas antes de la revisión del equipo. No se reabre el entregable documental #14 ni se cambia el alcance o cronograma. La validación técnica completa del recurso no equivale a aprobación del compañero ni a certificación administrativa/legal de los límites.

**Cómo registrar el acuerdo:** comentario de revisión de Jaziel en el PR/#16 y actualización explícita de la versión/estado del catálogo. La falta de respuesta no cuenta como aprobación. Las demás decisiones de DOC-14 permanecen en sus respectivos seguimientos.

## Implementación candidata API-01 / #18

Contrato `1.0-m2-candidata` basado en DOC-12. Se concreta la retención pendiente con instantáneas de 15 minutos y parámetro `versionConjunto`, reutilización de 5 minutos y error 409 al expirar/cambiar filtros. Período máximo 168 horas, consultas NASA por bloques de hasta cinco días. Se propone incluir bordes en cada selección, sin asignación municipal arbitraria cuando coinciden varias geometrías; consultas de territorios vecinos pueden compartir puntos de borde. Estas reglas y la adopción del catálogo siguen pendientes de revisión de Jaziel. Implementación y prueba real no equivalen a acuerdo ni aprobación del equipo.

El producto operativo candidato es VIIRS_SNPP_NRT y solo `modo=externo` tiene integración; demostración y otros módulos no tienen datos operativos en #18. No se adopta una regla de incendios confirmados, área quemada o gravedad. Los detalles se registran en `compartido/api/README.md` y `docs/API01_FOCOS_CALOR.md`.

## Seguimiento de entrega / #22 · corte 16/10/2026

La API y el visor web cuentan con evidencia externa y pruebas, en PR #25/#26 abiertos. Base #15 y catálogo #16 también siguen pendientes de revisión en #23/#24. No hay aprobación registrada del compañero al corte. La página web de pantalla pequeña no acredita implementación Flutter.

Pendientes que condicionan el cierre de M2: base/consulta móvil #17/#20 (Jaziel), equivalencia web/móvil #21 (Jaziel con participación de Josué), adopción del catálogo/contrato y revisión cruzada, y consolidación final #22 (Josué, revisa Jaziel). Se prepara el índice de evidencia y demostración en [entrega-m2/README.md](entrega-m2/README.md); su estado es parcial.

Seguimiento C/D/E: C preparado para revisión; D pendiente de evidencia; E con API/web preparadas y móvil/integración pendientes. No se altera Planner ni la línea base de PERT/CPM. Entrega prevista 22/10/2026; si persiste un bloqueo, registrar fecha, responsable, impacto y acuerdo antes de darla por aceptada. El cumplimiento técnico observado no implica revisión del equipo ni aprobación académica.

## Observaciones recibidas · 18/10/2026

Se comprobaron comentarios de Jaziel del 17/10: en #15 verificó la carga de la base; en #16 aprobó el catálogo mostrado de municipios/provincias; en #18 verificó alcance y pruebas y pidió una demostración real sencilla antes de aprobar. No constan revisiones formales de los PR #23–#26 al consultar este seguimiento. La aprobación del listado en #16 no se amplía por inferencia a las reglas de fronteras o retención del contrato.

Respuesta preparada a #18: [guía y comando reproducible](API01_DEMOSTRACION_NASA.md), con consulta externa nueva y resultados explicados; API/contrato sin cambios. Mantener pendiente la demostración conjunta y la aprobación final de Jaziel. Las evidencias del corte anterior conservan sus versiones históricas.
