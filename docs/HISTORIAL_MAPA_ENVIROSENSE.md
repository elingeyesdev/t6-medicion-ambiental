# Recorrer lecturas y compararlas en el mapa

Avance de #62 sobre `TimePlayer` y `CityHistoryPanel` del referente. Se adapta la consulta histórica de puntos a Laravel/Blade y a la API compartida. El mapa de capas raster y su reproducción no se dan por recuperados con esta parte.

## Recorrido

Al activar **Ver por momento**, se puede elegir fecha y hora, mover la barra o avanzar/retroceder una hora. El mapa consulta la última lectura disponible hasta ese momento dentro del período elegido. Conserva su fecha real: no genera una lectura en cada posición de la barra, no interpola ni rellena horas sin datos. Volver a desactivar el control recupera la última lectura del período completo.

Desde se incluye y Hasta se excluye; máximo siete días y sin futuro. El Momento se incluye, debe ser mayor o igual a Desde y menor que Hasta. Los campos y mensajes usan hora de Bolivia; la API exige UTC o zona explícita. El punto mantiene las coordenadas actuales de su ficha, mientras **Ver ubicación de la lectura** utiliza las coordenadas históricas guardadas.

## Comparación

**Fijar para comparar** conserva una copia del punto, sus lecturas, variable y unidad. Al elegir otro punto o momento, la ficha presenta Referencia y Selección, ambas con sus fechas reales. Se puede cambiar o quitar la referencia. Se conserva solamente en esta pantalla, sin grabar un reporte ni datos nuevos.

Las diferencias se calculan solo entre la misma variable, unidad, origen y fuente. Cero es un valor válido; una lectura que falta se muestra como **Sin lectura**, sin inventar una diferencia. No se mezclan registros, estimaciones y simulaciones. Las fechas pueden ser distintas: se muestran las dos para interpretar la diferencia, sin atribuirle una causa.

**Ver ambas ubicaciones** muestra A y B en las coordenadas históricas de cada lectura; si coinciden se ve A/B. Los puntos siguen visibles al lado de la ficha. Los datos simulados conservan su identificación y necesitan selección explícita.

## API para Flutter

La ruta existente `GET /api/v1/mapa/puntos` admite además `momento=2026-10-28T09:00:00Z`. Cuenta activa, token con capacidad `cuenta` y permiso `datos.consultar`; web usa sesión y `/mapa/puntos`. Los demás filtros y la paginación se conservan. La respuesta añade `momento`, o `null` cuando no se solicita, y sigue separando las últimas lecturas por origen/fuente.

Un Momento fuera de período, sin zona, futuro o repetido devuelve 422, sin consultar proveedores ni insertar datos. La comparación usa las dos respuestas verificadas y sus valores/fechas/coordenadas; Flutter debe mantener la misma separación y no restar variables o unidades diferentes. La referencia no queda en el servidor.

## Verificación y límites

Se añadieron seis pruebas de Laravel y diez de JavaScript: instante exacto, final excluido, cero, faltantes, coordenadas históricas, permisos y separación de fuentes/orígenes. La suite completa pasó: 371 pruebas de Laravel (3457 comprobaciones) y 63 de JavaScript. Las capturas de `evidencias/historial62/` muestran lecturas simuladas locales, identificadas como tales; no se generaron observaciones para completar la pantalla.

Pendientes de #62: capas raster históricas y reproducción automática/buffer, comparación de capas meteorológicas completas, establecimientos/POI y el recorrido Flutter. También sigue pendiente la revisión de la cadena de PR; no se presenta este avance como toda la conversión de EnviroSense.
