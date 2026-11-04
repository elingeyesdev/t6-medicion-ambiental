# Calidad del aire del lugar — #101 / #66

Continuación sobre #100. GeoSur recupera la consulta puntual de aire de EnviroSense dentro de su ficha del mapa, con Laravel/Blade y el mismo estilo del frente recuperado. No es una aplicación ni una pantalla principal nueva. Tabler 1.7.1 sigue aportando los componentes; la fuente, paleta y navegación permanecen como EnviroSense.

## Recorrido

1. Entrar al mapa y seleccionar un punto. También sirve buscar un lugar o elegirlo mediante **Tiempo de un lugar**.
2. Pulsar **Calidad del aire** en la ficha. Abrir la página o seleccionar un punto no consulta este proveedor automáticamente.
3. Ver el índice europeo, su estado, partículas finas y partículas PM10. La fecha es la del dato, en hora de Bolivia.
4. Abrir **Últimas 24 horas** para consultar los valores de cada hora. **Otros contaminantes** muestra nitrógeno, ozono y azufre. **Sobre estos datos** explica escala, unidades, resolución y fuente.
5. **Actualizar aire** vuelve a consultar el servicio de GeoSur. Si la respuesta sigue en la caché de quince minutos, no se hace otra petición externa. Un fallo conserva el resultado anterior con una advertencia. Cerrar o cambiar de lugar cancela la consulta y descarta una respuesta atrasada.

La consulta es **actual**, independiente del período elegido para las lecturas guardadas del mapa. No sustituye ese historial ni carga sus valores en la base de sensores. Las 24 horas son estimaciones archivadas del proveedor; no acreditan un histórico del proyecto original.

## Qué conserva y qué corrige del origen

`referencias/envirosense/codigo/Backend/Src/modules/sensores/sensores.service.js` consulta `european_aqi` en Open-Meteo. El mismo original también contiene una conversión PM2,5 a AQI estadounidense (`Backend/Src/utils/aqiMath.js`), otras escalas para sus texturas y reemplazos aleatorios cuando falla la consulta de un lugar.

Este cambio recupera el proveedor y el índice europeo, identificados como tales. No aplica aquella conversión, no mezcla el resultado con el campo genérico `aqi/AQI` de las lecturas existentes y no genera valores de reemplazo. No se transforma el aire o la humedad en una medida de calidad del agua o ruido.

Fuente técnica: [Open-Meteo Air Quality API](https://open-meteo.com/en/docs/air-quality-api). Se pide explícitamente **CAMS global**, también para los lugares fuera de Sudamérica, evitando cambiar de modelo al cruzar Europa. La resolución declarada del producto global es 0,4° (unos 45 km); que la API entregue horas no convierte la rejilla original en una red de estaciones ni mejora esa resolución. El índice europeo actual sigue las bandas documentadas por el proveedor: hasta 20, 40, 60, 80, 100 y por encima de 100. No se recorta a 100 ni se aplican las bandas de Estados Unidos.

Los gases y partículas son concentraciones en μg/m³; `EAQI` es una unidad de índice distinta. Se mantienen cero y null. La pantalla muestra la categoría escrita además del color. No ofrece diagnósticos ni recomendaciones médicas.

## Límites de la consulta

- Endpoint HTTPS fijo, sin claves ni credenciales del usuario. Solo se transmiten las coordenadas del lugar elegido, redondeadas a cuatro decimales.
- Dominio `cams_global`, UTC y tiempo Unix; seis variables actuales y por hora, 24 horas anteriores y una posición horaria actual.
- Conexión de tres segundos y respuesta de doce; sin reintentos ni redirecciones. Máximo 512 KiB y 26 horas recibidas. El control de progreso detiene una respuesta excesiva durante su descarga.
- Fechas actuales no futuras ni anteriores a dos horas. Serie ordenada con paso de una hora; se devuelve únicamente el tramo desde consulta−24 h hasta consulta. Las posiciones futuras recibidas se excluyen, no se convierten en historial.
- Unidades exactas, tipos numéricos finitos y no negativos, vectores completos y coherentes con sus fechas. Una celda fuera del entorno de un grado respecto del lugar solicitado se rechaza. Variables null son faltantes; si no hay ninguna variable actual, no se presenta una ficha vacía como consulta correcta.
- Solo se guardan temporalmente respuestas válidas, por coordenadas y por escala. Doce peticiones por minuto y por cuenta. No se crean puntos, lecturas, alertas o mensajes.
- Cuentas activas con permiso `datos.consultar`. La demo Visualizador puede consultar. No se cambian roles o credenciales.

## Comprobación realizada

Se consultó realmente Santa Cruz, coordenadas públicas de ciudad −17,7833 / −63,1833. El servicio devolvió una celda −17,799995 / −63,199997. Para 30/10/2026 03 UTC (29/10 a las 23:00 en Bolivia), se obtuvieron índice europeo **47**, PM2,5 **17,3 μg/m³** y PM10 **18 μg/m³**, además de 24 horas recientes. Es una captura fechada y modelada; no describe permanentemente la situación de la ciudad.

La evidencia está en [evidencias/aire101](evidencias/aire101/README.md), con respuesta pública, resultado normalizado, huellas y capturas. Las pruebas no consultan Internet ni modifican la base de la demo. Pasaron **496 pruebas PHP (6009 comprobaciones)** y **110 JavaScript**; diez pruebas PHP y cuatro JavaScript corresponden a este cambio. También pasó Pint. La revisión cruzada y CI se registran en su PR.

## Lo que sigue pendiente

La capa raster continental de AQI requiere su propio producto y publicación; esta consulta puntual no la sustituye. Tampoco se completan por esto las importaciones históricas originales, un broker/dispositivo físico, UV/evaporación raster o el trabajo Flutter de Jaziel. #66 y #62 siguen abiertas. M3 necesita revisión propia antes de main. Railway sigue pospuesto y el horario de capas de #99 continúa separado de esta consulta manual.
