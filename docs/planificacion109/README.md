# Preparación del ajuste del Planner — #109

Apoyo de Josué para la planificación a cargo de Jaziel. Quedan preparados el contraste de M1, la relación con las tareas actuales y los datos que ambos deben confirmar para recalcular ProjectLibre.

## Referencia conservada

[referencia-v2.xml](referencia-v2.xml) conserva exactamente el intercambio local `Cronograma_GeoCruz_ProjectLibre_v2.xml`, preparado el 13/10 y relacionado con DOC-08. Su SHA-256 es `bb6e2f703b5a07f490a1924ae38549be54f5f67356c528af22c8487af56c8436`. El XML y el POD originales permanecen como antecedentes históricos.

La [extracción reproducible](referencia-extraida.json) contiene doce actividades A–L, recursos, duraciones, esfuerzo, hitos y precedencias del archivo. Su red sin nivelación tiene **30 jornadas técnicas**; D tiene una jornada de holgura. El calendario del archivo usa 240 minutos por jornada. La reserva histórica es de seis jornadas y el cierre original figura el 30/11.

Las 3–4 horas diarias de M1 eran una estimación pendiente de confirmar. El bloque 08:00–12:00 permite calcular el calendario técnico y no acredita la disponibilidad personal de hoy. La duración de una actividad, el esfuerzo de cada persona y el tiempo de espera se conservan como magnitudes distintas.

El XML exportado coloca M2 el 22/10 a las 08:00 aunque su predecesora E termina a las 12:00. El documento local de seguimiento señala que el POD se recalculó con M2 a las 12:00. Esta diferencia se conserva en `advertenciasExportacion`, para contrastarla en ProjectLibre. Las fechas del archivo son previstas; no equivalen a fechas de revisión o aceptación reales. [Contraste documental](CONTRASTE_M1.md).

## Qué cambió respecto de M1

M1 estimaba los cinco análisis ambientales con EnviroSense como referencia y un alcance territorial de Santa Cruz. La decisión posterior conserva **EnviroSense completo como software principal**, adaptado a Laravel/Blade, API compartida y Flutter, con GeoSur como nombre. Cuentas, sensores, datos, capas, simulaciones, reportes y avisos requieren también integración y revisión. El chatbot quedó retirado por decisión de Josué.

La [trazabilidad](trazabilidad.json) conserva el inventario y las dependencias del 31/10 usados por #118 / PR #122, con su fecha y huella. El bloque `revisionDependencias` añade el estado del 03/11 sin reescribir esa captura histórica. La base es [develop a1ad8e7](https://github.com/elingeyesdev/t6-medicion-ambiental/commit/a1ad8e71f7a7f8cedf4f013c72e6ec4326593aef): se comprobaron localmente el merge de #96 y la presencia del HEAD de preparación de #46 en su historial. Los estados se contrastaron con la captura REST de [M4 del 03/11](https://github.com/elingeyesdev/t6-medicion-ambiental/blob/c5c6bd0061fe499da607e393b97ff9b4cf79cc80/docs/entrega-m4/evidencias/2026-11-03/snapshot.json). La disponibilidad diaria fue confirmada por Josué en esta conversación.

- **F — sequías:** #39 está cerrada y PR #88 integrado. Esa precedencia de #111 ya está cubierta; se conserva el vínculo histórico.
- **4.3 G — agua e inundaciones:** #46 integra preparación, pero #40 sigue abierta. #110 / PR #120 conserva la decisión sobre método, evento, referencia, acceso y calidad; #111 sigue pendiente de #110. Después vienen API #112, web #113 y Flutter #114, y el contraste #115. La base web #47 está incorporada por #96; no se vuelve a pedir su integración como trabajo restante de #113.
- **4.4 H — cobertura forestal:** #116 / PR #119 propone fuente y método. #117 es un avance parcial y espera #115 y #116; no completa todo H ni repite trabajo de G.
- **I — aire:** recuperar la consulta de EnviroSense no completa automáticamente el análisis previsto en el plan original.
- **Entregas:** #96 ya integró el paquete M3 de #41; quedan abiertas en M3 **#40, #49 y #89**. #118 conserva M4 y sus dependencias #109, #115 y #117; el insumo M3 de #41 ya está integrado. Esto no acredita aceptación académica ni cierra los pendientes de M3.

