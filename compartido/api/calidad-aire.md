# Calidad del aire del lugar

Seguimiento #101/#66. Implementado en web/API; Flutter no se modificó.

`GET /api/v1/aire?latitud=-17.7833&longitud=-63.1833`

Usa Bearer de una cuenta activa, habilidad `cuenta` y permiso `datos.consultar`. En Blade, el equivalente es `GET /mapa/aire` con sesión. Ambos aplican doce peticiones por minuto y por usuario. El endpoint anterior `/api/v1/calidad-aire` sigue siendo el marcador de un análisis futuro del Planner y no se reemplaza silenciosamente.

Coordenadas requeridas y numéricas, latitud −90…90 y longitud −180…180. No admite cambiar la URL, proveedor, modelo, variables o fechas desde el cliente. El servidor redondea a cuatro decimales y puede reutilizar una respuesta válida durante quince minutos.

Respuesta 200:

```json
{
  "escala": "european_aqi",
  "nombreEscala": "Índice europeo",
  "origen": "estimado",
  "fuente": "CAMS global · Open-Meteo",
  "ubicacion": {"latitud": -17.7833, "longitud": -63.1833},
  "celda": {"latitud": -17.799995, "longitud": -63.199997},
  "consultado": "2026-10-30T03:49:03Z",
  "desde": "2026-10-29T03:49:03Z",
  "hasta": "2026-10-30T03:49:03Z",
  "actual": {"instante": "2026-10-30T03:00:00Z", "indice": 47, "pm25": 17.3, "pm10": 18, "no2": 37.6, "ozono": 13, "so2": 18.7},
  "horas": [],
  "variables": {}
}
```

El ejemplo abrevia `horas` y `variables`; la captura completa está en `docs/evidencias/aire101/consulta-normalizada.json`. La respuesta también incluye documentación y atribución del proveedor. `horas` contiene hasta 25 posiciones del tramo cerrado de 24 horas; en una consulta entre horas normalmente son 24. Cada posición contiene `instante` y las mismas seis claves que `actual`. Cada entrada de `variables` declara `nombre` y `unidad`: `indice/EAQI`; las demás, `μg/m³`. Valores numéricos o null; un cero nunca significa ausencia.

Este índice europeo se clasifica en bandas 0–20, >20–40, >40–60, >60–80, >80–100 y >100. No es `us_aqi` ni se debe insertar como el AQI genérico de las lecturas de sensores. Conservar escala, fuente y origen en cualquier comparación. `ubicacion` es la consulta; `celda` identifica el centro de la rejilla utilizada. No representa una estación física.

No se publican posiciones futuras ni se elige el dato más cercano en su lugar. La consulta actual y sus 24 horas son independientes del filtro temporal de las lecturas guardadas en el mapa. No crea registros ni alertas.

Errores con el envoltorio habitual `error.codigo/mensaje`: 401 sesión inválida, 403 sin permiso, 422 coordenadas inválidas, 429 límite y 503 `CALIDAD_AIRE_NO_DISPONIBLE`. El cliente puede reintentar por petición del usuario; mantener la ficha previa con su fecha y una advertencia si falla una actualización. Cancelar al cerrar o cambiar el lugar y descartar respuestas que lleguen tarde. No consultar el proveedor automáticamente en un bucle.
