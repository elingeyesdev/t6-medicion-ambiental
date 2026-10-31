# Preparación del ajuste del Planner — #109

Apoyo de Josué para la planificación a cargo de Jaziel. Quedan preparados el contraste de M1, la relación con las tareas actuales y los datos que ambos deben confirmar para recalcular ProjectLibre.

## Referencia conservada

[referencia-v2.xml](referencia-v2.xml) conserva exactamente el intercambio local `Cronograma_GeoCruz_ProjectLibre_v2.xml`, preparado el 13/10 y relacionado con DOC-08. Su SHA-256 es `bb6e2f703b5a07f490a1924ae38549be54f5f67356c528af22c8487af56c8436`. El XML y el POD originales permanecen como antecedentes históricos.

La [extracción reproducible](referencia-extraida.json) contiene doce actividades A–L, recursos, duraciones, esfuerzo, hitos y precedencias del archivo. Su red sin nivelación tiene **30 jornadas técnicas**; D tiene una jornada de holgura. El calendario del archivo usa 240 minutos por jornada. La reserva histórica es de seis jornadas y el cierre original figura el 30/11.

Las 3–4 horas diarias de M1 eran una estimación pendiente de confirmar. El bloque 08:00–12:00 permite calcular el calendario técnico y no acredita la disponibilidad personal de hoy. La duración de una actividad, el esfuerzo de cada persona y el tiempo de espera se conservan como magnitudes distintas.

El XML exportado coloca M2 el 22/10 a las 08:00 aunque su predecesora E termina a las 12:00. El documento local de seguimiento señala que el POD se recalculó con M2 a las 12:00. Esta diferencia se conserva en `advertenciasExportacion`, para contrastarla en ProjectLibre. Las fechas del archivo son previstas; no equivalen a fechas de revisión o aceptación reales. [Contraste documental](CONTRASTE_M1.md).

## Qué cambió respecto de M1

M1 estimaba los cinco análisis ambientales con EnviroSense como referencia y un alcance territorial de Santa Cruz. La decisión posterior conserva **EnviroSense completo como software principal**, adaptado a Laravel/Blade, API compartida y Flutter, con GeoSur como nombre. Cuentas, sensores, datos, capas, simulaciones, reportes y avisos requieren también integración y revisión. El chatbot quedó retirado por decisión de Josué.

La [trazabilidad](trazabilidad.json) relaciona A–L con las tareas creadas. Conserva además el inventario de M3 y la captura de dependencias usada por #118 / PR #122, con su fecha y huella. Es un corte histórico del 31/10; una nueva revisión requiere otra captura.

- F: #35–#38 están cerradas en la captura; QA #39 sigue abierta y PR #88 mantiene cambios solicitados. La disponibilidad de código web no cierra F en ambas interfaces.
- G: #40 y #110 preparan el método; #111 conserva #39 y #110 como predecesoras. Después siguen API #112, web #113, Flutter #114 y contraste #115.
- H: #116 prepara la fuente/método; #117 es el avance parcial de M4 y espera #115 y #116. Sus tareas no equivalen a completar todo H.
- I: recuperar la consulta de aire de EnviroSense no completa automáticamente el análisis de calidad del aire previsto en el plan original.
- Entregas: #41 conserva M3 y #118 conserva M4. Los pendientes de recuperación no se trasladan ni se marcan terminados por crear el nuevo milestone.

Las issues desglosan el trabajo de G/H: sus checklist no agregan otra duración encima de la actividad padre. El alcance ampliado y lo que falta deben estimarse por paquete; las treinta jornadas originales no son una estimación actual del software completo.

## Preparación que puede avanzar en paralelo

- #109: confirmar capacidad, comparar la referencia y acordar las estimaciones restantes.
- #110: revisar caso, fechas, acceso y calidad; mantiene sus revisiones pendientes y la precedencia de #39 para procesar #111.
- #116: revisar la fuente forestal y sus límites; el procesamiento de #117 conserva la dependencia de #115.
- #118: ordenar fuentes, evidencias y pendientes; el paquete final espera sus dependencias.

La ejecución sigue F → G → H. Los acuerdos y comprobaciones preparatorios pueden adelantarse sin dar por implementado el análisis siguiente.

## Qué necesitamos confirmar juntos

[datos-por-confirmar.json](datos-por-confirmar.json) deja vacíos los datos todavía desconocidos:

1. Horas disponibles de Josué y Jaziel, calendario efectivo, período y ausencias/feriados.
2. Trabajo restante por paquete y persona, con estimación optimista, probable y pesimista en horas de esfuerzo. Incluir revisiones, integración y espera por datos.
3. Correspondencia de los pendientes ampliados con cada paquete, evitando contarlos dos veces.
4. Acuerdo de acceso y caso para los análisis; #110 documenta la baja calidad de las parejas ópticas y el requisito de acceso para la alternativa RTC.
5. Nueva versión de ProjectLibre con calendario, recursos, nivelación y fechas revisadas, conservando la referencia original.

La meta original M4 es 05/11 y la final 30/11. Falta el pronóstico para contrastar si caben y qué impacto tienen los pendientes. M4 no tiene fecha académica confirmada. Los campos del pronóstico siguen nulos hasta acordar capacidad y esfuerzo; un vacío no representa cero horas.

## Repetir la comprobación

Python 3.12, biblioteca estándar, desde la raíz:

```powershell
python herramientas/planificacion109/extraer.py --xml docs/planificacion109/referencia-v2.xml --salida tmp/planner-referencia-nueva.json
python -B -m unittest discover -s herramientas/planificacion109 -p 'test_*.py' -v
```

Crear `tmp/` si no existe y usar un archivo de salida nuevo. El lector no consulta red ni modifica XML/POD. Sus cinco pruebas contrastan la red real, distinguen esfuerzo de duración y detectan cambios del camino crítico, identificadores rotos y ciclos. El resultado matemático no nivela recursos ni incorpora ausencias, el nuevo alcance o estimaciones restantes.

Esta preparación adelanta la comparación y el análisis de precedencias de #109. Jaziel conserva la responsabilidad de planificación; quedan pendientes la estimación conjunta, el pronóstico, el nuevo POD y su revisión. La aceptación documental de [DOC-08](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/8) se conserva como antecedente de M1.
