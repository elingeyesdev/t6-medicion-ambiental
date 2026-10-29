# Revisión de sequías en móvil / PR #87

La consulta de sequías ya está conectada a la API compartida. Conserva el índice SPI-3, los valores faltantes y la distinción entre una muestra y el territorio completo. Las capturas del emulador de Jaziel están en la tarea #38.

Durante la revisión incorporé los ajustes aprobados del PR #84 y corregí estos casos:

- Salir mientras carga el catálogo ya no inicia otra consulta ni actualiza una pantalla cerrada. Una respuesta anterior tampoco reemplaza una consulta más reciente.
- Cambiar de provincia limpia el municipio elegido. El mes escrito se conserva y se valida antes de cerrar los filtros.
- La cobertura parcial se muestra junto al resumen; el aviso no se elimina de la información adicional. Un municipio sin datos no se presenta como libre de sequía.
- Una respuesta vacía o incompleta genera un error. El centro de una celda puede no tener municipio identificado, como permite el contrato; ese caso no rompe la consulta. Los ceros siguen siendo valores válidos.
- El mapa vuelve a centrar los resultados cuando se consulta otra vez. Los errores muestran un mensaje corto y el texto pequeño se hizo más legible.

Se agregaron pruebas de estos casos. El análisis, la suite Flutter y la compilación Android deben pasar en GitHub antes de aprobar este cambio. No hay SDK Flutter disponible en este equipo y no se presenta esta revisión como una ejecución local en emulador.

El contraste completo entre web y teléfono sigue en #39; las demás funciones móviles de EnviroSense permanecen en #48.
