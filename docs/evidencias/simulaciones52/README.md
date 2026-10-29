# Simulaciones #52: comprobación en navegador

Cuenta local temporal con rol Analista, sin promover la cuenta demo. Se creó un escenario de temperatura con Santa Cruz y Buenos Aires, distintas intensidades, un día y lecturas cada seis horas. El mapa muestra ambas zonas, el gráfico compara sus cinco pasos y los valores cambian al reproducir. Se comprobó que termina y conserva la sesión al volver a abrirla; también se revisó reinicio.

`comparacion-navegador.png` muestra la sesión calculada. Los resultados son simulados; no se publicaron sus lecturas en el almacenamiento de mediciones. Esa publicación, permisos, revisión de estado y fechas futuras se verifican en las pruebas aisladas.

La misma sesión se usó para crear una plantilla y un reporte con temperaturas mayores a 30 °C: cuatro de las diez lecturas originales cumplen. Se probó el cambio de serie y las descargas PDF/Excel desde la interfaz. Evidencia en `../reportes64/`.

La cuenta temporal se desactiva al cerrar esta revisión, sin borrar sus pruebas ni cambiar el rol Visualizador de la cuenta demo. La recuperación completa todavía incluye el modo continuo global, sockets, Flutter y revisión del compañero.
