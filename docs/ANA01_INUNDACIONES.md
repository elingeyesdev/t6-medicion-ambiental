# ANA-01 / #40 — preparación documental de agua e inundaciones

**25/10/2026 · propuesta pendiente de Jaziel.** Se adelanta documentación, inventario y lectura de metadatos. La ejecución de G conserva la dependencia de #39 y sus fechas del Planner. No hay lectura de píxeles, cálculo de agua, mapa de inundaciones ni método aceptado. Las rutas actuales de inundaciones conservan su respuesta 503.

## Qué quedó comprobado

La [configuración de exploración](../compartido/inundaciones/exploracion.json) delimita el rectángulo **[−62,99; −17,75; −62,90; −17,65]**, longitud/latitud, OGC:CRS84. Su intersección con el catálogo territorial versionado incluye BO070101 (Santa Cruz de La Sierra) y BO070102 (Cotoca). Es un área técnica parcial, no dos municipios evaluados completos; no certifica límites legales. Verificación geométrica reproducible y hash en la evidencia.

Se buscaron TIFF/JP2/DIM y nombres Sentinel en Descargas, GeoCruz demo, GeoCruz oficial y EnviroSense-equipo11, sin hallar imágenes utilizables en esas rutas. Josué indicó que desconoce la ubicación original. Se puede partir de nuevas adquisiciones públicas cuando se acuerde el análisis; no se afirma que no existan imágenes en todo el PC.

El catálogo CDSE respondió **HTTP 200 con HTML de rechazo**, tanto en el diagnóstico inicial GET/POST como en la consulta GET conservada. El lector registra `catalogo_no_verificado` y número de escenas desconocido, sin convertir el fallo en cero escenas. No se desactivó TLS ni se solicitaron credenciales.

