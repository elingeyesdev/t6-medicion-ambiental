# #109 - Contraste de la planificación documental M1

DOC-08 fue aceptado como **planificación documental de M1** el 15/10/2026.
La aceptación conserva A-L en el cronograma y permite crear las tarjetas de
desarrollo después de la validación académica de la EDT; no acredita su creación,
implementación, aprobación del docente ni fechas académicas futuras. Este
contraste recupera esa referencia histórica; no propone un cronograma nuevo.

## Fuentes verificadas

- [PDF original de DOC-08](https://github.com/user-attachments/files/33464979/DOC-08_Planner_Corregido_GeoCruz.pdf),
  enlazado por Jazielle en su [comentario del 14/10/2026](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/8#issuecomment-6180824761).
  Cuatro páginas, 509091 bytes. SHA-256:
  `288207d58f53c286d406bf04d62ae272b2807e46c1fdc8a106e11ea96f60034d`.
- [Aceptación final del 15/10/2026 en #8](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/8#issuecomment-6197625053),
  de JosuePadillaUnivalle. Distingue expresamente el cierre documental de la
  ejecución de A-L y de la aprobación docente.
- [XML v2 conservado](referencia-v2.xml).
  SHA-256: `bb6e2f703b5a07f490a1924ae38549be54f5f67356c528af22c8487af56c8436`.
- Nota local de ProjectLibre `14_CRONOGRAMA_PROJECTLIBRE.md`, conservada en el archivo documental de Josué,
  revisión del 13/10/2026 y actualización de dependencias de esa fecha.
  SHA-256: `f8ec3652670689c75a1212714261b37fcf91b88a05cdb4da1327cd3aeafed5a3`.
- `Cronograma_GeoCruz_v2.pod` y su copia `06_Cronograma_ProjectLibre.pod` del paquete M1 de Josué:
  ambos tienen SHA-256
  `89b2c7fb578898546f2d8395b97c79f4087f96df41b409959b9d3fb39c36385a`.
  Se leyeron exclusivamente para calcular el hash, sin edición, ejecución ni
  deserialización Java. La igualdad de bytes no verifica su calendario interno.

Se utilizó el runtime bundled `26.1026.10807`: extracción con `pypdf` y renderizado
con Poppler a 140 dpi de las páginas 1-4 del PDF original. Se inspeccionaron las
cuatro imágenes completas: portada; actividades; ruta/reserva/revisiones;
restricciones y evidencias. PDF, PNG y extracción quedan como temporales privados
en `tmp/pdfs/contraste-m1-20261031/` del nuevo worktree, ignorados por Git.

## A-L: coincidencias de PDF y XML

La tabla de la página 2 coincide con las tareas XML UID 2-13 en actividad,
duración, fechas, precedencias y responsables. Todas las fechas siguientes son
**programación histórica de 2026**, no fechas reales de ejecución.

- **A — Alcance y diagnóstico:** 1 jornadas; 12/10 / 12/10; predecesora Ninguna; Ambos.
- **B — Casos de uso, diseño y contrato:** 2 jornadas; 13/10 / 14/10; predecesora A; Ambos.
- **C — Laravel/Blade, API y territorios:** 3 jornadas; 15/10 / 19/10; predecesora B; Josué.
- **D — Flutter y navegación:** 2 jornadas; 15/10 / 16/10; predecesora B; Jaziel.
- **E — Focos de calor, ambas interfaces:** 3 jornadas; 20/10 / 22/10; predecesora C y D; Ambos.
- **F — Sequías, ambas interfaces:** 3 jornadas; 23/10 / 27/10; predecesora E; Ambos.
- **G — Inundaciones, ambas interfaces:** 4 jornadas; 28/10 / 02/11; predecesora F; Ambos.
- **H — Deforestación, ambas interfaces:** 4 jornadas; 03/11 / 06/11; predecesora G; Ambos.
- **I — Aire, ambas interfaces:** 3 jornadas; 09/11 / 11/11; predecesora H; Ambos.
- **J — Resumen y coherencia:** 2 jornadas; 12/11 / 13/11; predecesora I; Ambos.
- **K — Pruebas y correcciones:** 3 jornadas; 16/11 / 18/11; predecesora J; Ambos.
- **L — Manuales, evidencias y defensa:** 2 jornadas; 19/11 / 20/11; predecesora K; Ambos.

PDF, XML y nota conservan la ruta A-B-C-E-F-G-H-I-J-K-L de **30 jornadas**,
con C/D en paralelo y una jornada de holgura CPM de D. La secuencia entre módulos
organiza capacidad; no establece dependencia física entre fenómenos ambientales.

La meta histórica es **30/11/2026**: trabajo principal hasta el 20/11 y **seis
jornadas de reserva del 23 al 30/11**, independientes de la holgura de D y de
esfuerzo adicional medido. El resumen XML abarca 36 jornadas. El calendario XML
usa 240 minutos diarios, lunes a viernes, 08:00-12:00; la nota lo identifica como
**provisional de cálculo**. No confirma disponibilidad ni horario actual de nadie.

La página 3 y los hitos XML conservan los jueves previstos 15, 22 y 29/10;
05, 12, 19 y 26/11. Son revisiones con evidencia y resultados parciales: mencionar
agua o forestal antes de terminar G/H no equivale a declararlos implementados.
Estas fechas históricas no confirman vencimientos académicos actuales de M3/M4.

## Discrepancias y límites

1. **Exportación de M2:** UID 16 tiene `Start = Finish = 2026-10-22T08:00:00`,
   con predecesora UID 6, tipo FS (`Type=1`) y retardo cero. E termina
   `2026-10-22T12:00:00`: el hito exportado antecede cuatro horas a su predecesora.
   Es la única inversión encontrada al comprobar todos los vínculos FS sin
   retardo del XML. La nota indica que el POD fue recalculado y M2 quedó a las
   12:00 del mismo 22/10. Se registra como **discrepancia de exportación entre XML
   y nota**, sin corroborar el interior del POD ni corregir las fuentes. No es
   evidencia de una fecha real de entrega falsa o cambiada; el PDF solo da el día.
2. **Prioridades:** el PDF asigna Media a D y Alta al resto de A-L; el XML
   exporta `Priority=500` para las doce, perdiendo esa distinción.
3. **Confirmación del plazo:** la nota de UID 22 llama al 30/11 fecha límite
   confirmada; el PDF, páginas 2-3, la presenta como meta sujeta a avance y revisión.
   Esa etiqueta XML no aporta evidencia de confirmación académica futura.
4. **Identificación nominal:** la portada del PDF dice Daniel Diaz; sus páginas
   2 y 4 y los recursos XML identifican a Jaziel Díaz. Se deja constancia de la
   diferencia sin inferir equivalencia de identidad ni modificar el PDF aceptado.
5. **Estados históricos:** la nota del 13/10 y la página 4 del PDF conservan
   textos de aprobación pendiente/propuesta para revisión. La aceptación
   documental posterior del 15/10 resuelve ese estado para M1; no es aceptación
   académica de todo el sistema ni acredita implementación posterior.

Para #109 esta referencia permite contrastar alcance y supuestos. Actualizar
fechas requiere avance observado, disponibilidad y capacidad acordadas con ambos
integrantes, incluido Jaziel. No se recalculó el cronograma ni se dedujeron horas
actuales, entregas efectivas o nuevas aceptaciones.
