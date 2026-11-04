# MET03 / #116: comparación acotada de fuentes forestales

Revisión documental inicial: 31/10/2026; contraste oficial y aclaraciones: 03/11/2026. GeoSur adapta EnviroSense completo en Laravel/Blade: web de Josué, móvil de Jaziel, sin chatbot. La investigación M4 puede avanzar; el procesamiento H sigue bloqueado por G (#115). La propuesta se concentra en la [hoja de decisión para Jaziel](DECISION-JAZIEL.md); esta comparación no desbloquea H.

## GFW / UMD: piloto anual propuesto

La publicación GFC-2025-v1.13 documenta pérdidas anuales de 2001–2025, con cobertura global que incluye geográficamente Santa Cruz. Su cuadrícula es de un segundo de arco, aproximadamente 30 m en el ecuador. Publica `treecover2000`, `lossyear` y `datamask`; la cobertura base representa vegetación de más de cinco metros. No equivale a un inventario actualizado de bosque remanente. La metodología cambió y exige cautela entre períodos; cubrir bosque seco no demuestra precisión local. [UMD: publicación y limitaciones](https://storage.googleapis.com/earthenginepartners-hansen/GFC-2025-v1.13/download.html).

La pérdida de cobertura arbórea incluye perturbaciones humanas o naturales, temporales o permanentes, y plantaciones. La deforestación supone conversión duradera del bosque a otro uso; no se puede renombrar toda pérdida como deforestación ni como cambio neto. [GFW/WRI: interpretación oficial](https://globalnaturewatch.org/blog/data-and-tools/2025-tree-cover-loss-data-explained/).

Hay enlaces GeoTIFF directos documentados. La licencia CC BY 4.0 exige atribución, enlace de licencia e indicación de modificaciones; Earth Engine es otra vía con registro. Esta comparación no probó descargas raster. La consulta API pequeña del 31/10 sí está acreditada en [la captura y el verificador](README.md); la nueva lectura del catálogo del 03/11 agotó el tiempo de espera. [UMD: acceso y licencia](https://storage.googleapis.com/earthenginepartners-hansen/GFC-2025-v1.13/download.html).

## Copernicus CLMS LCFM: cobertura global a 10 m

LCFM es el servicio Copernicus de cobertura terrestre y seguimiento forestal tropical. El catálogo confirma el mapa global LCM de 2020 a 10 m; su alcance global incluye Santa Cruz. El horizonte anunciado 2020–2026 y la cadencia anual prevista no prueban que todos esos productos anuales estén liberados. [CLMS: alcance y productos disponibles](https://land.copernicus.eu/en/products/global-dynamic-land-cover).

La ficha consultada tiene extensión temporal 2020, proyección EPSG:4326 y actualización `AsNeeded`. Clasifica coberturas; obtener pérdida requiere definir y validar transiciones comparables entre fechas. Para bosque seco, validar clases y estacionalidad localmente. [CLMS: ficha LCM 2020](https://land.copernicus.eu/en/products/global-dynamic-land-cover/land-cover-2020-raster-10-m-global-annual).

Su política exige atribución, declaración de cambios y evitar sugerir respaldo de la UE; permite acceso gratuito y uso comercial. [Política CLMS](https://land.copernicus.eu/en/data-policy), [condiciones de uso](https://land.copernicus.eu/en/faq/data-use-terms-and-conditions). La descarga OData de CDSE requiere token y el acceso S3 requiere credenciales; documentación de acceso no equivale a descarga comprobada. [OData](https://documentation.dataspace.copernicus.eu/APIs/OData.html), [S3](https://documentation.dataspace.copernicus.eu/APIs/S3.html).

## JRC Tropical Moist Forests: complemento distinto

TMF es un producto relacionado de la UE/JRC, distinto de CLMS LCFM; no todo producto JRC es Copernicus. Ofrece seguimiento a 30 m de bosque tropical húmedo, degradación, deforestación y recuperación. [JRC: descripción](https://forobs.jrc.ec.europa.eu/TMF). La colección publicada llega de 1990 a 2025, con GeoTIFF y Earth Engine. Se anuncia actualización anual, normalmente marzo–abril, que también revisa trayectorias recientes. [Datos](https://forobs.jrc.ec.europa.eu/TMF/data), [FAQ](https://forobs.jrc.ec.europa.eu/TMF/resources).

Para Santa Cruz serviría como contraste dentro de su dominio húmedo; no reemplaza la evaluación de bosques secos chiquitanos o chaqueños. El propio JRC incorpora UMD para pérdidas fuera del dominio TMF en sus fichas. Declara datos gratuitos sin restricción de uso, con reconocimiento y cita exigidos; no se le atribuye automáticamente CC BY. Su WMS muestra imágenes RGB, insuficientes para análisis. [JRC: dominio, acceso y licencia](https://forobs.jrc.ec.europa.eu/TMF/data).

## Límites y decisiones pendientes

Treinta metros no son 30 m²: una celda métrica cuadrada de 30 × 30 m representa 900 m², o 0,09 ha. Ese factor no es universal: GFW usa celdas angulares. Calcular área geodésica o mediante proyección equivalente y tratar bordes y ausencia de datos. UMD además desaconseja estimaciones definitivas basadas solo en contar píxeles. [Especificación UMD](https://storage.googleapis.com/earthenginepartners-hansen/GFC-2025-v1.13/download.html).

La [hoja de decisión](DECISION-JAZIEL.md) propone revisar la serie anual GFW `umd_tree_cover_loss/v1.13`, ventana 2001–2025 y GADM 4.1 BOL/8, con el filtro continuo `percent >= 30` acreditado en la captura. El metadato del producto remite a GFC-2025-v1.13; se mantienen separadas la versión API, la edición raster y la versión territorial. La tabla categórica `threshold` no se intercambia con ese filtro y la referencia habitual de WRI `> 30 %` no es idéntica. Geometría local, pertinencia en bosque seco y adopción del criterio siguen pendientes. CLMS y TMF quedan como contrastes; H conserva el bloqueo de G.
