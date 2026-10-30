# Reportes atmosféricos comprobados — #103 / #64

Continuación sobre #102, en la misma área de reportes de EnviroSense. No se agrega otra pantalla o tecnología. Laravel/Blade, Tabler 1.7.1 y la presentación recuperada se conservan. La generación corresponde a Analista/Administrador; Visualizador consulta sus reportes propios.

## Corrección de las escalas

Reportes ya consultaba `us_aqi`, pero lo mostraba con unidad AQI y sin identificar la escala. La ficha del mapa recuperada en #101 consulta el índice europeo del original. Ahora el selector, filas, resúmenes, gráficos y exportaciones distinguen:

- `aqi`: **Aire · escala de Estados Unidos**, unidad **USAQI**, proveedor `us_aqi`, fuente `open-meteo-cams-us`.
- `aqi_europeo`: **Aire · escala europea**, unidad **EAQI**, proveedor `european_aqi`, fuente `open-meteo-cams-eu`.

Se conserva la clave de entrada `aqi` del reporte atmosférico para compatibilidad. Las lecturas guardadas y simulaciones que usaban `aqi/AQI` mantienen su catálogo y origen; no se infiere una escala para esos datos anteriores. Una plantilla con ambas variables no cruza fuentes para cumplir una condición: las dos escalas se mantienen separadas. Los reportes guardados conservan exactamente sus datos y configuración. El formato PDF incorpora numeración y evita partir un resumen pequeño; descargar vuelve a presentar el corte guardado sin consultar proveedores.

La [documentación de Open-Meteo](https://open-meteo.com/en/docs/air-quality-api) define ambos índices por separado. No se convierten entre sí ni se compara un número de una escala con el de otra como si tuvieran el mismo significado. No se añaden recomendaciones sanitarias.

## Comprobación de lugar, fecha y producto

La petición solicita solo las horas necesarias, hasta 72. Para aire se fija `cams_global`, como en la ficha del mapa. El máximo sigue siendo nueve localidades y mil valores solicitados; quince variables disponibles, con la nueva escala europea. Una respuesta no puede atribuirse a otra localidad aunque su `location_id` esté en orden: se comprueban coordenadas finitas, celda próxima y zona UTC. La latitud/longitud de cada celda quedan en los metadatos privados del reporte junto con la huella de la respuesta. Las coordenadas del lugar elegido se mantienen en las filas; no se presentan como una estación.

Se validan horas exactas, orden, unidades y cantidades. Ceros y faltantes siguen separados. Las dos peticiones posibles —tiempo y aire— tienen doce segundos, tres para conectar, un límite de 1,5 MB durante y después de descargar y no siguen redirecciones ni reintentan. Una respuesta inválida no guarda un reporte parcial. La clave del proveedor, si se configura, no se conserva en el corte ni en sus archivos.

## Resultado real del 30/10/2026

Se realizó una consulta de Santa Cruz a las **00:43 de Bolivia**, para las **00:00, 01:00 y 02:00** del mismo día. Es un pronóstico de modelos: las horas futuras están identificadas por su fecha y por el tipo de reporte, no son lecturas de sensores.

- Temperatura: **20,9; 20,5; 20,3 °C**.
- Índice de Estados Unidos: **53; 54; 54 USAQI**.
- Índice europeo: **44; 41; 34 EAQI**.

Dos respuestas públicas correctas, nueve valores y ningún faltante. Se generó el PDF de cuatro páginas y un Excel con hojas Lecturas/Resumen. Un lector Python independiente contrastó cada valor y unidad contra la respuesta original y el Excel, comprobó los tres resúmenes y las huellas, y verificó que el PDF contiene ambas unidades y numeración. Se revisaron visualmente las cuatro páginas y las dos hojas. Generar los archivos y volver a presentar el mismo corte no hicieron consultas externas.

Evidencias: [reportes103](evidencias/reportes103/README.md). El usuario y la base utilizados para esta comprobación son temporales, en memoria. No se cambió la demo, su rol, su contraseña, sus lecturas ni sus reportes.

## Repetir en local

La opción por entorno de Reportes ya existía y una instalación nueva la conserva apagada:

```dotenv
GEOSUR_REPORTES_ATMOSFERA_HABILITADO=true
```

En este equipo se habilitó solo esa opción privada después de comprobar el proveedor. No se activaron correo, mensajería ni otros servicios. Una cuenta Analista/Administrador puede entrar en **Área de Reportes → Pronóstico Atmosférico**, elegir las localidades, variables y horas, y generar un corte.

Para una comprobación aislada, desde la raíz:

```powershell
./herramientas/php.ps1 herramientas/reportes/comprobar-atmosfera.php --consultar-proveedor --salida=tmp/captura-atmosfera-nueva
python herramientas/reportes/auditar-atmosfera.py tmp/captura-atmosfera-nueva
```

La carpeta de salida debe ser nueva y su padre debe existir. La orden de captura requiere la opción explícita y entorno local/testing; usa SQLite `:memory:`, rutas públicas fijas y ninguna clave. Solo crea una cuenta en esa base temporal. Guarda las dos respuestas, recibo, corte JSON, PDF y Excel. El auditor requiere `pypdf` y `openpyxl`; se utilizaron los lectores del runtime de documentos del equipo, sin añadir dependencias al servidor Laravel. Audita esta captura fija de temperatura y dos escalas, no un reporte arbitrario.

Se conservan los límites y permisos actuales. Este avance no implementa la capa raster continental de AQI, el histórico original o dispositivos reales; #62/#66 siguen abiertas. Flutter sigue con Jaziel. Railway y la promoción de M3 a main conservan su revisión pendiente.
