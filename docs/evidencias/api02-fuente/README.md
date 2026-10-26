# Consulta externa de API-02

Se hizo **una nueva consulta real** desde el cliente PHP al host público de Open-Meteo. Recuperación: **2026-10-24T02:39:15.585398Z**, es decir, **23/10/2026 22:39 en Bolivia**. Producto solicitado ERA5, lluvia diaria en mm/UTC, muestra `scz-piloto-01`; período **01/07/2024–30/09/2024**.

- [`captura-objetivo.json`](captura-objetivo.json): cuerpo exacto recibido, sin reformatear. SHA-256 **1ec1fac03a7babf3fa69f87748c052899b51d67c8dcc7d804142ac132ed2d35a**.
- [`consulta-nueva.json`](consulta-nueva.json): HTTP 200, cabeceras, URL/parámetros, recuperación, procedencia, calidad resumida e ID del almacén. **92 días y tres meses completos**, cero días ausentes/null.
- [`reutilizada.json`](reutilizada.json): segunda ejecución servida desde el índice de captura, sin otra petición al proveedor; mismo ID y recuperación originales. HTTP/cabeceras descritos son los originales.
- [`ensayo-php.json`](ensayo-php.json): nueva captura objetivo combinada con la base histórica previamente conservada. No se volvió a descargar la base de #35.

La versión nueva es `81c72f90d1cd1019449b274bb7e46a1071d0d651881d10170f1f2e6bd353431e`. Se conservaron las versiones antiguas. Mensuales: **28,2 / 64 / 22,3 mm**, acumulado **114,5 mm**. El ensayo candidato con la base previa devolvió SPI **−0,6410480784168531**, categoría **normal**, 30 ventanas válidas de 1991–2020. La coincidencia con el ensayo anterior no implica que los bytes/hash de una respuesta nueva deban coincidir: incluye metadatos del proveedor y puede haber revisiones futuras.

Coordenada solicitada `[-63.1821,-17.7833]`, celda devuelta `[-63.25,-17.75]`. Reanálisis, no estación ni promedio municipal. La fuente/celda piloto no acredita disponibilidad para todas las muestras ni para el último mes. El método sigue pendiente de revisión y la API pública de sequías continúa en preparación.

Las respuestas artificiales de los tests son fixtures controladas del cliente HTTP, no evidencia externa. Las pruebas contrastan además los bytes/hash y el calendario de esta captura guardada, sin consultar Internet. [Cómo repetir y probar la reutilización](../../API02_CAPTURA_LLUVIA.md).
