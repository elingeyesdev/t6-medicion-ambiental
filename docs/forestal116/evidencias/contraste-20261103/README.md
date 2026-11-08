# Acceso y territorio contrastados · 03/11/2026

Se completaron las cuatro consultas de la fuente GFW. Todas respondieron HTTP 200 y el lector aceptó producto, versión fija v1.13, campos y 25 años informados. [Recibos y originales nuevos](acceso/consultas.json), [validación y comparación](acceso-validado.json).

El intento anterior de 12 segundos quedó registrado como tal. Esta captura posterior permite repetir la verificación sin depender de esa conexión. El producto y la versión conservan sus huellas anteriores. El catálogo de 223 campos cambió su orden, pero todos sus contenidos coinciden al compararlos por identificador. Dos valores anuales difieren en sus últimos decimales, con diferencia máxima de 2 × 10^-11 ha y diferencia del total de -3 × 10^-11 ha. Las cifras se conservan por captura para mantener la trazabilidad. Este contraste de respuestas no determina cambios ambientales.

## Correspondencia territorial comprobada

La geometría oficial GADM 4.1 identifica **BOL.8_1 / SantaCruz**, con ISO BO-S. Corresponde al departamento que se pidió como país BOL y región 8 en GFW. El catálogo local identifica **BO07 / Santa Cruz** con límites OCHA/HDX. [Identidad de la descarga GADM](gadm-recibo.json), [datos del contraste](territorios.json).

Los contornos tienen extensiones próximas y distinto detalle. El archivo local tiene 19.415 vértices de anillos y el de GADM 2.551. El cálculo geodésico WGS84 de esos archivos da aproximadamente 368.770,28 km² y 368.660,13 km², respectivamente. La diferencia de sus áreas totales es aproximadamente 110,14 km². Esa resta no mide su diferencia espacial ni establece una corrección para las cifras forestales.

La correspondencia de nombre y código queda comprobada. La equivalencia de límites requiere todavía contrastar su intersección, topología y procedencia. El agregado continúa atribuido a GADM; una futura consulta del contorno local requiere su propia geometría y cálculo. No se distribuye el total departamental entre municipios.

## Reproducción

Desde la raíz del repositorio, crea un entorno aislado e instala las [dependencias del contraste](requirements.txt). Estas cinco pruebas territoriales usan pyproj. El verificador de fuente y sus trece pruebas usan solo la biblioteca estándar.

En Windows, con PowerShell:

```powershell
python -m venv tmp/venv-forestal116
./tmp/venv-forestal116/Scripts/python.exe -m pip install -r docs/forestal116/evidencias/contraste-20261103/requirements.txt
./tmp/venv-forestal116/Scripts/python.exe -B -m unittest discover -s docs/forestal116/evidencias/contraste-20261103 -p 'test_*.py' -v
```

En Linux o macOS:

```sh
python3 -m venv tmp/venv-forestal116
./tmp/venv-forestal116/bin/python -m pip install -r docs/forestal116/evidencias/contraste-20261103/requirements.txt
./tmp/venv-forestal116/bin/python -B -m unittest discover -s docs/forestal116/evidencias/contraste-20261103 -p 'test_*.py' -v
```

Para repetir las consultas guardadas y el contraste geométrico, usa el mismo intérprete del entorno en cada comando. En Windows, sustituye `python` por `./tmp/venv-forestal116/Scripts/python.exe` en los ejemplos siguientes:

```powershell
python -B herramientas/forestal/verificar_fuente.py revisar --directorio docs/forestal116/evidencias/contraste-20261103/acceso
python -X utf8 -B docs/forestal116/evidencias/contraste-20261103/comparar_series.py docs/forestal116/evidencias docs/forestal116/evidencias/contraste-20261103/acceso
python -B docs/forestal116/evidencias/contraste-20261103/reproducir_territorio.py --gadm tmp/gadm41_BOL_1.json
python -B -m unittest discover -s docs/forestal116/evidencias/contraste-20261103 -p 'test_*.py' -v
```

La geometría GADM usada para el contraste permanece fuera de Git. Para repetirlo, obtener el archivo indicado en su recibo y contrastar SHA-256 antes de ejecutarlo. [Descarga oficial](https://geodata.ucdavis.edu/gadm/gadm4.1/json/gadm41_BOL_1.json), [licencia GADM](https://gadm.org/license.html). Se conservan los recibos y estadísticas del análisis, junto con el contorno local ya versionado.

La medida suma los exteriores y resta los huecos, utilizando segmentos geodésicos entre los vértices publicados. Se probaron las dos orientaciones de los anillos, Polygon/MultiPolygon equivalentes, coordenadas inválidas y área no positiva. Se rechaza también una geometría GADM que no coincida con su recibo. Son cinco regresiones locales, además de las trece pruebas del lector forestal. El resultado no acredita precisión cartográfica o legal de los límites. [API Geod](https://pyproj4.github.io/pyproj/stable/api/geod.html), [salidas de pruebas](pruebas.txt).

## Siguiente decisión

La [hoja forestal](../../DECISION-JAZIEL.md) conserva el acuerdo pendiente sobre criterio >= 30 %, período y piloto. El acceso actual y la identidad departamental ya tienen evidencia para esa revisión. La comparación geométrica no adopta automáticamente una equivalencia territorial.
