# Alternativa para revisar: inundaciones de diciembre de 2025

El 05/11/2026 la [API pública de Copernicus EMS](https://rapidmapping.emergency.copernicus.eu/backend/dashboard-api/public-activations/?code=EMSR853) devolvió HTTP 200 para la [activación EMSR853](https://mapping.emergency.copernicus.eu/activations/EMSR853/), en Santa Cruz. Registra el inicio del evento el 13/12/2025 a las 23:00 UTC y ocho áreas de interés.

Propongo revisar primero Santa Rosa del Sara como caso alternativo. El producto de delimitación 2155 tiene estado F, tres capas y adquisiciones COSMO-SkyMed del 17/12 a las 10:22 UTC y Sentinel-1 del 18/12 a las 09:50 UTC. Portachuelo y Warnes también tienen productos de delimitación entregados. La Guardia figura sin producto de delimitación utilizable por limitaciones de teledetección.

La ficha permite buscar un contraste geográfico y temporal del caso alternativo. Falta verificar el acceso a las capas, su licencia, contenido y resolución efectiva, y seleccionar una pareja de referencia y comparación. Para un contraste independiente debe comprobarse que las observaciones usadas en la validación sean distintas de las que alimenten el cálculo. La activación corresponde a un evento distinto al de Puente Pailas de marzo. Cambiar el caso requiere revisión de #110 antes de publicar un resultado en #111.

## Inspección del paquete oficial · 06/11/2026

La descarga del producto 2155 respondió HTTP 200. El ZIP contiene el polígono del área de interés y 142 polígonos en la capa de evento observado, ambos en CRS84. El [recibo](evidencias/emsr853-20261106/recibo.json) conserva la URL pública, las clases y las huellas SHA-256 del ZIP y de los dos archivos. El ZIP permanece fuera del repositorio. El conteo corresponde a geometrías del proveedor, no a hectáreas ni a un resultado propio de GeoSur.

El producto puede servir como referencia de contraste para Santa Rosa del Sara. Antes de adoptarlo, hay que revisar qué observación y fecha sustentan cada polígono y elegir imágenes independientes y comparables. El caso alternativo sigue sujeto al acuerdo de #110 y #111 sigue pendiente.

[Condiciones de uso](https://mapping.emergency.copernicus.eu/terms-and-conditions/) y [atribución del producto](https://mapping.emergency.copernicus.eu/about/citation-guidelines/). Copernicus Emergency Management Service (© 2025 European Union), [EMSR853] Santa Rosa del Sara (AOI02): Delineation Map.

La [prueba óptica del 06/11](evidencias/sara-20261106/README.md) midió una pareja por cada fecha de comparación compatible. En este recorte, ninguna de las nueve parejas de catálogo puede superar 16,39 % de cobertura conjunta con la máscara candidata. Se conserva la delimitación oficial como referencia de contraste y se propone revisar otra fuente de imágenes antes de adoptar el piloto.

## Pareja radar candidata · 06/11/2026

La tabla de fuentes del paquete identifica el origen 2 como Sentinel-1 del 18/12/2025 y el origen 3 como COSMO-SkyMed del 17/12/2025. El recorte óptico anterior se eligió alrededor de un polígono del origen 2, que podría compartir la observación del 18/12 con el análisis radar. Para contrastar con otro sensor, se propone un recorte distinto alrededor del mayor polígono del origen 3 según el área informada por Copernicus.

Para ese recorte, el [recibo de catálogo RTC](evidencias/sara-20261106/radar-catalogo.json) registra dos consultas HTTP 200 sin páginas adicionales. La pareja preferida por proximidad al evento es Sentinel-1C del 06/12 y 18/12, órbita relativa 83, descendente, VV/VH. Las cajas de ambas escenas contienen el recorte. Eso no prueba la cobertura útil, la coincidencia de píxeles ni la comparabilidad de las bandas. La observación de COSMO-SkyMed es del día anterior a la comparación, por lo que hay que revisar posibles cambios entre las dos adquisiciones.

La colección RTC de Planetary Computer exige cuenta para acceder a bandas. Aún no se descargaron. Para avanzar en #111 se necesita acceso autorizado, verificar huellas reales, malla y tratamiento radiométrico, acordar las reglas de detección y contrastar el resultado con el polígono de origen 3. Copernicus [Global Flood Monitoring](https://global-flood.emergency.copernicus.eu/general-information/about-glofas/) ofrece un producto derivado de Sentinel-1 como otra vía para revisar, sujeto a que el equipo acepte usar un producto externo y confirme acceso, cobertura y procedencia para el recorte.
