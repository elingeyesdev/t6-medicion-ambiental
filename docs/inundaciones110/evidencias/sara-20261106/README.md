# Calidad óptica en Santa Rosa del Sara · 06/11/2026

El recorte CRS84 [-63.865, -16.457, -63.805, -16.420] contiene un polígono observado del producto EMSR853 AOI02, con origen interno 2. Se escogió antes de consultar el catálogo, por ser el mayor polígono de ese origen según el campo de área del proveedor. El área del recorte es técnica y el conteo no representa todo el municipio ni toda la inundación. [Referencia oficial](../../ALTERNATIVA_EMSR853.md).

Se buscaron referencias del 15/11 al 13/12/2025 y comparaciones del 14/12 al 24/12/2025. El catálogo devolvió 18 fichas de referencia y seis de comparación, sin otra página anunciada. Las reglas de plataforma, órbita, tile, baseline y malla dejaron nueve parejas compatibles y tres escenas distintas de comparación. Para acotar las descargas, se midió la primera pareja según el orden del catálogo para cada escena de comparación. Se usó la máscara candidata SCL 4, 5 y 6 en una ventana común de 66 654 celdas de 20 m.

| Referencia → comparación | Píxeles válidos en la comparación | Válidos en ambas |
| --- | ---: | ---: |
| 10/12 → 20/12 | 16,39 % | 2,46 % |
| 12/12 → 22/12 | 0 % | 0 % |
| 05/12 → 15/12 | 0 % | 0 % |

No se midieron las nueve intersecciones. En cambio, se midieron las tres escenas B, por lo que ninguna de las nueve parejas puede superar el 16,39 % de cobertura conjunta en este recorte con la máscara candidata. El 80 % sigue siendo un criterio propuesto, pendiente de acuerdo. El resultado permite descartar estas escenas para el piloto general bajo esa propuesta. Hace falta revisar radar u otro caso con una referencia independiente, fechas y recorte adecuados antes de #111.

[Recibo y resultados](revision.json). Dos TIFF se reutilizaron con huellas verificadas; se descargaron 4 248 920 bytes adicionales. La repetición sin red confirmó las tres parejas y sus fracciones. Para reproducir, usa las dependencias del lector y ejecuta `python reproducir.py RUTA_TIFF_PRIVADOS` desde esta carpeta. Los TIFF no se incluyen en Git.

Contains modified Copernicus Sentinel data (2025), distribución Microsoft Planetary Computer. Contraste: Copernicus Emergency Management Service (© 2025 European Union), [EMSR853] Santa Rosa del Sara (AOI02): Delineation Map.
