# Contrato de agua/inundaciones — propuesta MET-02

Estado: pendiente de revisión, #110. No implementa la consulta #112 ni cambia las rutas actuales. [Preparación, fuentes y comprobaciones](../../docs/inundaciones110/README.md).

## Consulta futura

Territorio del catálogo, fecha de referencia, fecha de comparación e identificador/versionado de un producto procesado y revisado. Las fechas deben corresponder a escenas reales admitidas; no se reinterpreta el pronóstico meteorológico como inundación. La consulta no debería descargar escenas al abrir una pantalla.

Las geometrías de la cobertura efectiva y del territorio pedido deben distinguirse. Un recorte técnico que cruza dos municipios no produce automáticamente resultados para ambos municipios completos. Web y Flutter consumen el mismo producto y sus reglas; no recalculan agua por su cuenta.

## Ejemplo de estado actual

`ejemplo-met02.json` es una propuesta del contrato basada en las dos comprobaciones reales de acceso/calidad. No es una respuesta emitida por la API. Declara `implementado: false`, `metodo_aprobado: false`, `estado: sin_producto_validado`, con capa y áreas nulas. Describe la fracción comparable de la ventana exterior de la malla, sin atribuirla a todo un municipio ni a observación validada de terreno.

## Producto que se deberá acordar

Conservar producto y versión del método, identificadores de escenas, fechas UTC y presentación local, plataforma, resolución, CRS, malla, fuente, licencia y atribución. Las máscaras de ambas fechas forman parte de la identidad del resultado. El área comparable es la intersección de las celdas válidas, no su unión.

Si se adopta la política propuesta de SCL, solo las clases 4/5/6 en ambas fechas se comparan. Dentro de esa zona: clase 6 en ambas es agua persistente; clase distinta de 6 en A y 6 en B es agua nueva observada; clase 6 en A y distinta de 6 en B es disminución observada. Fuera de ella el resultado es no comparable. Es una política candidata, no un requisito impuesto por ESA ni prueba de exactitud de las clases.

El resumen deberá dar cobertura evaluada, no comparable y criterio de calidad; áreas de agua persistente y cambio; unidad y método de área; advertencias útiles. No extrapolar al faltante, calcular áreas en grados, sumar categorías solapadas ni dar profundidad/daños sin evidencia. Para llamar inundación al cambio de agua, conservar el contraste independiente y su ámbito.

## Estados que ambos clientes deben distinguir

- `dato`: producto adoptado y revisado, dentro de su cobertura, con límites explícitos. Un cero válido de cambio sigue teniendo su cobertura y sus dos fechas.
- `sin_cobertura`: el área pedida queda fuera de lo evaluado o no hay celdas comparables; áreas nulas, no cero.
- `calidad_insuficiente`: no satisface el criterio mínimo previamente acordado; no anunciar cero cambio ni mostrar capas como completas. Ese umbral todavía no está aprobado.
- `sin_producto_validado`: hay preparación o pruebas de acceso, pero no un producto analítico adoptado. Es el estado actual de estas muestras.
- `consulta_invalida` y `error_proveedor`: errores distinguibles y recuperables. No sustituirlos por una simulación o una consulta anterior sin aviso.

Los controles de selección deben seguir disponibles tras un error. En ambos clientes se podrá cerrar el detalle y volver al mapa sin perder los filtros; los controles no deben taparse entre sí. La interfaz usará textos breves y conservará fuente/cobertura relevantes en el detalle. La API mantiene la información de trazabilidad completa y los permisos existentes.

La elección del caso, referencia, criterio y contrato necesita a Jaziel; la implementación sigue en #111–#115 después de las dependencias de M3. No se acredita Flutter por preparar estos archivos.
