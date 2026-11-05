# Exploración de Puente Pailas, 05/11/2026

Se probó una segunda ventana en el entorno de Puente Pailas, río Grande. El [boletín del SENAMHI del 06/03/2025](https://senamhi.gob.bo/meteorologia/boletines/pronostico_hidrologico/2025/Marzo/PRON%C3%93STICO%20HIDROL%C3%93GICO%20SENAMI%20JUEVES%20%2006-03-2025.pdf) identifica esta estación. El [reporte del 16/03](https://unitel.bo/noticias/sociedad/pailon-se-anegan-cultivos-de-soya-y-sorgo-tras-la-rotura-de-un-dique-en-la-comunidad-puerto-ibanez-LH15300139) ubica afectaciones en Puerto Ibáñez. La ventana técnica [-62.80, -17.69, -62.75, -17.64] se eligió para explorar el entorno del puente. Aún falta una delimitación independiente de las áreas afectadas.

El catálogo devolvió cuatro fichas de referencia y cuatro de comparación, sin otra página. El filtro de malla y producto dejó seis parejas con siete escenas únicas. Se verificaron seis TIFF previamente capturados y se descargó un activo SCL adicional de 3 769 963 bytes.

- La pareja del 20/02 al 22/03 alcanzó **10,56 %** de cobertura comparable.
- La pareja del 25/02 al 27/03 alcanzó **1,00 %**.
- Las otras cuatro quedaron en **0 %**.

[Capturas y recibo de medición](revision.json). Bajo la máscara candidata SCL 4, 5 y 6, ninguna pareja se acerca al 80 % propuesto. El resultado es de calidad de escenas. El caso y el umbral siguen en revisión. No se calculó superficie de cambio de agua ni se inició #111.

Para repetir sin red, usa las [dependencias del lector](../../../../herramientas/met110/requirements.txt) y ejecuta `python reproducir.py RUTA_TIFF_PRIVADOS` desde esta carpeta, con los siete TIFF de la corrida local. El lector verifica cada archivo contra el recibo y comprueba que coincidan las seis parejas y sus fracciones.

Contains modified Copernicus Sentinel data (2025). Distribución: Microsoft Planetary Computer.
