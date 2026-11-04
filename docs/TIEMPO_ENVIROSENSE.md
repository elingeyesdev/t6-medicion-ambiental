# Tiempo y pronóstico de EnviroSense

Recuperación del servicio `radar/forecast` del referente como parte de #66 y #62. La consulta está dentro del mapa: se puede abrir en un punto o pulsar **Tiempo de un lugar** y elegir cualquier ubicación. También se puede mover el mapa con teclado y confirmar el centro con Enter.

Usa Open-Meteo, el proveedor del servicio original. Devuelve temperatura, sensación, humedad, lluvia, probabilidad, viento/dirección, visibilidad, nieve, nubosidad, presión y UV; datos actuales, hasta 24 horas recientes, 168 horas del calendario del pronóstico y siete días cuando el proveedor entrega la serie completa. Todos se identifican como **estimados**. No se guardan como lecturas de estaciones ni se disparan alertas sobre pronósticos.

## Correcciones al adaptar el original

- Las fechas del proveedor se solicitan como segundos UTC y se normalizan a ISO con Z. La pantalla las presenta en el huso del lugar; no depende del huso del navegador ni interpreta fechas sin zona como si fueran locales.
- Un dato ausente queda en `null`, distinto de cero. No se inventa temperatura cero, presión de 1013 o visibilidad de diez kilómetros al faltar una variable.
- Se validan forma, orden y longitud de las series, unidades, rangos y vigencia. Un fallo devuelve 503 con un mensaje breve; no devuelve un éxito con datos de demostración.
- Caché de quince minutos solo para respuestas válidas y límite de doce consultas por minuto/cuenta. El proveedor y las unidades se fijan en el servidor; no se admite una URL elegida por el cliente.
- La ficha se puede cerrar y el encabezado permanece visible al recorrer el pronóstico. No se añaden explicaciones de caché, backend u otros detalles internos a la pantalla.

## API para Flutter

`GET /api/v1/tiempo?latitud=-17.7833&longitud=-63.1833`: requiere cuenta activa, Sanctum, capacidad `cuenta` y permiso `datos.consultar`. La web usa sesión y `GET /mapa/tiempo` con los mismos parámetros y resultado.

Devuelve `fuente`, `origen=estimado`, `zonaHoraria`, `consultadoEl`, `ubicacion`, `actual`, `horas`, `historial`, `dias` y catálogo `variables` con nombres/unidades. Las coordenadas se redondean a cuatro decimales para consultar y almacenar la caché. El proveedor estima una celda de modelo, no una estación física exacta en esa coordenada. El [contrato de #124](../compartido/api/tiempo-lugar.md) define la separación entre horas recientes y pronóstico, las fronteras de fecha y la compatibilidad.

Errores: 401 por falta de acceso/cuenta inactiva, 403 por permiso/capacidad, 422 por coordenadas inválidas, 429 por frecuencia y 503 por proveedor o respuesta inválida. Una consulta no inserta puntos ni lecturas en la base. La pantalla mantiene separados el historial elegido y el pronóstico actual.

## Fuentes y comprobación

Se contrastó con el servicio conservado `referencias/envirosense/codigo/Backend/Src/modules/radar/radar.service.js` y la [documentación oficial de Open-Meteo](https://open-meteo.com/en/docs). La atribución se mantiene en la ficha.

Pruebas específicas: ocho pruebas y 63 comprobaciones sobre permisos, coordenadas cero/inválidas, faltantes, unidades, zonas horarias, fechas, caché, fallo, respuesta malformada y frecuencia, incluidos los errores de la ruta web. La suite completa con el buscador pasó: 365 pruebas y 3410 comprobaciones. Se hizo además una consulta real de Santa Cruz (168 horas, siete días) y una comprobación en la ficha del mapa. Ver [evidencias/tiempo66](evidencias/tiempo66/README.md).

El candidato está en el PR #78, sobre #77. #79 corrige los filtros de los controles de GitHub para que se ejecuten también cuando el destino es una rama `fix/`, `hotfix/`, `docs/` o `test/`, conservando sus permisos de lectura y sus filtros de archivos.

Actualización del candidato al 31/10: las capas y su recorrido/comparación tienen implementación en #62/#66 y sus PR encadenados; los reportes atmosféricos en #103, el aire en #101 y las unidades en #85. Ver [capas del mapa](CAPAS_MAPA_62.md) y [unidades](UNIDADES_ENVIROSENSE.md). El avance nuevo #124 recupera las horas recientes de `Frontend/src/utils/weatherApi.js`, junto al pronóstico existente. Ver [su comprobación](evidencias/tiempo124/README.md). Estas implementaciones pendientes de revisión no declaran terminadas #62/#66, Flutter ni toda la migración.

## Alcance actualizado

Josué retiró el chatbot el 28/10. #67 queda cancelada, no implementada. No se solicitan claves de IA ni se activa un asistente conversacional; esta consulta meteorológica funciona sin chatbot.
