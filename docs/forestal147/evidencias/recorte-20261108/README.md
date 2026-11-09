# Recorte técnico oficial del 08/11/2026

Se leyó una ventana rectangular de la tesela 10S_070W, columnas 25200 a 25263 y filas 28000 a 28063. Borde oeste -63,700°, este -63,684°, norte -17,000° y sur -17,016°. Son coordenadas de una prueba técnica, sin equivalencia municipal aprobada.

Las tres bandas llegaron mediante 21 respuestas HTTP 206: 1.103.455 bytes en total. La carpeta captura conserva cada rango exacto, URL, ETag, Content-Range, hora UTC y SHA-256. Las consultas figuran como 09/11 en UTC y corresponden a la noche del 08/11 en Bolivia. No se descargaron los GeoTIFF completos. La primera exploración privada de cabeceras no forma parte de estos 1.103.455 bytes.

El original se decodifica por tiras de una fila: solo se reconstruyen las filas necesarias y luego se recortan las columnas. No se abre en memoria la tesela de 40.000 por 40.000 píxeles. La cuadrícula guardada en estos archivos es de 0,00025° por píxel. La descripción web indica nominalmente un segundo de arco; se conserva esta diferencia y se usa la transformación del archivo, sin corregirla a mano.

## Resultado reproducido

Con cobertura base 2000 mayor o igual a 30 %, todavía no adoptada, se identificaron 2.838 celdas arboladas de 4.096 celdas de tierra base. En los años 2024 y 2025 hay 26 celdas de pérdida cartografiada que suman aproximadamente 1,91 ha en este rectángulo. No es un total municipal, una medición de deforestación confirmada ni una estimación estadística definitiva. Las áreas sin ajuste de muestreo no tienen un intervalo de confianza calculado.

resultado/resumen.json conserva la serie 2001–2025 y el total solo de los años solicitados. resultado/perdida.geojson contiene las 26 celdas con su año e identidad de fila y columna. Los tres PNG conservan el valor de cada píxel; no son fotos. La cobertura anual de observaciones queda desconocida, aunque la máscara base tenga tierra en toda la ventana.

Los rangos, las tres imágenes y la capa se reprodujeron sin Internet. Las 51 pruebas incluyen rechazo de HTTP 200 antes de leer el cuerpo, rangos incompletos, cambio de ETag, captura alterada, lectura acotada de archivos grandes, TIFF incompatible, orientación, escala, celdas limítrofes, valores fuera de versión, umbral inclusivo, cero y máscara de agua. La revisión estática independiente detectó una lectura local sin límite; se corrigió y se agregó su regresión.

## Procedencia y límites

Fuente: Hansen/UMD/Google/USGS/NASA. Producto GFC-2025-v1.13, disponible en [Global Forest Change](https://glad.earthengine.app/view/global-forest-change). Licencia [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/). Se modificaron los datos mediante recorte, filtros, vectorización de celdas y cálculo geodésico de área. No se cambió el raster original.

La [documentación de la fuente](https://storage.googleapis.com/earthenginepartners-hansen/GFC-2025-v1.13/download.html) distingue pérdida de cobertura arbórea, advierte cambios entre períodos y desaconseja interpretar el conteo de píxeles como estimación definitiva de área. datamask separa faltantes, tierra y agua persistente de su base 2000–2012; no mide disponibilidad anual completa.

Este ensayo no adopta el método, no valida un piloto ni publica el producto de #147. El contraste territorial y la decisión de #116 y #117 siguen pendientes.
