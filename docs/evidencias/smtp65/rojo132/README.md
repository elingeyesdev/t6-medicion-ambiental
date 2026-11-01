# Prueba roja de #132

Ocho casos SMTP reales ejecutados contra el runtime anterior: un fallo, únicamente en la nueva expectativa fallido / PROVEEDOR_RECHAZO del RCPT 550; se obtuvo incierto / TRANSPORTE_SIN_CONFIRMACION. El resto de los casos, incluido el 554 tras aceptar DATA y el 450, pasó.

El servidor y el puerto se cerraron también en esta ejecución fallida. Los hashes de fuentes en resultado.json corresponden a esta fase anterior al fix y al formato final de la suite.
