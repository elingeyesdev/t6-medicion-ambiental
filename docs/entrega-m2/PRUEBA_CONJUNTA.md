# Recorrido conjunto de web y Flutter · QA-01 / #21

**Guía de repetición. QA-01 fue revisado e integrado el 21/10/2026 mediante PR #33.** Los resultados, matriz y límites están en [la revisión QA-01](../qa21/README.md). Los pasos siguientes conservan el plan de comprobación; no acreditan por sí solos una ejecución adicional.

Responsable de #21: Jaziel. Revisión: Josué. Usar el mismo backend integrado en `develop`; no confundir una captura de la web en pantalla pequeña con Flutter.

## Preparar una sesión comparable

1. Registrar fecha UTC de ejecución, commit del backend y commit/APK de Flutter, dispositivo o emulador y origen de la API. La clave NASA permanece solo en el backend.
2. Elegir un intervalo pasado de hasta 168 horas, con horas UTC explícitas, dentro de la disponibilidad real del proveedor. Escribir los mismos valores de departamento, provincia, municipio y confianza en ambas interfaces.
3. Usar la misma `versionConjunto` devuelta por el backend mientras no expire. Si una interfaz no permite compartir esa versión, documentar la limitación: dos descargas independientes no prueban identidad del conjunto. La instantánea vence a los 15 minutos; reiniciar ambas consultas al expirar.
4. Guardar capturas y respuestas sin credenciales. Para datos externos, anotar adquisición y recuperación; para respuestas controladas, escribir explícitamente **fixture de prueba** y su origen.

## Casos que deben ejecutarse

- **Consulta válida:** mismo territorio, período y confianza. Contrastar fuente, cobertura, naturaleza, total del conjunto, faltantes y FRP con su unidad; conservar los IDs completos de una misma página y su versión. Un foco térmico no confirma incendio.
- **Territorio:** cambiar provincia y comprobar que no queda aplicado un municipio incompatible. Contrastar una consulta municipal con su provincia padre.
- **Lista → detalle → regreso:** seleccionar el mismo ID en ambas interfaces; comprobar territorio, período, valores y procedencia. Volver debe conservar filtros, página y contexto.
- **Paginación:** conservar filtros y `versionConjunto`; comprobar IDs, límites de página y que el resumen sigue representando el conjunto. Los grupos del mapa web cuentan registros de su página, no de todo Santa Cruz.
- **Parámetros inválidos:** Desde posterior a Hasta, intervalo superior a 168 horas y provincia `BO0702` con municipio `BO070101`. Comprobar el rechazo y la explicación sin sustituir los resultados anteriores por datos ficticios.
- **Vacío:** respuesta correcta sin registros, identificando si es externa o un fixture. No confundir cero resultados con ausencia del fenómeno; FRP desconocida no se convierte en cero.
- **Cobertura y fallo:** comprobar mensajes, reintento y procedencia; usar fixtures identificados para condiciones que no puedan reproducirse con el proveedor real. No presentar una condición simulada como evidencia externa.
- **Respuesta anterior y expiración:** conservar visible su fecha/ámbito cuando proceda y comprobar el manejo de 409 `CONJUNTO_CAMBIADO` al expirar la instantánea. No mezclar páginas de versiones diferentes.

## Evidencia mínima por caso

Registrar: caso, fecha, versiones de código, parámetros, `versionConjunto`, origen externo/fixture, resultado esperado, resultado observado en web, resultado observado en Flutter, enlaces de capturas/respuestas, defecto y verificación de su corrección si corresponde.

El registro ejecutado está en [la matriz de QA](../qa21/matriz.json). #22 incorpora esos resultados y queda pendiente de revisión de Jaziel. Repetir esta guía en otra versión requiere registrar resultados reales; esta guía no cambia Planner ni la fecha prevista del 22/10/2026.
