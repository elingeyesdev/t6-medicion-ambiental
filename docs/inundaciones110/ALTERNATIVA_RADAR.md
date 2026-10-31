# Alternativa radar — preparación #110

Después de comprobar la baja intersección comparable de las ocho parejas ópticas, consulté solamente los metadatos públicos de Sentinel-1 RTC para **la misma caja y las mismas dos ventanas**. Los tres originales y sus recibos están en [evidencias/radar](evidencias/radar/consultas.json). No se descargaron bandas ni se inició #111.

El catálogo devolvió una ficha de referencia, **22/02/2025**, y dos de comparación, **06/03 y 18/03/2025**, sin siguiente página anunciada. Son Sentinel-1A, modo IW, órbita relativa 10 descendente, polarizaciones VV/VH y EPSG:32720. Es disponibilidad de metadatos, no comprobación de valores, cobertura válida ni inundación en el piloto. No se selecciona automáticamente la fecha más reciente.

## Impedimento de acceso y tamaño

La [colección oficial de Microsoft](https://planetarycomputer.microsoft.com/api/stac/v1/collections/sentinel-1-rtc), conservada con fecha y huella, declara que **hace falta una cuenta Planetary Computer para obtener el acceso a los raster RTC**. No se intentó autenticar, solicitar claves ni usar el acceso público de Sentinel-2 para esta colección. La consulta pública al catálogo no acredita permiso de lectura de las bandas.

Cada banda VV anuncia unos **1,86 GB**. No se descargaron archivos completos ni se probaron enlaces de bandas. Cualquier lectura futura debe limitarse al recorte necesario y verificar acceso, rango, versión y valores, usando un entorno aislado. La licencia declarada de la colección es CC BY 4.0; deben conservarse atribuciones Sentinel/Catalyst/Microsoft y las condiciones de cada insumo y servicio.

## Comparabilidad pendiente

Las fichas no tienen el mismo tamaño ni el mismo origen de raster aunque sus celdas sean de 10 m y compartan CRS. No se comparan arrays por posición: será necesario verificar la georreferencia de los archivos, su huella y una ventana común. El espaciado de 10 m no equivale a resolución efectiva de 10 m; los metadatos señalan resolución radar de 20/22 m.

La descripción de Microsoft identifica calibración de intensidad, corrección radiométrica del terreno y ortorrectificación; no es un producto SCL ni una máscara de agua lista. Para continuar hay que acordar coeficiente/unidad y transformación, máscara de nodata (-32768 anunciado), sombras geométricas y zonas no observables, ruido y tratamiento del speckle, registro espacial, condiciones de referencia y contraste independiente. No se aplica un umbral universal en dB ni se interpreta toda señal baja como inundación. La [documentación oficial de procesamiento GRD](https://documentation.dataspace.copernicus.eu/APIs/SentinelHub/Data/S1GRD.html) distingue las opciones de retrodispersión, corrección del terreno y filtrado; no prueba que este proveedor ejecute exactamente la misma cadena.

## Decisión que falta

Revisar con Jaziel si se amplían las fechas ópticas o se evalúa radar con acceso legítimo y una cadena propia. La opción GRD requeriría revisar además calibración y corrección, no renombrar archivos como RTC. Tampoco se conoce todavía una referencia espacial independiente para validar el evento dentro del recorte. El caso y método siguen propuestos, sin superficie de inundación ni pantalla nueva habilitada.

Revisión de la captura sin red:

```powershell
python herramientas/met110/radar.py --directorio docs/inundaciones110/evidencias/radar
```

Las cuatro pruebas del lector comprueban la captura real, páginas pendientes, pertenencia/fecha/geografía e integridad/enlaces. No validan un método radar ni la exactitud ambiental.
