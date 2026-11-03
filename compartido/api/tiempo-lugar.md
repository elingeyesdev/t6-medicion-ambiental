# Tiempo de un lugar: horas recientes y pronóstico

Recuperación web/API #124, dentro de #62/#66. Conserva el proveedor de EnviroSense. No cambia el contrato M2 de focos de calor ni implementa el cliente Flutter.

`GET /api/v1/tiempo?latitud=-17.7833&longitud=-63.1833`

Requiere una cuenta activa, Sanctum con capacidad `cuenta` y permiso `datos.consultar`. El equivalente web es `GET /mapa/tiempo` con sesión. Ambos mantienen doce peticiones por minuto y cuenta, coordenadas numéricas dentro de −90…90 / −180…180, y respuesta privada `no-store`. La URL, las variables, el período y el proveedor se fijan en el servidor.

## Respuesta

Se mantienen `fuente`, `origen`, `zonaHoraria`, `consultadoEl`, `ubicacion`, `actual`, `horas`, `dias` y `variables`. Se agrega **`historial`**, siempre un arreglo; los clientes anteriores pueden seguir usando los demás campos.

- `actual.instante`: instante UTC de las condiciones actuales del proveedor. Se rechaza si está en el futuro o tiene más de seis horas.
- `historial`: entre cero y 24 filas horarias, ordenadas, con instante **mayor** que `actual.instante − 24 horas` y **menor o igual** que `actual.instante`. Puede ser parcial; no se inventan filas ni valores. Es una ventana de 24 horas transcurridas, incluso si se repite una hora local durante un cambio de horario.
- `horas`: el calendario del pronóstico solicitado, desde hoy hasta seis días después, hasta 168 filas. Hoy se determina al consultar, no desde una condición actual que pudo llegar con retraso. Puede solaparse con el historial durante el día en curso; siguen siendo estimaciones del mismo modelo.
- `dias`: hasta siete fechas `YYYY-MM-DD`, desde hoy. El día adicional pedido al proveedor para el historial no se antepone al pronóstico.
- `variables`: nombres y unidades de temperatura, sensación, humedad, lluvia, probabilidad, viento, dirección, visibilidad, nieve, nubosidad, presión y UV.

Una fila de `historial` tiene exactamente la misma forma y unidades que una fila de `horas`: `instante` ISO 8601 UTC terminado en `Z`, seguido de las claves de `variables`, con valor numérico o `null`. Cero es un dato válido, distinto de `null`. Las fechas diarias respetan el offset de calendario devuelto por el proveedor; la interfaz muestra los instantes en la zona IANA `zonaHoraria`. Distingue las horas locales repetidas con su offset.

`fuente = Open-Meteo` y `origen = estimado`. `past_days=1` recupera pronósticos archivados recientes: **no son observaciones de estaciones ni reanálisis ERA5**, y no se mezclan con lecturas guardadas, alertas ni el historial de imágenes del mapa. El plazo de caché sigue siendo quince minutos; en #124 la clave pasó a v2 para no reutilizar respuestas anteriores sin `historial`.

## Condición del tiempo · #127

Cada fila de `actual`, `horas`, `historial` y `dias` añade `codigoTiempo`, entero 0…99 o `null`. Es la clasificación WMO de `weather_code` del proveedor, no una magnitud que se convierta de unidad. El código **0** significa despejado, no dato ausente. Cadenas, booleanos, fracciones y valores fuera del rango quedan en `null`. Un código numérico sin clasificación reconocida se conserva para compatibilidad, y la interfaz muestra «Sin dato»; no lo convierte en sol o tormenta.

`actual.esDia` es booleano o `null`, según `is_day` numérico 1/0 del proveedor. La noche cambia el símbolo de cielo despejado/parcialmente nublado, sin alterar su descripción. No se infiere desde la zona del navegador. Un día completo no usa este indicador actual.

En `dias`, el código es **la condición más intensa prevista para esa fecha**, según la definición del proveedor; no representa las 24 horas completas ni una alerta oficial. Los códigos horarios mantienen su propio instante y el historial conserva las fronteras de #124. Las series categóricas no aparecen en `variables`, en el selector de magnitudes, ni en los gráficos numéricos.

La unidad de origen se comprueba como `wmo code`. Si falta la clasificación se mantiene el resto del tiempo; un vector de códigos con longitud incoherente o una unidad incompatible devuelve 503. El nuevo campo no cambia los permisos, frecuencia ni formato de los campos anteriores. La caché pasa a **v3** para evitar respuestas anteriores sin condición. Un cliente anterior puede ignorar ambos campos, y la web conserva el recorrido si recibe un DTO anterior sin ellos.

La clasificación de la web está en `web/public/js/mapas/condicion-tiempo.js`, con descripciones en español y SVG propios. Incluye el código 97 de tormenta fuerte publicado en la documentación oficial consultada el 31/10/2026. No se usan emojis ni HTML recibido del proveedor. Ver [comprobación #127](../../docs/evidencias/condicion127/README.md).

Errores: 401 sin acceso/cuenta inactiva, 403 sin permiso/capacidad, 422 coordenadas inválidas, 429 por frecuencia y 503 por fallo o respuesta inválida del proveedor. No se devuelven detalles internos.

## Comprobación

En la ficha: **Tiempo por hora → Período → Últimas 24 horas**. Cambiar período, variable o unidad reutiliza la respuesta ya recibida. Una serie vacía lo indica; los faltantes o huecos temporales cortan el gráfico. Los datos actuales y los próximos días conservan su recorrido.

Ver [evidencias #124](../../docs/evidencias/tiempo124/README.md) y [servicio recuperado](../../docs/TIEMPO_ENVIROSENSE.md). Fuente de los parámetros y su naturaleza: [documentación oficial de Open-Meteo](https://open-meteo.com/en/docs).
