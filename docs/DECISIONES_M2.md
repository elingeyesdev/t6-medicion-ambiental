# Seguimiento de decisiones de M2

## Propuesta territorial GEO-01 / #16

Preparación: Josué Padilla. Revisión y acuerdo de adopción: **pendientes de Jaziel**. Antecedente: DOC-14 / #14, cerrado documentalmente sin resolver automáticamente la decisión de catálogo y límites.

Se propone OCHA/HDX Bolivia COD-AB v02 para las geometrías y PCODE, con contraste nominal de las 56 unidades de Santa Cruz en el portal INE Censo 2024. La correspondencia completa y las 72 geometrías se preparan en `compartido/territorios/`. Se conservaron las coordenadas, las fechas reales declaradas y las denominaciones de autonomía de Charagua/Gutiérrez. La licencia de geometrías es CC BY 3.0 IGO con atribución.

El INE del mapa público aporta nombres para el contraste. Sus números ordinales no se convierten en códigos oficiales. Los PCODE tampoco se convierten a IDs de GADM; la muestra GFW `BOL/8` y el polígono de FIRMS de DOC-13 tienen una procedencia distinta.

Se propone conservar tres niveles de filtro y resolver padre-hijo por los campos publicados. Un municipio incompatible con la provincia se rechaza, y uno sin provincia explícita la obtiene del catálogo. Falta acordar con Jaziel la adopción de los PCODE, el significado de nivel municipal con autonomías y el tratamiento final de puntos exactamente en fronteras en #18.

**Efecto:** #18 no debe declarar adoptado este catálogo ni sus reglas antes de la revisión del equipo. No se reabre el entregable documental #14 ni se cambia el alcance o cronograma. La validación técnica completa del recurso no equivale a aprobación del compañero ni a certificación administrativa/legal de los límites.

**Cómo registrar el acuerdo:** comentario de revisión de Jaziel en el PR/#16 y actualización explícita de la versión/estado del catálogo. La falta de respuesta no cuenta como aprobación. Las demás decisiones de DOC-14 permanecen en sus respectivos seguimientos.
