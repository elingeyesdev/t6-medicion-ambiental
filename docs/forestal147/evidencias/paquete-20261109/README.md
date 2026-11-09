# Paquete completo para revisión local

El 09/11 se reprodujo el recorte técnico con las tres bandas capturadas el 08/11, ampliando únicamente los años seleccionados a 2001–2025. No se descargaron datos nuevos ni se amplió la ventana espacial de 64 por 64 píxeles. El caso sigue sin territorio municipal aprobado.

La identidad del paquete es 78a62654465d330e4aa0f241d74e237bfa65de709dee827099dc8594d4e88730. Es el SHA-256 de los bytes de manifiesto.json. Este manifiesto vincula versión, tesela, captura original, criterio, ventana y años con las huellas de resumen.json y perdida.geojson. No es una firma digital ni acredita aceptación científica.

## Reproducir desde la raíz

Con las dependencias de herramientas/forestal/requirements-raster.txt instaladas, elegir carpetas nuevas:

~~~powershell
python -c "from pathlib import Path; import shutil; shutil.copytree('docs/forestal147/evidencias/recorte-20261108/captura', 'tmp/forestal-completo/captura')"
python herramientas/forestal/recorte_raster.py --columna 25200 --fila 28000 --ancho 64 --alto 64 --umbral 30 --desde 2001 --hasta 2025 --captura tmp/forestal-completo/captura --salida tmp/forestal-completo/resultado --reproducir
python herramientas/forestal/empaquetar_revision.py --caso tmp/forestal-completo --destino tmp/revisiones-forestales
~~~

El último comando contrasta el caso antes de empaquetarlo, crea una carpeta por identidad y no sobrescribe un paquete existente. Repetir el mismo caso devuelve la misma identidad; si su contenido fue alterado, falla. Un error de escritura elimina la carpeta temporal. No cambia el catálogo ni publica el resultado.

El lector web debe usar exclusivamente un directorio privado. El paquete conserva estado revision_tecnica y criterio.adoptado false. Una revisión local no habilita su publicación en producción.

## Comprobaciones

Pasaron 63 pruebas de Python. Las seis nuevas comprueban repetición, hashes, identidad distinta al cambiar de período, rechazo de resultados alterados y limpieza ante fallos de escritura. contraste.json registra la comprobación del caso completo. Las cifras siguen siendo áreas cartografiadas de este rectángulo, sin estimación estadística de exactitud ni contraste de campo.

Las respuestas originales conservan el crédito Hansen/UMD/Google/USGS/NASA y licencia CC BY 4.0 documentados en la evidencia del recorte. Se añadió empaquetado y selección de todos los años; no se modificaron los píxeles fuente.
