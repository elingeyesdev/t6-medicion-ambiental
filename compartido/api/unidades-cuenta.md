# Unidades de la cuenta

Seguimiento #85 / #48. Usa Sanctum, token con capacidad `cuenta` y una cuenta activa, igual que `GET /api/v1/cuenta`. Las respuestas llevan `Cache-Control: no-store`.

`GET /api/v1/cuenta/unidades` devuelve `unidades` con las elecciones de la cuenta y `catalogo` con grupos, variables compatibles, unidad base, opciones, coeficientes y precisión. El mismo catálogo versionado está en `compartido/unidades/presentacion.json`.

`PUT /api/v1/cuenta/unidades` recibe:

```json
{"unidades":{"temperatura":"F","viento":"m/s"}}
```

Temperatura: `C`, `F`, `K`. Viento: `km/h`, `m/s`, `mph`. Se puede enviar solo uno de los dos campos. El otro conserva su valor anterior; una cuenta sin preferencias usa `C` y `km/h`. La respuesta contiene las elecciones normalizadas y el catálogo, como GET.

`GET /api/v1/cuenta` también incluye `preferencias.unidades`. Guardar ubicación o avisos no borra las unidades. No se admiten grupos desconocidos, unidades de otro grupo, valores nulos o asignar una cuenta/rol ajeno. Los errores siguen el contrato común de validación; no se guarda parcialmente una petición inválida.

## Presentación en Flutter

Las respuestas de mapa, historial, tiempo y simulaciones conservan las unidades base. No reenviar los valores convertidos como si fueran lecturas base. Para una variable compatible y una respuesta cuya unidad coincide con `base`, presentar `valor * factor / divisor + desplazamiento`. Para una **diferencia**, omitir el desplazamiento. Mostrar `simbolo` y redondear solo en la pantalla.

Los alias de sensación, máxima y mínima usan la unidad de temperatura. Una unidad base distinta se conserva para evitar una segunda conversión. Los faltantes siguen siendo faltantes; el cero se convierte normalmente. No convertir índices generales AQI en PM2.5 ni mezclar fuentes/orígenes para comparar. Las coordenadas y fechas tampoco cambian.

Comprobaciones de referencia: 0 °C → 32 °F o 273,15 K; diferencia 2 °C → 3,6 °F o 2 K; 36 km/h → 10 m/s; 16,09344 km/h → 10 mph. Los reportes guardados y sus exportaciones mantienen sus unidades explícitas.

La ruta web equivalente es `PUT /cuenta/unidades`, con sesión y CSRF. Acepta formulario sin JavaScript y JSON para el selector del mapa.
