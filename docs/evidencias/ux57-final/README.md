# UX-02 / #57 - Continuidad sin repetir pantallas

El inicio conserva su portada. El ingreso y el registro usan una tarjeta de formulario clara, con foto lateral solo en escritorio. Comparten cabecera, pie, verdes, botones de 48 px, esquinas de 6 px y texto normal. Sobre blanco se usa la acción verde de Tabler; sobre la foto del inicio se conserva su variante blanca.

- `01-ingreso.png`: formulario independiente en escritorio.
- `02-ingreso-movil.png`: ingreso a 390 × 844.
- `03-registro-movil.png`: registro con cuatro campos a 390 × 844.

En móvil se comprobó ancho de documento 375 px dentro de viewport 390, campos de 16 px y navegación entre pantallas. El ingreso demo y cierre de sesión funcionaron. Las pruebas de registro, errores y protección de sesión forman parte de las 244 pruebas Laravel / 1740 aserciones que pasaron; Pint también pasó. La letra activa es Segoe UI / Arial.

Tabler 1.7.1 aporta controles, tarjetas y rejilla. La distribución, marca y foto son la adaptación de GeoCruz. Se conservan la licencia, los archivos originales de Tabler y el crédito de la foto. La propuesta local que repetía la portada no forma parte de estas capturas ni de la entrega final. Revisión de Jaziel pendiente en el PR #55.
