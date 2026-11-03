# Alertas y avisos de EnviroSense

Candidato Laravel/Blade/API de #65 sobre Reportes #74. Conserva los tres roles del original, como confirmó Josué: Visualizador consulta; Analista y Administrador reconocen alertas. No se alteran los roles de las cuentas locales.

## Qué se recuperó

- 29 umbrales de las cinco variables originales, extraídos del seed sin ejecutar SQL y con huella del archivo. Se comprueban con `python herramientas/datos/recuperar-umbrales.py --comprobar`.
- Al registrar lecturas, se evalúan cambios de nivel. Como en la implementación final del original, solo críticas y emergencias generan alertas persistidas. El estado se conserva en la base, separado por punto, variable, origen y fuente; los reinicios no duplican alertas.
- Historial con filtros por variable, origen, nivel, fechas y reconocimiento. Un reconocimiento guarda usuario/fecha reales y no se sobrescribe al repetirlo. Ningún formulario puede elegir otro usuario como autor.
- Pantalla de niveles y acceso al historial del punto. El listado excluye simulados hasta elegirlos expresamente; el escenario queda identificado.
- Bandeja privada de avisos dentro del sistema, preferencias por variable e inclusión opcional de simulaciones. No hay suscripción automática. Respeta cuenta activa, ubicación guardada y radio de 50 km; sin ubicación, ámbito general. Un aviso como máximo por usuario/variable/origen cada dos minutos, conservando todas las alertas.
- Las lecturas de más de quince minutos de antigüedad pueden generar alertas de historial, pero no avisos que parezcan actuales. Las fechas y coordenadas de la lectura se conservan.

El seed tiene rangos inclusivos superpuestos y huecos entre enteros de AQI/ICA. Se conserva el primer intervalo coincidente del original; un decimal en un hueco pasa al intervalo superior, en lugar del salto erróneo al último nivel que hacía el código anterior. Los niveles recuperados no se presentan como una validación normativa ni recomendación médica.

## API para Flutter

Se requiere cuenta activa, Sanctum y capacidad `cuenta`. Las rutas de consulta usan el permiso existente `datos.consultar`; reconocer usa `alertas.gestionar`.

- `GET /api/v1/alertas`: `metrica`, `origen`, `severidad=critica|emergencia`, `reconocida=0|1`, `desde`, `hasta`, `page`. Veinte registros por página. Fechas ISO UTC, desde incluido y hasta excluido; sin fechas futuras.
- `PATCH /api/v1/alertas/{id}/reconocer`: cuerpo vacío. Devuelve la alerta; idempotente, actor tomado de la sesión/token.
- `GET /api/v1/umbrales`: catálogo original y procedencia.
- `GET /api/v1/avisos`: bandeja propia paginada, contador `sinLeer` y preferencias.
- `PUT /api/v1/avisos/preferencias`: `{activos:true, simulados:false, metricas:["temperatura","aqi"]}`. Entre una y cinco variables, sin repetir.
- `PATCH /api/v1/avisos/{id}/leer`: marca un aviso propio, idempotente. Un identificador ajeno devuelve 404.

Para las lecturas locales anteriores a esta migración: `./herramientas/php.ps1 web/artisan alertas:evaluar`. Admite `--desde` y `--hasta` ISO UTC, máximo diez mil lecturas candidatas, sin recortes silenciosos. Respeta el estado actual: un dato atrasado no lo hace retroceder. Se puede repetir sin duplicar. No borra datos ni envía mensajes externos.

## Verificación y pendientes

Trece pruebas específicas cubren procedencia, límites, ceros, huecos, persistencia, duplicados, carga atrasada, transacciones, filtros, roles, reconocimiento, distancia, preferencias, bloqueo entre cuentas y frecuencia. La suite completa pasa: 350 pruebas y 3284 comprobaciones. También se probaron las pantallas con la cuenta demo y las alertas simuladas existentes del almacenamiento local, sin inventar observaciones.

Actualización M3 (#65/#63): se implementan outbox, consentimiento y transportes de correo/Telegram/WhatsApp/push web, con bandeja actualizada mediante consultas breves. Integración exacta, pruebas y límites en [Avisos y cuentas web](M3_AVISOS_CUENTAS_WEB.md). Los proveedores siguen desactivados; no se ejecutó una conexión externa real ni se comprobó recepción. La habilitación/prueba externa autorizada y el cliente Flutter #48 siguen separados de esta entrega. No se utiliza WhatsApp personal como canal del software.
