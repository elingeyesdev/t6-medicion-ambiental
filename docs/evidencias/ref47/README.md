# Revisión del panel - #47

Versión candidata de EnviroSense/GeoCruz, 26/10/2026. Rama `refactor/47-panel-envirosense`, basada en la web de #37 / PR #45. La revisión de Jaziel y la integración a develop siguen pendientes.

- `01-panel.png`: panel real con Tabler; solo focos y sequías en menú e inicio. Sin estadísticas ficticias ni módulos pendientes.
- `02-sequias-ficha.png`: ficha abierta de `scz-piloto-01`; SPI, lluvia, referencia y cobertura conservados. Captura de viewport después de abrir el detalle, sin retocar.
- `03-menu-movil.png`: navegador a 390 × 844, menú desplegado. Se comprobó también el cierre con teclado; ancho del documento 375 frente a viewport 390, sin desbordamiento horizontal.
- `04-focos-reales.png`: consulta NASA FIRMS del 25/10/2026 00:00 al 26/10/2026 00:00 Bolivia, final excluido. Se ven 489 detecciones totales, 17 de confianza alta y FRP máxima de 104,94 MW; mapa y lista conservan su página de 25 y el contorno territorial. El resultado no es el caso distinto de 1.482 detecciones registrado por Jaziel para 24-25/10.

La distribución y el contraste se revisaron en el navegador, conservando el tamaño de escritorio que tenía el panel. No se afirma aquí una prueba nueva de Flutter: la captura móvil es la **web** en pantalla pequeña.

Laravel: 228 pruebas / 1618 aserciones; JavaScript: 40 pruebas aprobadas. Se aisló el directorio de vistas compiladas de las pruebas para evitar competencia con el servidor PHP de Windows durante la ejecución. No fue necesario cambiar la lógica ambiental. Pint correcto.

El piloto usa una publicación real renovada por consola: objetivo recuperado de Open-Meteo el 26/10/2026 a las 16:56 Bolivia, vigencia hasta el 27/10 a las 16:56. El SPI se conserva en -0,6410480784 y la precipitación en 114,5 mm; período julio-septiembre de 2024, referencia 1991-2020. No representa condiciones actuales ni cobertura completa: 1 muestra publicada, 55 pendientes. La base histórica conserva su propia fecha de recuperación; no se declara descargada de nuevo por renovar el objetivo.

Los directorios privados y el .env no se adjuntan. En otra instalación hay que preparar las publicaciones según la guía de API-02; las capturas acreditan el recorrido de esta copia y no distribuyen una caché privada como parte del código.

La clave NASA se configuró solo en el .env local reutilizando la configuración privada ya existente de API-01. Se comprobó antes el aviso de configuración ausente y, después, el reintento correcto; no se adjunta ni se copia esa clave a clientes o comentarios.
