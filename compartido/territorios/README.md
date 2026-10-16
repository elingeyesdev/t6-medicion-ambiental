# Catálogo territorial de Santa Cruz · GEO-01 / #16

Versión `scz-codab-v02-1.0.0-propuesta`. Estado **propuesta pendiente de revisión** de Jaziel y acuerdo del equipo. Contiene 1 departamento, 15 provincias y 56 unidades de nivel municipal, vinculados uno a uno con 72 geometrías publicadas.

## Dónde está cada cosa

- `datos/catalogo.json`: identificadores, nombres, tipos y padres; es común a Laravel y Flutter.
- `datos/limites/`: GeoJSON completo de departamento, provincias y municipios. Sin recorte, reparación o simplificación de coordenadas.
- `fuentes/fuentes.json`: productor, publicador, URLs, versión, fechas, licencia y SHA256 del ZIP fuente.
- `fuentes/nombres_ine_2024.json`: los 56 nombres consultados en el portal INE del Censo 2024.
- `fuentes/conciliacion_nombres.json`: correspondencia uno a uno entre PCODE, nombre de la geometría y etiqueta INE.
- `manifiesto.json`: SHA256 de cada recurso publicado. Comprueba consistencia; no constituye una firma o aprobación del proveedor.
- `../../web/app/Services/Territorios/`: `CatalogoTerritorial.php` y `FiltroTerritorialInvalido.php`, sin dependencia de Laravel, red o base de datos.
- `comprobar.php` y `pruebas/reglas.php`: validación de recursos y 244 comprobaciones de filtros, incluidas las 56 relaciones municipales válidas e incompatibles.
- `herramientas/validar_geometrias.py`: validación completa de los 72 polígonos y las 16 divisiones padre-hijos.
- `revisar_catalogo.php`: herramienta local para probar selecciones. No es el visor ambiental ni una operación de la API de DOC-12.

## Cómo probar

Con PHP 8.2+ en PATH, desde la raíz de la rama de #16:

```powershell
php compartido/territorios/comprobar.php
.\revisar-territorios.ps1
```

Abrir **http://127.0.0.1:8001/revisar_catalogo.php**. Detener el servidor con Ctrl+C. La herramienta escucha únicamente en el PC local y no necesita Laravel, Composer o claves de proveedores.

Casos para probar en los selectores:

1. Dejar ambos sin filtro: **Santa Cruz · BO07**.
2. Seleccionar **Andrés Ibáñez · BO0701** y **Santa Cruz de La Sierra · BO070101**: selección válida del municipio.
3. Seleccionar **Andrés Ibáñez · BO0701** y **Warnes · BO070201**: rechazo `FILTROS_INCOMPATIBLES`, HTTP 422.
4. Dejar provincia sin filtro y seleccionar **Cotoca · BO070102**: la provincia se obtiene como **Andrés Ibáñez · BO0701**.
5. Abrir Cordillera en el listado: Charagua y Gutiérrez conservan las denominaciones de autonomía que muestra el INE.

La lista incluye las 56 unidades para probar tanto selecciones válidas como incompatibles. Los selectores del producto se implementarán en #19 y #20; estos deberán vaciar una selección municipal incompatible al cambiar de provincia.

## Identificadores y nombres

Se conservan los **PCODE de OCHA COD-AB v02**: `BO07` (departamento), `BO0701` (provincia), `BO070101` (municipio). Son cadenas opacas, no enteros ni códigos oficiales INE. No se calculan padres recortando cadenas: se utiliza la relación publicada `adm1_pcode`/`adm2_pcode` de cada registro fuente.

El campo `cod` del mapa público del INE es ordinal. No se lo utilizó como código INE o identificador territorial. Los nombres se concilian de forma uno a uno, ignorando diacríticos, espacios y texto entre paréntesis únicamente para la comparación. Se conservan tanto la etiqueta original de la geometría como la etiqueta completa del INE. Los 56 nombres encuentran correspondencia única; no se usan asignaciones por proximidad.

`tipo=municipio` expresa el nivel de consulta de DOC-12, incluyendo las dos unidades que el INE denomina como autonomías indígenas. `denominacionAutonomiaINE` conserva esa distinción sin renombrarlas como provincias o inventar una clasificación legal actualizada. La adopción de esta representación sigue pendiente del equipo.

No convertir estos IDs a GADM: **`BO07` no significa `BOL/7`**. La muestra GFW de DOC-13 utilizó `BOL/8` y las detecciones FIRMS se filtraron con otro polígono. Esas muestras no demuestran equivalencia de geometrías o integración del nuevo catálogo.

