# Actualización de la base del candidato M3 · #41

El 31/10 se incorporó `develop` (`cf37681`) al historial de `feature/41-evidencias-entrega-m3`. Sus cambios aceptados de ingestión (#91) y simulación/reportes (#92) ya estaban presentes en el candidato: el primer merge conservó exactamente su árbol de archivos.

Después se incorporó la actualización del PR #94 (`f5808e0`). El conflicto de rutas correspondía a la posición del mismo bloque de simulación continua. Se conservó la posición del candidato y quedaron una sola vez las rutas de simulación, avisos, push y cambio de clave, con sus permisos originales. El código de `web/` sigue coincidiendo con el candidato anterior; se añade el registro documental de #94.

La resolución de #94 pasó 67 pruebas PHP / 1762 comprobaciones, diez JavaScript y Pint de rutas. Para este candidato se verificó además la identidad del árbol de código, sintaxis y ocho comprobaciones de rutas/protecciones. Esas comprobaciones no equivalen a repetir toda la suite del candidato ni a probar proveedores reales.

Los PR #98, #100, #102, #104, #106, #108 y #121 mantienen su revisión separada en la cadena. La actualización del padre conserva sus propuestas sin integrarlas hacia atrás. Los recuentos y capturas anteriores de `entrega-m3/` corresponden al corte que allí se identifica; cada ampliación conserva su evidencia propia.

La revisión de Jaziel del PR #96 continúa pendiente. QA #39 y método #40 siguen abiertos; esta actualización de base no acredita M3 terminado ni autoriza promoverlo a main.
