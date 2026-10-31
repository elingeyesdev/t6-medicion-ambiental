# Simulaciones de EnviroSense en GeoSur

Seguimiento #52. La pantalla `/simulaciones` tiene acceso propio en el menú de las cuentas Analista y Administrador. Visualizador mantiene los permisos de consulta originales; no se cambia su rol al abrir la página.

## Recorrido recuperado

Elegir temperatura, humedad, aire, agua o ruido; configurar entre uno y treinta días, intervalo y código de repetición; comparar puntos del catálogo o regiones recuperadas. Cada zona puede tener un escenario y una intensidad diferentes. La web permite comparar tres zonas; la API admite hasta diez, con un máximo de 2000 instantes y 10000 valores por sesión.

Los diez casos y sus tres intensidades provienen de `simulacion-zona/escenarios.data.js`. Se reproducen con `herramientas/datos/recuperar-escenarios.py --comprobar`. Se adaptan las curvas lineal, pico y exponencial y la variación gaussiana del 4 % del rango de `simulacion-zona.service.js`. El ruido utiliza SHA256 y una semilla explícita para obtener el mismo resultado en revisiones repetidas. Se conservan los límites absolutos del catálogo original; esto es una simulación de pruebas y no un método científico de predicción.

Se pueden ajustar valor inicial, objetivo y curva; el caso Personalizado requiere esos tres parámetros. La zona usa su límite original y un punto de referencia dentro de él; no se presenta como estación física ni como promedio de toda la región.

La sesión y su resultado quedan guardados por usuario. Reproducir, pausar, detener, reiniciar y finalizar actualizan su estado con revisión para impedir que dos pantallas sobrescriban cambios. La reproducción visual recorre resultados ya calculados en el navegador; no consume un request por cada frame ni ejecuta un trabajador global al abrir la web. La sesión puede volver a abrirse y continuar desde la última posición guardada por un control.

Mapa, comparación, gráfico y resumen muestran datos simulados. **Preparar una sesión no escribe lecturas ambientales.** Guardarlas requiere un botón explícito: solo se incorporan fechas pasadas, con origen `simulado` y fuente propia de la sesión. Repetir la acción no duplica ni modifica datos. Los puntos movidos o archivados impiden cambiar el historial de esa sesión. Las fechas futuras permanecen dentro del escenario y no entran como registros reales.

## API para Flutter

Todas las rutas usan cuenta activa, token Sanctum con habilidad `cuenta` y permiso `simulaciones.gestionar`. Cada sesión es privada de su creador; otra cuenta recibe 404.

- `GET /api/v1/simulaciones/catalogo`: variables, escenarios, puntos y regiones.
- `GET /api/v1/simulaciones`: sesiones propias, paginadas de veinte en veinte.
- `POST /api/v1/simulaciones`: `nombre`, `metrica`, `inicio` ISO con zona, `dias`, `intervalo` en minutos, `semilla`, `zonas`.
- Cada zona lleva `punto` **o** `region`, `escenario` y `nivel` (`bajo`, `medio`, `alto`); opcionales `inicio`, `fin` y `curva`. Caso `personalizado`: los tres parámetros son obligatorios.
- `GET /api/v1/simulaciones/{id}`: configuración, resultado, estado, posición y revisión. Las fechas virtuales están en `resultado.instantes`; cada serie tiene sus valores y coordenadas.
- `POST /api/v1/simulaciones/{id}/accion`: `accion` (`iniciar`, `pausar`, `detener`, `reiniciar`, `finalizar`), `posicion` y `revision`. Revisión antigua o transición incompatible: 409. Parámetros inválidos: 422.
- `POST /api/v1/simulaciones/{id}/historial`: incorporación explícita de las lecturas pasadas; responde `nuevas`, `repetidas` y `futuras` excluidas.

Se conserva el esquema de errores y `Cache-Control: no-store`; no se acepta `usuario_id` desde el cliente. Requiere la migración `000004`, sin borrar datos ni importar cuentas del original.

## Verificación y límites pendientes

Trece pruebas específicas verifican permisos, catálogo, repetición, todas las variables, comparación, regiones, personalización y cero, límites, estados, revisiones, privacidad, publicación idempotente, datos futuros y puntos movidos. La suite completa pasó 323 pruebas y 3112 comprobaciones con esta ampliación.

El lote local `feature/52-simulacion-continua` recupera el recorrido global y sus controles/inyección mediante consultas HTTP breves, además de sincronizar el avance de escenarios en el servidor. La entrega necesita integrar sus rutas y migración; no usa persistencia ambiental automática ni notificaciones. Ver [contratos, snippets, pruebas y límites M3](INTEGRACION_M3_SIMULACIONES_REPORTES.md). La adaptación Flutter y la integración/revisión del compañero siguen fuera de este lote; no se declara terminado EnviroSense completo.