## Reglas propuestas

- Sin provincia ni municipio: departamento completo.
- Provincia conocida: consultar su polígono y sus hijos publicados.
- Solo municipio: inferir su provincia por el catálogo; ambos proporcionados: exigir correspondencia.
- Identificador ajeno, vacío explícito o usado en otro nivel: rechazar. No buscar por nombre o por cercanía para corregirlo silenciosamente.
- Geometría ausente o versión diferente: error; no reemplazar por una caja rectangular o devolver datos sin filtro.
- Coordenadas en **OGC:CRS84 / WGS84**, grados decimales, orden **[longitud, latitud]**.
- Para una detección exactamente en una frontera compartida, la propuesta de #18 es identificar la asignación ambigua y evitar duplicados en el conjunto departamental. La regla geométrica de puntos y su implementación/revisión pertenecen a #18; esta clase resuelve relaciones administrativas y recupera polígonos, no clasifica eventos ambientales.

## Fuente, licencia y vigencia

Geometrías: [Bolivia COD-AB, OCHA/HDX](https://data.humdata.org/dataset/cod-ab-bol), versión 02, productor **Ministerio de Desarrollo Rural y Tierras**. El publicador declara coordenadas creadas/editadas el **01/01/2013**, validez para uso humanitario del **16/09/2024** y revisión del **30/10/2025**. Una fecha de revisión o descarga reciente no actualiza por sí sola los límites.

Atribución: **Ministerio de Desarrollo Rural y Tierras; OCHA FIS / HDX. Bolivia COD-AB v02. [CC BY 3.0 IGO](https://creativecommons.org/licenses/by/3.0/igo/legalcode).** Se seleccionó Santa Cruz y se añadieron propiedades y nombres de consulta; las coordenadas se conservaron numéricamente idénticas. No se presume aval del productor.

Contraste nominal: [INE, Censo 2024](https://cpv2024.ine.gob.bo/), variable pública `santacruz_municipios`. Se conserva una instantánea de nombres y su fecha de consulta. No se localizó licencia abierta explícita del sitio; se mantienen únicamente nombres territoriales y referencias, sin redistribuir el HTML, imágenes ni estadísticas poblacionales.

El conjunto nacional publicado tiene 9/112/339 unidades por nivel; el contraste realizado cubre exclusivamente Santa Cruz, con 1/15/56. La fuente advierte huecos hidrológicos en otros departamentos; no se inventaron superficies para completarlos. Esta propuesta es cartografía de referencia del conjunto publicado, no una certificación de deslinde legal vigente o actualización administrativa a 2026.

## Validación geométrica completa

Con Python 3.12 y un entorno virtual de herramientas:

```sh
python -m venv .venv-geo
```

Activar con `.\.venv-geo\Scripts\Activate.ps1` en PowerShell o `source .venv-geo/bin/activate` en Linux/macOS. Después:

```sh
python -m pip install -r compartido/territorios/herramientas/requirements.txt
python compartido/territorios/herramientas/validar_geometrias.py
```

Se revisan todos los polígonos, correspondencia de IDs, validez topológica y la unión de hijos de cada provincia y del departamento. Las comparaciones de área se realizan en **EPSG:6933**, nunca con grados como metros. La tolerancia de **1 m²** solo admite residuo numérico; no representa precisión cartográfica. Los datos de salida permanecen en longitud/latitud.

Reproducción de extracción y comparación con origen:

```sh
python compartido/territorios/herramientas/preparar_catalogo.py --descargar
python compartido/territorios/herramientas/validar_geometrias.py --zip tmp/bol_limites.geojson.zip
```

La descarga debe coincidir con el SHA256 fijado. Si cambia el recurso remoto, se rechaza y se revisa la nueva versión; no se actualizan datos en silencio. Los límites no se reconstruyen a mano. La validación normal y CI usan los recursos fijados y no requieren consultar servicios externos.

`.gitattributes` conserva LF en JSON y GeoJSON para que las huellas coincidan también al clonar en Windows. No editar a mano los recursos generados: modificar una fuente requiere revisar su versión y reconstruir el paquete.

Los informes y capturas de ejecución están en `docs/evidencias/geo01/`; seguimiento de la decisión en `docs/DECISIONES_M2.md` y en #14/#16. Registrar el acuerdo de Jaziel antes de cambiar el estado de propuesta.