En M4 siguen pendientes de revisión **fuente #119 (#116), método #120 (#110), evidencias #122 (#118) y planificación #123 (#109)**. Integrar M3 y la preparación de #46 no aprueba estos cuatro PR ni implementa G/H.

Las issues desglosan **4.3 G** y **4.4 H**: cargar el esfuerzo restante una sola vez por paquete. Si se estima por subtareas, consolidarlas en el padre; no sumar además la duración histórica del padre. El alcance ampliado requiere su propia estimación y las treinta jornadas originales no estiman el software actual.

## Preparación que puede avanzar en paralelo

- #109: completar calendario y período, comparar la referencia y acordar estimaciones; Jaziel es responsable de planificación y Josué presta apoyo.
- #110: revisar caso, referencia, acceso y calidad con Jaziel; #39 ya está cubierta, pero #111 espera el acuerdo de #110 y el pendiente de #40 no se da por cerrado.
- #116: revisar fuente y límites forestales; #117 conserva #115 y #116.
- #118: ordenar fuentes y evidencias con el insumo M3 integrado; el paquete final espera #109, #115 y #117.

El orden F → G → H se conserva como referencia y propuesta pendiente de revisión con Jaziel. La preparación puede avanzar en paralelo; todavía no hay un nuevo orden aprobado, nivelación ni pronóstico de fechas.

## Capacidad declarada y plantilla de carga

El usuario confirmó **2–3 horas al día por persona**, tanto para Josué como para Jaziel. La suma es **4–6 horas-persona** en un día en que ambos estén disponibles; no equivale a 4–6 horas de duración calendario. No se conocen los días de trabajo, la coincidencia de horarios, la fecha de inicio ni el período de vigencia; no se calculan horas semanales ni fechas del Planner. Esta capacidad declarada tampoco estima el esfuerzo restante.

[datos-por-confirmar.json](datos-por-confirmar.json) registra el intervalo confirmado de cada persona y ofrece una fila por actividad A–L para cargar lo que falta. Los responsables propuestos y las precedencias de actividad proceden de la referencia; deben revisarse con Jaziel. Las dependencias de issues se contrastan con `revisionDependencias` de [trazabilidad.json](trazabilidad.json).

Para completar la carga:

1. Confirmar días/horarios, fecha de inicio, período y ausencias/feriados de cada integrante.
2. Describir solo el trabajo restante de cada paquete y confirmar sus responsables y dependencias. Registrar O/M/P por persona en horas de esfuerzo y su fuente; los campos siguen nulos, nunca cero por falta de datos.
3. Incluir esfuerzo de revisión e integración; registrar aparte las esperas externas. En 4.3 G y 4.4 H, consolidar las subtareas una sola vez, sin añadir la duración histórica del padre.
4. Asignar los pendientes M3 #40, #49 y #89 a sus paquetes antes de estimarlos, sin trasladarlos ni duplicarlos en M4. Revisar con Jaziel el caso y acceso de #110: calidad óptica baja y acceso RTC pendiente.
5. Acordar el orden y las estimaciones; después preparar otra versión de ProjectLibre con recursos, calendario, nivelación y fechas revisadas, conservando la referencia original.

La meta original M4 es 05/11 y la final 30/11. Falta el pronóstico para contrastar si caben y qué impacto tienen los pendientes. M4 no tiene fecha académica confirmada. Los campos del pronóstico siguen nulos hasta acordar capacidad y esfuerzo; un vacío no representa cero horas.

## Repetir la comprobación

Python 3.12 o superior, biblioteca estándar, desde la raíz:

```powershell
python herramientas/planificacion109/extraer.py --xml docs/planificacion109/referencia-v2.xml --salida tmp/planner-referencia-nueva.json
python -B -m unittest discover -s herramientas/planificacion109 -p 'test_*.py' -v
```

Crear `tmp/` si no existe y usar un archivo de salida nuevo. El lector no consulta red ni modifica XML/POD. Sus cinco pruebas contrastan la red real, distinguen esfuerzo de duración y detectan cambios del camino crítico, identificadores rotos y ciclos. El resultado matemático no nivela recursos ni incorpora ausencias, el nuevo alcance o estimaciones restantes.

Esta preparación adelanta la comparación y el análisis de precedencias de #109. Jaziel conserva la responsabilidad de planificación, con apoyo de Josué; quedan pendientes la aprobación del orden propuesto, la estimación conjunta, el pronóstico, el nuevo POD y su revisión. La aceptación documental de [DOC-08](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/8) se conserva como antecedente de M1.
