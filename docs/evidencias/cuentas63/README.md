# Comprobación de cuentas — #63

28/10/2026. Rama `feature/63-cuentas-envirosense`, sobre la candidata #55. Revisión del compañero pendiente.

SQLite temporal independiente con cuatro cuentas sintéticas: administración, analista, visualizador y suspendida. Los correos `@ejemplo.test` son de prueba. No se copió la base de EnviroSense ni se conservaron contraseñas, enlaces de recuperación o tokens en estas capturas.

- `perfil.png`: nombre, rol y ubicación guardada. Se eligió el centro del mapa con Enter y se guardó; al cargar de nuevo se conserva.
- `usuarios.png`: roles y estados; la cuenta propia no ofrece controles para quitarse administración.
- `perfil-movil.png` y `usuarios-movil.png`: comprobación a 390 × 844, en una columna.
- `recuperacion.png`: acceso al formulario desde el ingreso, con fondo de Lomas de Arena. El cambio de contraseña y consumo del enlace se prueban con PHPUnit, sin cambiar credenciales reales desde el navegador.

La SQLite habitual se respaldó de forma privada antes de ejecutar las dos migraciones nuevas. Sus dos cuentas se conservaron; no se asignó administración a ninguna.

Validación: suite Laravel completa, JavaScript, Pint y Composer estricto. El resultado final y CI quedan en el PR. Las pruebas SQLite no acreditan concurrencia o despliegue PostgreSQL, ni ejecución en Flutter.
