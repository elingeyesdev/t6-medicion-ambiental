# MET-01 / #35: propuesta de sequía meteorológica

Estado: **propuesta pendiente de revisión de Jaziel**. Versión `spi3-era5-gamma-mle-1991-2020-v0.1-propuesta`. Preparada el 22/10/2026 (Bolivia). Esta investigación precede a la implementación Laravel #36, Blade #37 y Flutter #38; el endpoint actual de sequías continúa respondiendo 503. No requiere rehacer focos ni cambiar `main`.

## Decisión propuesta

Usar **SPI-3** para describir déficit o exceso de precipitación respecto de una referencia fija **1991–2020**. Tres meses de calendario completos, terminados en el mes solicitado, con días UTC. Es un indicador de sequía **meteorológica**, no una medición de daño agrícola, humedad del suelo, caudal o superficie afectada. La temperatura sola no permite sustituirlo.

La propuesta es un cálculo propio de GeoCruz. No se descarga ni reproduce exactamente el SPI oficial de Copernicus: se usa otro ajuste estadístico y una agregación diaria del proveedor. La aceptación del compañero permite implementar una versión candidata; no equivale a validación por estaciones locales o aprobación académica.

## Fuente y acceso verificado

Fuente de entrada: **Open-Meteo Historical Weather API**, producto explícito **ERA5**, precipitación diaria `precipitation_sum` en mm. Reanálisis global de resolución nominal 0,25°, con historia desde 1940. Combina información observacional y modelos; no es una estación observada en Santa Cruz. No usar `best_match`, pronósticos ni cambiar a ERA5-Land dentro de la misma serie. [Documentación del proveedor](https://open-meteo.com/en/docs/historical-weather-api).

Parámetros fijos del piloto: `models=era5`, `timezone=GMT`, `precipitation_unit=mm`, `cell_selection=nearest`, `elevation=nan`. Se conservan coordenada solicitada y coordenada devuelta. La resolución de la celda no acredita precisión local de 25 km ni cobertura municipal completa.

Datos distribuidos bajo **CC BY 4.0**, con atribución a Open-Meteo y ECMWF / Copernicus Climate Change Service. La API gratuita se reserva a uso no comercial; los límites y disponibilidad se revisarán para un despliegue distinto del académico. No requiere MAP_KEY de NASA. No se garantiza suministro continuo. [Licencia](https://open-meteo.com/en/licence), [condiciones de acceso](https://open-meteo.com/en/terms).

Se recibieron dos respuestas HTTP 200 para la muestra solicitada `[-63.1821,-17.7833]`, contexto BO070101. La celda devuelta en ambas fue `[-63.25,-17.75]`. La captura real contiene 11.019 días de 1990-11-01 a 2020-12-31 y 92 días de julio–septiembre de 2024. Los meses extra de 1990 permiten ventanas enero/febrero de 1991. Descarga: 23/10/2026 02:56 UTC, todavía 22/10 en Bolivia. No se acreditó disponibilidad de todos los municipios ni del último mes operativo.

## Cálculo reproducible

1. Sumar precipitación diaria de cada mes. Exigir todas sus fechas y valores finitos no negativos; un cero conocido es válido, un día ausente o `null` invalida el mes. No interpolar ni rellenar con cero.
2. Para el mes final `m` de un año `y`, sumar `m-2`, `m-1`, `m` incluyendo cambio de año. Comparar solo contra las **30 ventanas que terminan en ese mismo mes** de 1991 a 2020. No mezclar meses secos y lluviosos en una sola distribución.
3. Política conservadora propia: exigir las 30 ventanas completas y al menos 10 acumulados positivos distintos de cero operativo. Acumulados menores de 0,01 mm se tratan como cero para el ajuste. Una base positiva constante tampoco admite ajuste.
4. Ajustar Gamma de dos parámetros por **máxima verosimilitud (MLE), localización fija en cero**, solo sobre los positivos. En la referencia se usa `scipy.stats.gamma.fit(..., floc=0, method="MLE")`. La escala `θ` está en mm y no es el parámetro tasa `1/θ`. [Definición de Gamma y escala](https://docs.scipy.org/doc/scipy/reference/generated/scipy.stats.gamma.html).
5. Para `n=30`, `q=n_ceros/n`: `H(x)=q+(1-q)·G(x;α,θ)` y `SPI=Φ⁻¹(H(x))`. Si el ajuste falla, la serie es insuficiente o la probabilidad llega numéricamente a 0/1, devolver índice y categoría **null con motivo**, sin inventar un extremo finito. Clasificar el SPI sin redondear; redondear solo al presentarlo.

El fundamento Gamma → probabilidad ajustada por ceros → normal y las fronteras siguientes se apoyan en la [ficha técnica SPI de Copernicus, secciones 2–4](https://drought.emergency.copernicus.eu/data/factsheets/factsheet_spi.pdf). Esa ficha usa **L-momentos**, mientras esta propuesta usa MLE; los resultados no son intercambiables. La exigencia de 30 ventanas completas, rechazo de series constantes y manejo de extremos son políticas de esta versión.

- `SPI ≤ -2`: extremadamente seco.
- `-2 < SPI ≤ -1,5`: muy seco.
- `-1,5 < SPI ≤ -1`: moderadamente seco.
- `-1 < SPI ≤ 1`: normal.
- `1 < SPI ≤ 1,5`: moderadamente húmedo.
- `1,5 < SPI ≤ 2`: muy húmedo.
- `SPI > 2`: extremadamente húmedo.

No declarar inicio/duración de un episodio de sequía con una sola ventana. Muchos ceros, sesgo del reanálisis, solo 30 ventanas y una distribución paramétrica limitan la interpretación. No se ha contrastado el ajuste con un SPI oficial, pluviómetros locales ni observaciones de impactos; eso requiere validación posterior.

## Alcance territorial que deben respetar los clientes

El piloto usa una muestra fija de referencia, **no un promedio municipal**. Los PCODE y límites siguen siendo el catálogo común OCHA/COD-AB. La coordenada solicitada identifica el contexto territorial; la celda devuelta es el soporte climático y puede extenderse fuera del municipio. No recortar lluvia de una celda por el polígono ni llamarla estación.

La comprobación con la geometría existente confirma que el punto solicitado pertenece a **BO070101** y el centro de la celda a **BO070103**. No se reasigna el municipio del usuario a partir del centro de celda. Se conserva `municipioDelCentroCelda` como contexto del centro, no como propietario de toda la celda, y una advertencia visible. [Verificador territorial del piloto](../herramientas/met35/verificar_piloto.php), ejecutable con `php herramientas/met35/verificar_piloto.php`; [resultado conservado](evidencias/met35/pertenencia-piloto.json).

Para #36 se propone preparar un catálogo versionado de hasta una muestra interior por cada una de las 56 unidades municipales, verificar pertenencia con los polígonos y conservar la celda devuelta. No usar centroides que queden fuera del polígono. La muestra piloto debe conservar su identificador si se amplía el catálogo. La disponibilidad y los 30 años se comprobarán por cada celda; este PR solo prueba una. Si dos muestras reciben la misma celda, declarar `celdasUnicas` y no interpretarlas como observaciones independientes.

Los filtros de provincia/departamento agrupan **muestras**, no calculan un SPI territorial ni superficie afectada. Mapa con puntos y polígonos solo como contexto, sin rellenar todo el municipio con el color de una muestra. Resumen: número de muestras con/sin índice, con SPI ≤ -1, mínimo SPI y número de celdas únicas; nunca porcentaje de área en sequía. No promediar SPI de muestras para crear un SPI departamental. Antes de disponer de las 56 muestras, el backend debe informar las unidades sin muestra y cobertura parcial del catálogo.

## Contrato candidato

Ver [contrato para los clientes](../compartido/api/sequias-propuesta/README.md), esquemas JSON y ejemplo generado desde el cálculo conservado. La solicitud candidata usa `mesFinal=AAAA-MM`, territorio opcional y muestra registrada opcional. El ejemplo es septiembre de 2024; no son filtros de 168 horas ni fechas diarias de focos.

Los nueve campos comunes se conservan, con estructuras específicas de sequía documentadas. Colección pequeña, hasta 56 muestras, sin paginación ni snapshot de focos. Los clientes consumen el índice/categoría del servidor, muestran unidad adimensional, ventana, base, fuente y límites; no recalculan SPI ni sustituyen `null` por normal. La ruta operativa solo se actualizará en #36 después de revisión.

## Evidencia y verificación

- [Captura externa y manifiesto](evidencias/met35/fuente-20261022/manifest.json): URL pública exacta, parámetros, instantes UTC, coordenadas, conteos y SHA-256 de las dos respuestas sin modificar.
- [Resultado reproducido](evidencias/met35/resultado-referencia.json): 30 acumulados de referencia, parámetros del ajuste, probabilidad e índice. Para julio–septiembre de 2024: **114,5 mm**, SPI **-0,6410480784**, categoría **normal**, en esa celda.
- [Referencia ejecutable](../herramientas/met35/referencia.py): prototipo científico fuera de Laravel, sin dependencia de Python para arrancar la web.
- [Pruebas](../herramientas/met35/test_referencia.py): calendario/cambio de año/bisiesto, ausentes vs cero, entradas inválidas, base insuficiente/constante, masa en cero, fronteras, cola numérica, ecuación MLE, CDF por integración y normal por `erf`, integridad y contrato. Las series analíticas de prueba son controladas; no son evidencia de sequía real.

Para repetir offline desde la raíz de la rama, con Python 3.12:

```powershell
python -m venv tmp/venv-met35
./tmp/venv-met35/Scripts/python.exe -m pip install -r herramientas/met35/requirements.txt
./tmp/venv-met35/Scripts/python.exe -m unittest discover -s herramientas/met35 -p "test_*.py" -v
./tmp/venv-met35/Scripts/python.exe herramientas/met35/referencia.py
```

En Linux usar `tmp/venv-met35/bin/python`. La primera instalación descarga bibliotecas; las pruebas y el reproductor usan las respuestas guardadas y no consultan proveedores. El ejemplo del contrato debe conservar el mismo resultado, a tolerancia 1e-6 para un futuro portado a PHP.

Para una **nueva** consulta externa, con Internet y respetando las condiciones del proveedor:

```powershell
./tmp/venv-met35/Scripts/python.exe herramientas/met35/capturar_fuente.py --salida tmp/met35-nueva-captura
./tmp/venv-met35/Scripts/python.exe herramientas/met35/referencia.py --captura tmp/met35-nueva-captura
```

La carpeta de salida debe ser nueva. Una descarga parcial/fallida no reemplaza evidencia aceptada. Revisiones del proveedor pueden cambiar datos y hashes; conservar cada corte con su manifiesto.

## Revisión requerida y trabajo siguiente

Jaziel debe revisar: SPI-3/MLE/base fija y límites, forma de filtrar meses, `null`/errores, puntos frente a polígonos y los campos consumibles por Flutter. Resolver observaciones en este PR antes de tratar #35 como acordado.

Después: #36 implementa calendario, catálogo de muestras, caché/errores, cálculo PHP contrastado con esta referencia y consulta real; #37/#38 implementan los clientes sobre la misma versión; #39 compara resultados, faltantes, cobertura y fallos en ambos. #40 sigue siendo análisis inicial de inundaciones y #41 recopila solo evidencias ejecutadas. Las dependencias del tablero continúan vigentes mientras #35 esté en revisión; se ajustará el alcance de esos issues si cambia la decisión.
