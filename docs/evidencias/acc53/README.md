# Recorrido de EnviroSense - ACC-01 / #53

Revisión local del 27/10/2026. PR #51, rama `refactor/47-panel-envirosense`. La web de sequías #45 ya está integrada en develop; este recorrido ampliado queda para revisión de Jaziel.

- `01-inicio.png`: portada de EnviroSense con su marca y los módulos disponibles. Tabler 1.7.1, tipografía y paleta compartidas, texto breve y mapa provincial de referencia.
- `02-acceso.png`: acceso en dos columnas, adaptado de AuthHero/Auth.css, con formularios Tabler y destino conservado.
- `03-mapa.png`: sesión iniciada y consulta real de focos del 25/10 00:00 al 26/10 00:00 Bolivia. 489 detecciones, 17 de confianza alta y FRP máxima de 104,94 MW; se dibujan los 25 registros de la página actual. No son detecciones del día de la captura.
- `04-acceso-movil.png`: acceso en viewport 390 × 844, sin desbordamiento horizontal. Corresponde a la web, no a Flutter.

Se creó una cuenta **solo local de prueba** para recorrer registro → mapa → salida → contraseña incorrecta → ingreso correcto. No se publican contraseñas ni se distribuye la base local. También se verificó que el mapa y los módulos vuelven al acceso al cerrar sesión; las rutas de datos de Flutter conservan su contrato.

Pruebas: **238 de Laravel / 1709 aserciones**, **40 de JavaScript**, Pint correcto. Las diez nuevas pruebas de acceso cubren sesión, registro, credenciales, bloqueo por intentos, tipos inválidos, límite en bytes, cierre y CSRF real. Se reutiliza el esquema de usuarios de Laravel, sin copiar cuentas del referente. El piloto de sequías conserva su preparación privada y su propia vigencia.

Los textos de ayuda, fuente y límites están disponibles en desplegables. Los errores, resultados parciales y consulta anterior siguen siendo visibles. Los detalles técnicos del flujo y las carpetas están en `docs/ACCESO_ENVIROSENSE.md`.