Una consulta explícita alternativa al STAC de **Microsoft Planetary Computer** sí devolvió metadatos: **8 fichas en septiembre de 2024**, sin otra página anunciada, y **12 fichas de la primera página de marzo de 2025**, con paginación pendiente. Son tiles 20KNF y 20KMF; distintas fichas pueden corresponder al mismo instante. [Originales y hashes](evidencias/ana01/README.md). El servicio documenta acceso público al catálogo, distinto del acceso a los activos. [Documentación de Microsoft](https://planetarycomputer.microsoft.com/docs/).

Ejemplos de fichas, **no una pareja elegida para analizar un evento**:

- `S2A_MSIL2A_20240903T141711_R010_T20KNF_20240903T195858`: 03/09/2024 14:17:11 UTC, nubosidad de escena 0,140036 %.
- `S2C_MSIL2A_20250322T141811_R010_T20KNF_20250322T185816`: 22/03/2025 14:18:11 UTC, nubosidad de escena 49,149987 %.

La búsqueda usa cajas envolventes: aún hay que comprobar la huella real y el recorte. Nubosidad de toda la escena no acredita visibilidad del área. Los enlaces SCL están listados, pero no se descargaron ni probaron sus píxeles. Septiembre y marzo son ventanas técnicas arbitrarias, de estaciones distintas; **no acreditan una referencia antes/después ni un episodio de inundación**.

## Producto y método que propongo revisar

**Sentinel-2 Level-2A**, con reflectancia de superficie y clasificación SCL de Sen2Cor. SCL se entrega a **20 m**, aunque otras bandas tengan otro tamaño de píxel. Conservar producto, tile, fecha, plataforma, baseline de procesamiento y geometría. [Producto oficial](https://sentiwiki.copernicus.eu/web/s2-products), [resoluciones por capa](https://documentation.dataspace.copernicus.eu/APIs/SentinelHub/Data/S2L2A.html).

SCL identifica agua con la clase 6 y distingue nubes, sombras, píxeles defectuosos y otras clases. Su finalidad principal es apoyar la corrección atmosférica; **no es una clasificación estricta de cobertura del suelo ni una validación de inundaciones**. [Algoritmo oficial, sección 3](https://sentiwiki.copernicus.eu/__attachments/1692737/S2-PDGS-MPC-ATBD-L2A%20-%20Level%202A%20Algorithm%20Theoretical%20Basis%20Document%202021%20-%202.10.pdf), [códigos SCL](https://sentiwiki.copernicus.eu/web/s2-processing).

Como exploración inicial propongo comparar la clase de agua, sin inventar un umbral NDWI/MNDWI:

1. Acordar un evento/objetivo y un área antes de seleccionar dos escenas. Revisar referencia previa, estacionalidad y una fuente independiente de contraste. Las ventanas de este ensayo de catálogo no satisfacen ese paso.
2. Leer metadatos y recortes SCL/reflectancia de las escenas elegidas; verificar integridad, huella, CRS, resolución y procesamiento. Usar la misma malla y remuestreo de vecino más próximo para clases, nunca interpolar sus números.
3. Propuesta conservadora: comparar solo píxeles clasificados 4, 5 o 6 en **ambas** fechas. Excluir nubes, sombras, nieve, no-data, defectuosos y clases dudosas; contarlos como no comparables, no como ausencia de agua. Esta es una política de GeoCruz pendiente de revisión, no una regla impuesta por ESA.
4. Separar agua persistente, agua presente solo en la segunda fecha y agua presente solo en la referencia, siempre sobre el área comparable. Mostrar cobertura válida y faltantes. El área de píxeles se calculará en una proyección métrica comprobada, no en grados ni extrapolando al municipio.
5. Interpretar una diferencia como **cambio de agua observado**, sujeto a errores de clasificación, fechas y visibilidad. Para llamarlo inundación se necesita contexto del evento y contraste independiente; no estimar profundidad, gravedad, daño ni personas afectadas a partir de esta máscara.

Si la nubosidad impide una comparación útil, no forzar el resultado. Considerar Sentinel-1 como alternativa a acordar, registrando calibración, ruido, geometría/terreno, polarización, órbita y comparación temporal. Un umbral universal de retrodispersión no se adopta aquí. [Cadena de procesamiento SAR oficial](https://documentation.dataspace.copernicus.eu/APIs/SentinelHub/Data/S1GRD.html).

## Acceso, licencia y responsabilidades

El catálogo CDSE y la descarga son operaciones distintas; para descargar por sus servicios se seguirá el flujo de cuenta/token documentado, sin publicar contraseñas o tokens ni trasladarlos a Flutter. No se creó una cuenta ni se descargó un producto completo. [STAC](https://documentation.dataspace.copernicus.eu/APIs/STAC.html), [autenticación](https://documentation.dataspace.copernicus.eu/APIs/Token.html).

Los datos Sentinel tienen su aviso legal propio; no se les asigna automáticamente la licencia CC BY de otro proveedor. Conservar atribución y, para derivados, la indicación «Contains modified Copernicus Sentinel data» con los años correspondientes. [Aviso legal Sentinel](https://sentinels.copernicus.eu/documents/247904/690755/Sentinel_Data_Legal_Notice). Las condiciones y cuotas del distribuidor se comprueban por separado.

Josué prepara selección, lectura y procesamiento después de resolver las dependencias; Jaziel revisa método, fechas y necesidades móviles. Quedan por confirmar: ubicación de los archivos originales, evento/objetivo, pareja y baseline de referencia, calidad local, acceso a recortes, validación y contrato de consumo. Los metadatos disponibles no eliminan esos pendientes.

## Cómo comprobar este avance

Desde esta copia, **sin Internet ni cuentas**, con Python 3.12 o superior:

```powershell
cd C:\Users\cjosu\Desktop\GeoCruz-oficial\tmp\analisis-inundaciones
python herramientas/inundaciones/catalogo.py revisar
python -m unittest discover -s herramientas/inundaciones -p "test_*.py"
```

El primer comando debe mostrar CDSE no verificado, 8 y 12 fichas MPC, paginación pendiente en B y **agua/inundación no calculadas**. Los tests usan respuestas congeladas o modificaciones controladas: no son nuevas observaciones.

Validación local: **11 pruebas correctas** y verificación del área contra las geometrías y la evidencia conservada. El workflow ANA-01 repite estos controles en Python 3.12 sin consultas a proveedores.

Para repetir una consulta pequeña, con Internet y un directorio de salida **nuevo**:

```powershell
python herramientas/inundaciones/catalogo.py consultar --proveedor mpc --ventana A --salida tmp/ana01/nueva-A
```

Consulta una sola página de hasta 12 fichas, con límite de 2 MiB y 30 s. No sigue enlaces de activos, páginas ni redirecciones automáticamente; no descarga bandas. Conserva el original JSON y su hash solo si es verificable. HTML y fallos tienen diagnóstico separado. La configuración permite elegir explícitamente `cdse` o `mpc`; no cambia de proveedor en silencio. No sobrescribe evidencias existentes.

`herramientas/inundaciones/verificar_area.py` repite la intersección con Shapely usando las versiones fijadas en `herramientas/inundaciones/requirements.txt`; CI también la verifica. Se instala en un entorno Python del proyecto con `python -m pip install -r herramientas/inundaciones/requirements.txt`. El lector de catálogo no requiere esas bibliotecas y la verificación no depende de la rama de la API de sequías.

## Propuesta de consumo y siguiente entrega

La [semántica compartida propuesta](../compartido/inundaciones/README.md) queda para revisar con Jaziel. Aún no se añade al OpenAPI operativo ni se implementa HTTP/Blade/Flutter. La adquisición y el procesamiento serán tareas previas a la consulta web, con originales privados/versionados, metadatos y resultados ligeros trazables. M4 podrá desglosar implementación cuando se acuerden método e insumos; no se declara ejecutado G ni cerrado #40 por este documento.
