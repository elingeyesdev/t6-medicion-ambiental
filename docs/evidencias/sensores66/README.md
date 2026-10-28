# Revisión local: logo elegido, mediciones e historial

28/10/2026. Tareas #66 y #69. Este avance se revisa sobre la rama de cuentas de #68 y conserva la cadena de PR anterior; no cambia el corte M2 en `main`.

- Se aplicó la propuesta 3, **Trama ambiental**, como monograma SVG compartido. Se comprobó en escritorio, móvil y oscuro.
- Laravel: **295 pruebas / 2157 comprobaciones** en la suite completa; las **19 pruebas nuevas / 129 comprobaciones** de datos volvieron a pasar después de ajustar la pantalla. SQLite de pruebas aislada.
- JavaScript existente: **48 pruebas** correctas. Sintaxis de `mediciones.js` comprobada con Node. Pint y `composer validate --strict` correctos.
- El catálogo se reproduce con `python herramientas/datos/recuperar-catalogo.py --comprobar`: 61 puntos, cinco variables. `referencias/envirosense/verificar.py` confirma los 352 archivos conservados, incluidas las dos sustituciones privadas documentadas anteriormente.
- En la base local se aplicó una migración aditiva después de crear un respaldo privado. Se conservaron las dos cuentas. No se copiaron cuentas del referente ni se modificó el rol de la cuenta demo.
- Demostración explícita: **360 lecturas simuladas** para tres ciudades. No hay lecturas observadas en esta base. Una consulta manual a Open-Meteo guardó **dos estimaciones**; `captura-modelo.json` contiene solo campos ambientales públicos. La captura externa quedó deshabilitada por defecto.
- Se probaron en navegador cambio de punto Santa Cruz → La Paz, selección de Cochabamba desde el mapa sin inventar lecturas donde no las hay, filtro de estimaciones, gráfico, tabla, consulta móvil, tema y menú. La cuenta visualizadora no muestra controles de escritura.

## Capturas

- `escritorio.png`: filtros de consulta y marca elegida.
- `estimacion.png`: mapa y registro de la captura del tiempo, separado de los simulados.
- `oscuro.png`: contraste del panel y símbolo.
- `movil.png`, `movil-grafico.png`, `movil-marca.png`: ancho de 390 px, fechas completas, gráfico y menú con logo.

Las capturas muestran datos de demostración o modelo y una cuenta local visualizadora. No contienen contraseñas, tokens, cookies ni claves de proveedores. La atribución cartográfica pertenece a la base de Mapbox y sus proveedores.

## Pendiente

Revisión de Jaziel, conexión Flutter, MQTT, radar, ETL/data-worker, copia histórica saneada y prueba real de PostgreSQL. La demostración no prueba estaciones conectadas, precisión de un sensor ni cobertura ambiental continental. #66 permanece abierto.
