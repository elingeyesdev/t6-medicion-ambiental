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

`fuente = Open-Meteo` y `origen = estimado`. `past_days=1` recupera pronósticos archivados recientes: **no son observaciones de estaciones ni reanálisis ERA5**, y no se mezclan con lecturas guardadas, alertas ni el historial de imágenes del mapa. El plazo de caché sigue siendo quince minutos; la clave pasa a v2 para no reutilizar respuestas anteriores sin `historial`.

Errores: 401 sin acceso/cuenta inactiva, 403 sin permiso/capacidad, 422 coordenadas inválidas, 429 por frecuencia y 503 por fallo o respuesta inválida del proveedor. No se devuelven detalles internos.

## Comprobación

En la ficha: **Tiempo por hora → Período → Últimas 24 horas**. Cambiar período, variable o unidad reutiliza la respuesta ya recibida. Una serie vacía lo indica; los faltantes o huecos temporales cortan el gráfico. Los datos actuales y los próximos días conservan su recorrido.

Ver [evidencias #124](../../docs/evidencias/tiempo124/README.md) y [servicio recuperado](../../docs/TIEMPO_ENVIROSENSE.md). Fuente de los parámetros y su naturaleza: [documentación oficial de Open-Meteo](https://open-meteo.com/en/docs).
