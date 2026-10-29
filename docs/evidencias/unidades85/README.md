# Comprobación de unidades #85

Se revisó el selector original y se recuperaron sus opciones válidas de temperatura y velocidad. No se ejecutó ni modificó la copia de EnviroSense. `origen.json` registra los archivos usados. Los controles y el diseño siguen Tabler 1.7.1.

Las pruebas específicas cubren conversión, diferencias, cero, faltantes, evitar dobles conversiones, permisos, aislamiento entre cuentas, actualización parcial, persistencia y que las lecturas/API conserven sus valores base. La suite completa y JavaScript se registran en `resultado-pruebas.txt`.

En el navegador local se usó la cuenta demo con su rol Visualizador. Se fijó Buenos Aires (23,6 °C) y se seleccionó Santa Cruz (30,5 °C), usando únicamente las lecturas simuladas de Demostración ya existentes. Al elegir Fahrenheit se mostraron 74,5 °F, 86,9 °F y una diferencia de +12,4 °F. Se conservaron la referencia, las fechas y las ubicaciones A/B; captura `comparacion-fahrenheit.png`.

Las comprobaciones de pronóstico se distinguen de esos datos simulados y no se insertan en el historial. No se publican la base local, el respaldo, tokens ni configuraciones privadas. Los controles móviles, capas raster y conexiones externas siguen siendo trabajo pendiente.

También se comprobó Kelvin/mph sobre el mismo pronóstico ya recibido, incluido el selector y la escala del gráfico horario. Al abrir Mediciones se conservaron Kelvin, el resumen y los registros convertidos. El cierre de pruebas se hizo el 29/10, con 379 pruebas Laravel y 73 JavaScript correctas.
