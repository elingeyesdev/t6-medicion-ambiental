# Agua y cambio observado — semántica para revisión de ANA-01

Propuesta de Josué, 25/10/2026, pendiente de Jaziel. No es un contrato operativo ni habilita `/api/v1/inundaciones`; esa ruta conserva 503. [Método, fuentes y pendientes](../../docs/ANA01_INUNDACIONES.md).

Los consumidores futuros de Laravel/Blade/Flutter deben distinguir tres conceptos:

- **Presencia de agua:** clasificación de una fecha, limitada a píxeles observables y a la incertidumbre del método.
- **Cambio de agua observado:** diferencia entre fechas aprobadas, sobre su área común comparable. No equivale a superficie inundada.
- **Inundación confirmada:** exige contexto y evidencia independiente del evento. Ausencia de confirmación se expresa como desconocida, no como falsa.

Campos a acordar: versión/método, territorio y área efectiva, IDs de las dos escenas y fechas UTC, referencia y razón de selección, CRS/malla/tamaño de píxel, hashes y fuente/atribución, cobertura comparable y píxeles excluidos por motivo, capa de agua por fecha, capa de cambio y estadísticas sobre el área comparable. Los originales y recortes quedan fuera de Git; los clientes no recibirán credenciales ni calcularán umbrales de agua.

Semántica propuesta para estados: `sin_analisis`, `insumos_no_disponibles`, `cobertura_no_comparable`, `agua_observada`, `cambio_agua_observado`. No hay un estado de inundación confirmado por la clasificación SCL sola. Un análisis no ejecutado conserva métricas null, no ceros. Las capas deben diferenciar zona sin datos de zona evaluada sin agua; nunca rellenar todo el municipio a partir de un recorte parcial.

`exploracion.json` solo configura búsqueda de metadatos. `eventoConfirmado=false` significa que este ensayo no tiene un evento confirmado seleccionado; no afirma ausencia de inundaciones reales. `escenasAprobadas=[]` conserva pendiente la elección de una pareja. Ninguna ficha STAC por sí sola autoriza publicar un resultado ambiental.
