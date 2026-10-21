# QA-01 - Resultado de revisión web y Flutter

Fecha: 21/10/2026. Implementación y capturas de recorrido: Jaziel Díaz. Revisión y ampliación reproducible de pruebas: Josué Padilla. Seguimiento: #21 / PR #33.

## Resultado observado

Se revisaron las cinco capturas aportadas por Jaziel, que documentan una consulta externa válida de Warnes del 14/10/2026 00:00 al 21/10/2026 00:00 Bolivia. Web y móvil muestran una detección, el mismo identificador completo, confianza nominal, fecha observada 20/10/2026 13:32 Bolivia, FRP 3,43 MW y NASA FIRMS / VIIRS_SNPP_NRT. Las imágenes están en [la carpeta original](../QA-01%20-%20Verificar%20integraci%C3%B3n%20web%20m%C3%B3vil%20y%20fuente%20de%20focos%20de%20calor/). No se sustituyen ni se atribuyen al revisor.

Se añadió una comparación de ambos clientes sobre **la misma captura histórica de API**, usando sus controladores y parsers reales con transporte controlado. Ambos procesaron 25 registros de la misma página y conservaron exactamente IDs, coordenadas, atributos/unidades, fechas, territorio, faltantes, naturaleza, método, fuentes, contexto de consulta, resumen, cobertura, advertencias y paginación/versionConjunto. Se ejecutaron **11 casos en cada cliente**.

Resultado verificable de esa versión (`c3351df79af00f3733823368b6b3842e8a753c69`): [CI de comparación](https://github.com/elingeyesdev/t6-medicion-ambiental/actions/runs/39681981989). [Informe de comparación](resultados/comparacion.json), [salida web](resultados/web.json), [salida Flutter](resultados/movil.json) y [matriz de esperado/observado](matriz.json).

La suite web completa ejecutó 32 pruebas JavaScript. La CI de Laravel en PHP 8.2 y 8.3 terminó correctamente con 35 casos y 263 aserciones; emitió avisos de entorno de pruebas, que no se presentan como ausencia total de advertencias. [Registro](https://github.com/elingeyesdev/t6-medicion-ambiental/actions/runs/39681982057). La suite Flutter y el APK se verifican por su workflow propio; la revisión final del PR enlaza el resultado definitivo.

## Matriz resumida

1. **Equivalencia:** esperado, conservar el mismo conjunto/página y contexto. Observado, 25 registros y metadatos coinciden campo a campo entre salidas independientes de web y Flutter.
2. **Paginación:** esperado, segunda página de la misma versión sin cambiar resumen. Observado, IDs 26-50 de la captura, token reutilizado y resumen conservado en ambos clientes.
3. **Vacío válido:** esperado, cero registros y cobertura desconocida sin error ni datos inventados. Observado, ambas respuestas se conservan como válidas y vacías.
4. **Sin cobertura:** esperado, estado diferente de un fallo o una ausencia demostrada. Observado, ambos conservan `sin_cobertura` y cero registros; la regresión de interfaz móvil de #32 verifica el mensaje explícito.
5. **Cobertura parcial:** esperado, conservar esa limitación aunque no existan registros. Observado, ambos conservan `parcial`; la regresión móvil verifica la prioridad del aviso.
6. **Fallo y reintento:** esperado, conservar la respuesta anterior durante un 503 y recuperar la consulta al reintentar. Observado, ambos retienen su objeto anterior y completan el reintento sin sustituciones ficticias.
7. **Expiración:** esperado, rechazar 409 al cambiar de página y reiniciar sin token vencido. Observado, ambos conservan página anterior y reinician sin `versionConjunto`.
8. **Respuesta atrasada:** esperado, una respuesta antigua no sobrescribe la consulta más reciente. Observado, ambos conservan la consulta identificada como más reciente. La prueba Flutter espera explícitamente el inicio de las dos solicitudes antes de resolverlas.
9. **Período inválido:** esperado, rechazar intervalo nulo, invertido y superior a 168 horas. Observado, ambos rechazan los tres casos; Flutter no emite peticiones.
10. **Territorio incompatible:** esperado, rechazar BO0702 con municipio BO070101. Observado, Flutter lo rechaza antes de consultar; web conserva el rechazo 422 del backend controlado. La suite de API comprueba la regla del servidor.
11. **Cero y faltantes:** esperado, distinguir FRP 0 de FRP ausente. Observado, ambos conservan cero, nulo y el faltante explícito sin convertir el dato ausente a cero.

## Alcance de la evidencia

Las capturas muestran la ejecución externa realizada por Jaziel. Los escenarios automatizados son **replay/fixtures**, incluida la reutilización controlada de una respuesta externa capturada el 16/10/2026; no son nuevas consultas NASA ni una ejecución de todos los estados en un emulador por Josué. Los casos derivados alteran de manera explícita cobertura, estado HTTP o datos de prueba. No describen mediciones ambientales nuevas.

Las capturas por sí solas no demuestran que se compartiera la misma `versionConjunto` en ambas solicitudes. La comparación automatizada sí usa una fuente fija idéntica y comprueba contexto/token y los campos conservados. No se extrapola esa equivalencia a cualquier consulta futura, al rendimiento, a precisión geográfica o a otros módulos.

El formulario/listado/detalle se respalda en las capturas originales y las pruebas de navegación/regresiones de MOV-02. La aprobación técnica de QA-01 no sustituye la revisión de Jaziel del paquete ENT-02 ni la aprobación del ingeniero para `main`.

## Cómo repetir

Desde la raíz del repositorio con Node 22+, Flutter 3.38.3 y Python 3:

```powershell
cd web
node --test tests/Js/qa21-compartida.test.mjs
cd ../movil
flutter pub get --enforce-lockfile
flutter test test/qa21_compartida_test.dart --reporter expanded
cd ..
python herramientas/verificar_qa21.py
```

Los resultados locales quedan en `tmp/qa21/`, excluido de Git. El workflow `qa21.yml` repite esos pasos y adjunta sus JSON. La igualdad se comprueba a partir de las salidas ejecutadas; no se copió el resultado web para simular una ejecución móvil.
