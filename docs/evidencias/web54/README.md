# WEB-03 / #54 - GeoCruz y acceso demo

Comprobación local del 27/10/2026, rama `feature/54-marca-geocruz`, sobre la base candidata #51. Tabler 1.7.1 se mantiene sin editar sus recursos originales.

- `01-inicio-geocruz.png`: nombre, símbolo ambiental aprobado, Explorar y Qué quieres consultar en escritorio.
- `02-demo-mapa.png`: sesión de Demo GeoCruz y consulta externa real del 25/10 00:00 al 26/10 00:00 Bolivia: 489 detecciones, 17 de confianza alta, FRP máxima 104,94 MW. Se representan los 25 registros de la página; no son observaciones del día de la captura.
- `03-menu-movil.png`: menú en viewport 390 × 844 con la marca y las consultas, sin desbordamiento horizontal. Es la web responsive, no Flutter.

Se probó ingreso por el formulario normal con la cuenta local demo, menú contraído, tema oscuro, navegación a Sequías y consulta del piloto de septiembre de 2024: SPI-3 -0,64, Normal, con su vigencia y cobertura propias. Al cerrar sesión, Entrar al mapa volvió a pedir el acceso. No se publican la base ni contraseñas.

Resultado: 242 pruebas de Laravel / 1733 aserciones, 40 pruebas de JavaScript y Pint correctos. Los cuatro casos nuevos cubren creación con hash y acceso, conservación de una cuenta existente, rechazo de clave débil y bloqueo del comando en producción. El trabajo queda para revisión de Jaziel; una prueba local no acredita despliegue público.
