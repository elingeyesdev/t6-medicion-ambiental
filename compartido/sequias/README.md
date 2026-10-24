# Muestras territoriales candidatas de sequías

[`muestras.json`](muestras.json) contiene **56 puntos de solicitud**, uno por unidad municipal del catálogo compartido de Santa Cruz. Versión `scz-muestras-sequias-0.1-propuesta`, pendiente de revisión junto con #35. No son estaciones, medias municipales, una nueva división administrativa ni resultados de sequía.

Cada entrada conserva un identificador estable, PCODE municipal/provincial, coordenada solicitada `[longitud,latitud]` y método de selección. El piloto de #35 mantiene `scz-piloto-01`, BO070101 y `[-63.1821,-17.7833]`. Los otros 55 usan `scz-<pcode en minúsculas>-01` y un punto interior de la geometría, redondeado a seis decimales para el cliente de captura. Ese redondeo es un formato de intercambio, no precisión geográfica acreditada.

El archivo fija versión y SHA-256 del catálogo territorial y los límites municipales. El generador verifica esos hashes contra el manifiesto original; no modifica los polígonos. La selección usa `representative_point` / [point_on_surface de Shapely](https://shapely.readthedocs.io/en/stable/reference/shapely.point_on_surface.html), no un centroide que podría quedar fuera de una forma cóncava o dentro de un hueco. Después de redondear se exige interior estricto, separación numérica del borde y pertenencia a un único municipio. PHP repite la comprobación con el algoritmo territorial existente y una prueba de interior que excluye bordes/huecos.

Las coordenadas de celda climática y SPI **no están en este archivo**. Se obtienen, cuando corresponda, de una captura/cálculo de un período y producto concretos. Compartir un punto no acredita disponibilidad climática; que todos los municipios tengan punto tampoco acredita 56 celdas diferentes o cobertura de superficie.

## Uso y reproducción

Desde `web/`, con PHP y dependencias instaladas:

```sh
php artisan sequias:muestras
php artisan sequias:muestras --provincia=BO0701
php artisan sequias:muestras --municipio=BO070101
php artisan sequias:muestras --geojson
```

Todos listan o exportan sin red. La selección puede añadir `--muestra=scz-piloto-01`. Provincia y municipio deben ser compatibles; un municipio infiere su provincia. Si se agrega un filtro de muestra, el resumen distingue las muestras existentes en el territorio de la única seleccionada.

Para reproducir con Python 3.12, desde la raíz, en un entorno separado:

```sh
python -m pip install -r herramientas/sequias/requirements-muestras.txt
python herramientas/sequias/preparar-muestras.py --salida tmp/muestras-reproducidas.json
python herramientas/sequias/test_muestras.py
```

El catálogo fue generado con NumPy 2.3.4, Shapely 2.1.2 y GEOS 3.13.1, registrados en el archivo. No hay descargas de geometrías ni llamadas climáticas en esa generación. Las pruebas contrastan el catálogo congelado con la regeneración y casos cóncavos, huecos, multipolígonos, geometrías inválidas y pérdida del interior por redondeo. Python/Shapely son herramientas de preparación; no se necesitan para arrancar Laravel ni listar muestras.

Consulta registrada y prueba sencilla: [API02_MUESTRAS_TERRITORIALES.md](../../docs/API02_MUESTRAS_TERRITORIALES.md). La adopción del método, API pública y clientes sigue pendiente.
