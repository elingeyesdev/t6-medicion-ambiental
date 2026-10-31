# MET-02 / #110 — caso candidato y calidad de escenas

Preparación de M4 del 31/10/2026, para revisión de Jaziel. Continúa #40/PR #46, cuya aceptación sigue pendiente, en una rama dependiente explícita. La ejecución de cambios de agua #111 conserva su dependencia de #39 y del método acordado. No se cerró M3 ni se habilitó un análisis de inundaciones en la web.

## Caso y consultas

El [contexto](CONTEXTO.md) propone estudiar las crecidas del río Grande de marzo de 2025. El rectángulo técnico anterior se mantiene: OGC:CRS84, `[-62.99, -17.75, -62.90, -17.65]`, parcialmente en Santa Cruz de La Sierra y Cotoca según el catálogo versionado. No representa municipios completos. La referencia propuesta está entre 15/02 y 03/03; la comparación entre 04/03 y 24/03/2025. No se asegura que la primera estuviera seca ni que el episodio terminara el día 24.

Dos consultas nuevas de Sentinel-2 L2A al catálogo público Microsoft Planetary Computer devolvieron **8 y 10 fichas**, 276 238 bytes en total, sin siguiente página anunciada. Son nueve instantes de adquisición repartidos entre dos tiles, no dieciocho observaciones independientes. [Respuestas y recibos originales](evidencias/consultas.json), [candidatas reproducidas](evidencias/candidatas.json).

El lector anterior admite ahora una configuración opcional; su consulta y evidencia originales no cambian. La nueva configuración está en [metodo-m4.json](../../compartido/inundaciones/metodo-m4.json). El comparador exige misma plataforma, tile, órbita relativa, baseline, CRS y malla SCL de 20 m, y caja que contenga el recorte. Ordena por nubosidad de escena únicamente para priorizar el acceso; esa cifra no demuestra visibilidad local. No adopta automáticamente una pareja ni certifica su huella por comparar cajas.

## Acceso y calidad comprobados

Se probaron las dos primeras parejas técnicas del catálogo:

- **Sentinel-2B, 25/02 → 07/03/2025:** [comprobación](evidencias/calidad.json). Fracción comparable de la ventana en ambas fechas: **9,49 %**; por separado 49,01 % y 16,86 %.
- **Sentinel-2C, 20/02 → 22/03/2025:** [comprobación](evidencias/calidad-2.json). Fracción comparable conjunta: **0,77 %**; por separado 4,67 % y 29,27 %.

Se accedió solamente a cuatro activos SCL, **17 086 338 bytes**, sin descargar productos SAFE completos ni bandas de reflectancia. Cada solicitud está limitada a 8 MiB, exige HTTP 206 y longitud verificada; no sigue redirecciones ni reintenta. La firma temporal del servicio público se usa en memoria, sin claves de cuenta ni enlaces firmados en la evidencia. [Acceso documentado por Microsoft](https://github.com/microsoft/planetary-computer-sdk-for-python/blob/main/planetary_computer/sas.py).

Se comprobaron TIFF, CRS EPSG:32720, origen, resolución y la misma ventana de 478 × 554 celdas originales. El recorte usa la envolvente proyectada y su ventana exterior sobre la malla; no calcula el área exacta del polígono OGC:CRS84. Los TIFF originales permanecen privados en `tmp/`, fuera de Git. Los recibos conservan URL sin firma, tamaño y huellas del archivo y del recorte.

La fracción usa **la política candidata** de SCL 4, 5 y 6 comparables en ambas fechas. Lo demás queda excluido, no seco. No se contaron píxeles de agua ni se calculó superficie inundada. El umbral mínimo útil aún no está acordado. La baja fracción de estas dos parejas no permite evaluar toda la ventana y tampoco demuestra que no exista otra pareja óptica útil. [Clases y resolución oficiales SCL](https://sentiwiki.copernicus.eu/web/s2-processing).

SCL es una clasificación de apoyo al procesamiento atmosférico; su clase de agua y estas máscaras no constituyen una validación de inundaciones. Referencia técnica: [algoritmo L2A, sección de clasificación](https://sentiwiki.copernicus.eu/__attachments/1692737/S2-PDGS-MPC-ATBD-L2A%20-%20Level%202A%20Algorithm%20Theoretical%20Basis%20Document%202021%20-%202.10.pdf). Para la atribución: Contains modified Copernicus Sentinel data (2025). [Aviso legal Sentinel](https://sentinels.copernicus.eu/documents/247904/690755/Sentinel_Data_Legal_Notice). No se redistribuyen aquí las imágenes completas.

## Método y contrato por revisar

La propuesta distingue, solo dentro de celdas comparables en ambas fechas: agua persistente, agua presente únicamente en la comparación y agua presente únicamente en la referencia. El resto es no comparable. Para llamar inundación al cambio se necesita contexto y contraste independiente en la zona y fechas correctas. Los reportes regionales citados no son polígonos de verdad de terreno del piloto.

El cálculo de áreas deberá identificar su método, proyección y tratamiento de bordes. No se calcula en grados ni se extrapola el área visible a la parte no comparable. Las áreas de la malla UTM y un área geodésica no se confunden como magnitudes exactas equivalentes. No se derivan profundidad, daños o personas afectadas.

[Contrato propuesto para web/Flutter](../../compartido/inundaciones/CONTRATO_M4_PROPUESTO.md). La primera respuesta candidata es `sin_producto_validado`, con áreas y capas nulas, no una respuesta exitosa con cero inundación. No se cambian el OpenAPI ni las rutas actuales antes de revisar el producto.

## Cómo repetir

Python 3.12 y las dependencias aisladas del archivo de requisitos. No actualizar el entorno del servidor ni el de los productores ya en uso:

```powershell
python -m venv tmp/venv-met02
./tmp/venv-met02/Scripts/python.exe -m pip install -r herramientas/met110/requirements.txt
./tmp/venv-met02/Scripts/python.exe herramientas/met110/escenas.py revisar --directorio docs/inundaciones110/evidencias
./tmp/venv-met02/Scripts/python.exe -m unittest discover -s herramientas/met110 -p 'test_*.py' -v
python -m unittest discover -s herramientas/inundaciones -p 'test_*.py'
```

La revisión del catálogo y las pruebas no acceden a proveedores. Consultas nuevas opcionales, con destinos privados nuevos:

```powershell
python herramientas/met110/escenas.py capturar --directorio tmp/catalogo-nuevo --consultar-mpc
./tmp/venv-met02/Scripts/python.exe herramientas/met110/calidad.py --evidencia docs/inundaciones110/evidencias --privado tmp/scl-nuevo-1 --salida tmp/calidad-nueva-1.json --consultar-mpc --pareja 1
./tmp/venv-met02/Scripts/python.exe herramientas/met110/calidad.py --evidencia docs/inundaciones110/evidencias --privado tmp/scl-nuevo-2 --salida tmp/calidad-nueva-2.json --consultar-mpc --pareja 2
```

No hay red en CI: se repiten once pruebas nuevas con casos controlados y las once pruebas del lector anterior. Las de calidad prueban clases/faltantes, intersección, malla y límites de descarga; no acreditan exactitud ambiental.

## Siguiente decisión

Revisar con Jaziel evento, referencia y cobertura mínima; decidir si se buscan otras fechas ópticas o se prepara un método Sentinel-1 con su cadena y órbita propias. No aplicar un umbral radar universal ni llamar validado un producto porque se descargó. También falta una referencia independiente espacialmente adecuada. #110 sigue abierta; #111 no se inicia por esta comprobación preparatoria.
