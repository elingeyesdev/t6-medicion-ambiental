# Decisión propuesta para el piloto de agua

Preparada el 03/11/2026 para revisar en #110 y PR #120. Josué prepara los datos. Jaziel revisa el caso y el contrato de consumo.

## Recomendación

Mantener como episodio candidato las crecidas del río Grande de marzo de 2025. Antes de fijar el recorte definitivo, contrastarlo con una zona afectada documentada de Pailón y con una referencia fechada. El rectángulo técnico ya ensayado cubre parcialmente Santa Cruz de la Sierra y Cotoca. La noticia regional respalda el episodio, pero falta localizar una afectación dentro de ese rectángulo. [Contexto y geometría](CONTEXTO.md).

Priorizar otra comparación óptica dentro del episodio y un recorte geográficamente justificado. Dejar radar como segunda opción, condicionada a resolver acceso y procesamiento. Esta prioridad aprovecha el lector y el acceso público de Sentinel-2 que ya comprobamos.

## Qué resolvió la búsqueda adicional

Amplié solo la referencia a **01/02–03/03/2025**, conservando la comparación **04/03–24/03/2025** y la caja ya ensayada. Una consulta pública devolvió 11 fichas y ocupó 169.156 bytes. Reutilicé las 10 fichas de comparación conservadas el 31/10. El lector encontró diez parejas compatibles, dos con una nueva referencia del 05/02. [Datos, fechas de consulta y reproducción](evidencias/exploracion-20261103/resultado.json).

La comparación del 07/03 tiene solo **16,86 %** de celdas válidas en la ventana conocida. Por tanto, cualquier intersección con otra referencia tiene como máximo ese porcentaje. La del 17/03 tiene cero celdas válidas bajo la misma máscara. Ampliar únicamente la referencia no puede recuperar la mayor parte de esa ventana. Las ocho parejas ya comprobadas mantienen su mejor intersección real de **9,49 %**. La cota de 16,86 % es un límite matemático, no una cobertura nueva medida de la pareja. [Calidad anterior](evidencias/revision-completa.json).

Propongo conservar estas parejas como casos de baja calidad y buscar otra fecha de comparación o delimitar un piloto distinto con respaldo geográfico. Para repetir la exploración sin consultar proveedores:

```powershell
python -X utf8 -B docs/inundaciones110/evidencias/exploracion-20261103/reproducir.py
```

El resultado se contrasta con el JSON guardado. La exploración comprueba huellas, fechas, mallas y la cota de intersección. No descargó bandas nuevas.

## Producto y criterio que propongo acordar

- **Objetivo inicial.** Medir cambios de agua visible entre dos fechas en las celdas comparables del piloto. Usar la categoría inundación confirmada cuando exista un contraste independiente, espacial y temporalmente adecuado.
- **Referencia.** Buscar una escena anterior al 04/03, lo más próxima posible y con antecedentes de agua revisados. El 05/02 queda como candidato de catálogo para una exploración más amplia. Su mayor separación temporal exige revisar cambios estacionales.
- **Producto.** Sentinel-2 L2A, SCL de 20 m, mismo tile, plataforma, órbita, baseline y malla conforme al comparador existente. La política candidata compara clases 4, 5 y 6 en ambas fechas. La clase 6 representa agua en esta clasificación de apoyo. [Documentación de procesamiento](https://sentiwiki.copernicus.eu/web/s2-processing).
- **Cobertura.** Propongo revisar un mínimo operativo del **80 % de la ventana común del piloto**, dejando como máximo 20 % sin comparar para publicar su resumen general. Es una propuesta de alcance del equipo, pendiente de acuerdo, no una garantía de exactitud de SCL. Se deben revisar además la localización de los huecos y la calidad del área de interés. Las ocho parejas actuales y las dos nuevas candidatas quedan por debajo de ese criterio.
- **Resultados.** Área comparable y sin evaluar, agua persistente, agua nueva visible y disminución, en hectáreas sobre una malla proyectada identificada. La cobertura del municipio se informa por separado de la ventana técnica. Los píxeles excluidos conservan su ausencia de dato.
- **Interfaz y API.** Mantener los estados y la trazabilidad del [contrato propuesto](../../compartido/inundaciones/CONTRATO_M4_PROPUESTO.md). Ante una pareja que incumpla el criterio acordado, presentar calidad insuficiente y áreas de cambio nulas. Un producto aceptado con cambio cero debe conservar su cobertura y sus fechas.

## Decisiones para dejar registradas con Jaziel

- [ ] Confirmar el episodio y la referencia independiente que se utilizará para delimitar el piloto, empezando por una zona reportada de Pailón.
- [ ] Revisar el mínimo propuesto de 80 % y el significado de las categorías con las que se mostrará el resultado.
- [ ] Elegir entre ampliar la comparación óptica en el episodio o preparar radar con acceso autorizado. Si cambia el área o la ventana, repetir catálogo y calidad antes de elegir escenas.
- [ ] Acordar el contrato con web y Flutter y registrar quién revisó la selección final. Después continúa #111.

## Fuentes revisadas

SEARPI informó crecidas entre el 04/03 y el 19/03 y afectaciones en Pailón y otros municipios. Esa referencia orienta el episodio candidato. [ABI, 19/03/2025](https://historico.abi.bo/index.php/noticias/gobierno/37-notas/noticias/sociedad/61811-rio-grande-continua-con-crecidas-en-diferentes-estaciones).

La colección RTC mantiene el requisito de cuenta para obtener el acceso a sus bandas. La opción radar requiere resolver ese acceso y verificar sus georreferencias y unidades antes de comparar. [Colección oficial de Microsoft](https://planetarycomputer.microsoft.com/api/stac/v1/collections/sentinel-1-rtc).

## Recorte adicional en Puente Pailas · 05/11/2026

La [comprobación de Puente Pailas](evidencias/pailas-20261105/README.md) midió seis parejas. La mejor permite comparar 10,56 % del recorte. Se conserva la evidencia para descartar esta selección bajo el criterio candidato. Para avanzar a #111 hace falta otra pareja con cobertura suficiente y una referencia independiente para el área seleccionada.
