# Evidencia de SMTP local real · #65 / fix #132 WEB

Rama actual `fix/132-rechazo-smtp`, renombrada desde `test/65-smtp-local`, base `b994b643d1b9086abd695053eb3a27030ed238b3`. Fecha/hora, versiones y hashes de las fuentes en [resultado.json](resultado.json).

- [phpunit.txt](phpunit.txt) y [JUnit](phpunit.xml): **8 pruebas / 345 comprobaciones**, sin fallos, errores ni omitidos.
- [smtp.json](smtp.json): aceptación normal 250; RCPT 550 sin DATA; DATA sin respuesta final; RCPT 450 sin DATA; un DATA aceptado con 250 seguido de rechazo 554 en la conexión del reinicio. Se capturaron tres mensajes; solo dos recibieron aceptación SMTP. La aplicación conserva incierto para el aceptado seguido de fallo al reconectar, sin repetirlo.
- 550 de la transacción: **fallido / PROVEEDOR_RECHAZO**. Timeout, 450 y 554 posterior a aceptación: **incierto / TRANSPORTE_SIN_CONFIRMACION**. Ninguno se reenvía al reprocesar.
- Consentimiento ausente, baja y cambio de correo: cero conexiones SMTP en cada caso.
- [Regresión PHP](regresion-php.txt) ejecutada después del fix: **17 pruebas / 109 comprobaciones**, sin fallos. Usa SQLite en memoria y los transportes controlados de esa suite existente; no cuenta como evidencia SMTP real.
- [Pint](pint.txt): pasan runtime y suite propia. [Regresión JS](regresion-js.txt): 7 pruebas repetidas después de aplicar la corrección y las guardas PHP.
- [verificacion.json](verificacion.json): único runtime modificado, ampliación de 14 líneas, alcance, hashes, guardas PHP y cierre. El proceso Node terminó con código 0; puerto cerrado, cero sesiones SMTP y ningún broker separado.

## Comparación conservada

- [antes132/](antes132/README.md): entrega original #65, seis casos / 209 comprobaciones; 550 incierto.
- [rojo132/](rojo132/README.md): ocho casos contra runtime anterior; falla únicamente la expectativa fallido para 550. Confirma el defecto antes de aplicar las 14 líneas.
- Archivos de este directorio raíz: ejecución corregida de #132. El original y la prueba roja se conservan sin sobrescribir sus resultados.

La implementación solo inspecciona tipo/código de excepción y clase/función del marco `SmtpTransport::doSend`; no analiza texto o debug privados. Las trazas adjuntas son sintéticas. No se adjuntan entornos, claves, bases ni logs privados.

Detalle del fix, límites y reproducción oculta: [SMTP_LOCAL65.md](../../SMTP_LOCAL65.md). Solo se demuestra protocolo SMTP local y estado de outbox, no entrega externa, TLS/AUTH ni concurrencia del SO.
