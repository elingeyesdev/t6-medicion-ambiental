# Unidades de medida de EnviroSense

Seguimiento #85, relacionado con #61, #62 y #66. Se recupera el selector de `useUnidades.js`, `utils/unidades.js` y `ControlPanel.jsx` del referente, adaptado a Laravel/Blade y JavaScript. Tabler 1.7.1 se conserva.

## Cómo usarlo

En el mapa, abrir **Unidades de medida**, elegir temperatura y viento y pulsar **Guardar unidades**. Los valores y las comparaciones cambian sin perder el punto ni la referencia fijada. La misma elección aparece al volver a ingresar y en Mi cuenta. Mediciones y el resultado de Simulaciones tienen el mismo selector.

Temperatura admite Celsius, Fahrenheit y Kelvin; viento admite kilómetros por hora, metros por segundo y millas por hora. El pronóstico aplica la elección a las tarjetas, temperaturas diarias, tabla por hora, selector de variable y escala del gráfico. Cambiar la unidad redibuja la respuesta ya recibida; no crea una consulta meteorológica nueva.

En Mediciones cambian registros, resumen, título y escala del gráfico. En Simulaciones cambian los valores mostrados y su resumen, manteniendo el recorrido. Los formularios de carga y configuración conservan la unidad que indican. Los reportes ya guardados, sus condiciones y archivos PDF/Excel conservan el corte y las unidades con las que se generaron; no se reescriben al cambiar una preferencia.

## Datos y conversiones

El catálogo compartido está en `compartido/unidades/presentacion.json`. Laravel y JavaScript leen los mismos coeficientes. Se usa la milla internacional exacta de 1,609344 km, ajustando el redondeo del factor del referente. Las diferencias de temperatura aplican la escala, sin desplazamiento: 2 °C de diferencia son 3,6 °F; en Kelvin la diferencia conserva el mismo valor que en Celsius. El redondeo ocurre al mostrar, no antes de comparar.

El cero es válido. Un faltante, una cadena vacía o un valor no finito no se convierte en cero. Las respuestas ambientales, filtros, alertas y lecturas guardadas mantienen sus unidades base. Una respuesta que ya indica otra unidad no se vuelve a convertir.

El referente ofrecía transformar AQI en una concentración supuesta de PM2.5. No se reproduce esa opción como cambio de unidad: el índice general puede corresponder a otro contaminante y no identifica por sí solo esa concentración. Se conservan el índice recibido y su unidad. Esta corrección no agrega un método ambiental nuevo.

Fuentes comprobadas el 28/10/2026: [NIST, temperatura](https://www.nist.gov/pml/owm/si-units-temperature), [NIST, factores de conversión](https://www.nist.gov/pml/special-publication-811/nist-guide-si-appendix-b-conversion-factors/nist-guide-si-appendix-b8) y [EPA, índice general y contaminante principal](https://www.epa.gov/outdoor-air-quality-data/about-airdata-reports). El registro de archivos originales está en `docs/evidencias/unidades85/origen.json`.

## Cuenta y API

La preferencia pertenece a la cuenta autenticada; no al navegador ni a otra persona. Una actualización parcial conserva la otra unidad, la ubicación y los avisos. Cuenta suspendida y token sin capacidad no pueden modificarla. La migración `2026_10_28_000007_guardar_unidades_presentacion.php` añade una columna nullable; las cuentas existentes continúan con °C y km/h.

Flutter puede consultar y guardar la misma preferencia; contrato en [compartido/api/unidades-cuenta.md](../compartido/api/unidades-cuenta.md). Todavía necesita sus controles y comprobación con Jaziel. Esta recuperación tampoco completa las capas raster, MQTT, ETL ni los canales externos del original. El chatbot sigue fuera por decisión de Josué.
