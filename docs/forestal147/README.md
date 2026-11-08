# Resumen anual forestal por período

Avance de #147 desde la captura revisada en #116. El cálculo funciona sin Internet y vuelve a verificar los cuatro originales y sus huellas antes de seleccionar los años. Todavía faltan el criterio aprobado, el piloto de #117 y la capa espacial para completar el producto forestal. No se añade una pantalla ni una ruta pública.

## Repetir el cálculo

Desde la raíz del repositorio, con Python 3.12 o posterior:

```powershell
python herramientas/forestal/ventana.py --directorio docs/forestal116/evidencias/contraste-20261103/acceso --desde 2024 --hasta 2025 --salida tmp/forestal-2024-2025.json
python -m unittest discover -s herramientas/forestal -p 'test_*.py' -v
```

La carpeta de salida debe existir y el archivo debe ser nuevo. Sin --salida, el resultado se imprime en pantalla. No se sobrescriben archivos ni se consultan proveedores. La [salida guardada](evidencias/2024-2025.json) permite repetir este caso desde los mismos originales del 03/11; no es una consulta nueva al proveedor del 08/11.

## Qué conserva y qué calcula

Los años inicial y final están incluidos. Solo se admiten años publicados por la versión de la captura; la disponibilidad completa de fuente.anios se conserva aunque se solicite un intervalo menor. Cambiar el intervalo recalcula el subtotal y el total desde las filas elegidas, sin reutilizar el total completo ni convertir las hectáreas a kilómetros cuadrados.

El cálculo usa decimales exactos y conserva las cadenas de cada año. Cero continúa siendo un dato válido. Un año ausente o nulo queda como no_informado. Si falta un año del intervalo, total_periodo_ha queda nulo y solo se presenta el subtotal conocido. Si ninguno tiene datos, también el subtotal queda nulo.

Se conservan la identidad GADM del proveedor, versión, criterio candidato, atribución y límites. La cobertura espacial continúa desconocida y la capa continúa nula. El resumen no se convierte en un resultado municipal, no acredita cobertura completa ni identifica la causa de la pérdida. El hash de los recibos y las huellas de los cuatro archivos quedan en reproduccion.

Esta rama depende del PR #119 y prepara el cálculo temporal de #147. No da por terminados #117, #147 o #148 ni modifica Flutter.

## Verificación

Pasaron las 32 pruebas de fuente y ventana temporal: 13 regresiones existentes y 19 del cálculo nuevo. Se comprobaron cero, faltantes, años no publicados, captura alterada, cambio de recibos durante la lectura, suma con más de 28 cifras, repetición de la evidencia y protección de archivos existentes. [Salida local](evidencias/pruebas.txt). El resultado de GitHub se comprueba por separado en el PR.
