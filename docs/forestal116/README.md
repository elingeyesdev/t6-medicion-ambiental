# MET-03 / #116 — propuesta para pérdida de cobertura forestal

Preparado el 31/10/2026 por Josué para revisión de Jaziel. **Método propuesto, sin adoptar ni integrar a la aplicación.** Es investigación adelantada de M4; el procesamiento de H y el piloto #117 conservan la dependencia de G / #115. No se modifican Flutter, main, cuentas ni datos del servidor local.

## Lo comprobado

La captura guardada contiene cuatro consultas públicas nuevas a GFW: producto, versión, campos y serie. Respondieron HTTP 200, sin claves, y suman 74 090 bytes. Los originales y sus recibos fechados con SHA-256 están en [evidencias](evidencias/consultas.json). El verificador reproduce el ejemplo compartido sin Internet y rechaza otra consulta, una captura incompleta o una alteración. La descarga opcional consulta solo esos cuatro recursos, exige una opción expresa y usa un directorio nuevo; no sigue redirecciones ni reintenta.

La versión fijada `umd_tree_cover_loss/v1.13` ofrece años semánticos 2001–2025. La consulta devuelve 25 filas para **GADM 4.1, BOL/8**, identificado por GFW como Santa Cruz. No se lo convierte en un código de nuestro catálogo ni en resultados municipales. La identificación pública se puede contrastar en el [panel de GFW](https://www.globalforestwatch.org/dashboards/country/BOL/8/); la equivalencia geométrica local está pendiente.

Se repitió la condición de ensayo de DOC-13: campo `umd_tree_cover_density_2000__percent >= 30`. No equivale a seleccionar la clase codificada `threshold = 30`. El catálogo de campos y la consulta exacta quedan conservados, sin corregir o completar cifras del proveedor.

## Qué propongo revisar

Usar inicialmente esta serie anual departamental como caso de comprobación. La unidad es hectáreas del resumen del proveedor; se conserva el decimal recibido como cadena, sin atribuir a sus decimales una exactitud real. El criterio del 30 % y esta geometría son **candidatos**, no una definición legal de bosque aprobada. La versión no informa pérdidas de 2026.

Para H habrá que acordar una zona piloto y validar sus límites; si se ofrecen provincias/municipios o una capa, deberán calcularse o consultarse con la misma definición y el recorte correspondiente. No se repartirá la cifra departamental entre municipios. La resolución angular de GFC no permite aplicar 900 m² por píxel como constante universal. Las áreas de un mapa son estimaciones cartográficas: su validación estadística es un trabajo separado. [Especificaciones y cautelas de UMD](https://storage.googleapis.com/earthenginepartners-hansen/GFC-2025-v1.13/download.html).

La pérdida del producto no demuestra deforestación permanente, causa, ilegalidad ni pérdida neta. Tampoco se resta directamente de una cobertura antigua para anunciar bosque actual. Son distinciones explicadas por [WRI/GFW](https://gfr.wri.org/annual-tree-cover-loss-data-explained). Las alternativas Copernicus CLMS y JRC se revisan por separado en [ALTERNATIVAS.md](ALTERNATIVAS.md).

Los datos UMD indican CC BY 4.0 con atribución. El límite GADM tiene condiciones distintas: no se redistribuye su geometría ni se le asigna la licencia de UMD. [Licencia UMD](https://storage.googleapis.com/earthenginepartners-hansen/GFC-2025-v1.13/download.html), [condiciones GADM](https://gadm.org/license.html).

## Contrato que pueden revisar ambos clientes

[Propuesta del contrato](../../compartido/cobertura-forestal/README.md) y [ejemplo generado](../../compartido/cobertura-forestal/ejemplo-propuesto.json). No existe todavía una ruta de consulta forestal ni una pantalla habilitada. Los campos de estado, criterio, versión, unidad, fuente y territorio impiden presentar la muestra como integración completa.

Un año ausente o nulo se conserva como `no_informado`; cero sigue siendo un dato válido. Una serie parcial solo tiene subtotal informado; su total de todo el período queda nulo. Tener 25 filas **no acredita** que se observó el 100 % del territorio: la fracción válida no viene en esta respuesta y queda desconocida. No hay capa ni exactitud municipal verificadas.

## Repetir la comprobación

Python 3.12 o posterior, sin dependencias adicionales, desde la raíz:

```powershell
python herramientas/forestal/verificar_fuente.py revisar --directorio docs/forestal116/evidencias
python -m unittest discover -s herramientas/forestal -p 'test_*.py' -v
```

Para generar de nuevo el ejemplo desde los originales guardados:

```powershell
python herramientas/forestal/verificar_fuente.py revisar --directorio docs/forestal116/evidencias --salida compartido/cobertura-forestal/ejemplo-propuesto.json
```

Consulta nueva opcional, pequeña y explícita; usar un destino que no contenga evidencia previa:

```powershell
python herramientas/forestal/verificar_fuente.py capturar --directorio tmp/forestal-nueva --consultar-gfw
```

No descarga escenas, procesa raster, publica el producto ni escribe en la base de GeoSur. Un fallo no se transforma en cero pérdida.

Comprobación local: 13 pruebas correctas, incluidas lectura sin red, integridad, versión, años duplicados/futuros, cero, nulos y series parciales. También se ejecutó la captura real mediante el comando opcional. Una repetición nueva puede tener diferencias numéricas mínimas por la agregación del proveedor; se verifica la integridad de cada captura por separado, sin exigir identidad de bytes entre consultas distintas. El workflow repite la comprobación sin proveedores en Linux y Windows; su resultado se revisa en el PR.

## Revisión pendiente

Jaziel debe revisar producto/versión, parámetro de cobertura, período, geometría y lectura desde Flutter. Ambos deben acordar el piloto y su contraste antes de #117. #116 queda en revisión, no cerrada por esta preparación. #109 conserva la replanificación con la capacidad real del equipo; los pendientes M3 siguen en M3.
