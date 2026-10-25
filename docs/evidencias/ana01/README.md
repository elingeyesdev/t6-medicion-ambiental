# Evidencia ANA-01 — solo metadatos, 25/10/2026

Esta evidencia registra acceso a catálogos y contexto territorial. **No acredita píxeles de agua, área inundada ni método aceptado.** No se descargaron imágenes satelitales; se conservaron dos respuestas JSON pequeñas y sus resúmenes. No son fixtures climáticos ni imágenes de una inundación.

- `cdse-A/informe.json`: GET oficial CDSE, 21:36:23 UTC, HTTP 200 / text/html, 246 bytes. Rechazo identificado como `RESPUESTA_NO_JSON`; cantidad de escenas desconocida. Se conserva hash/diagnóstico, sin publicar el HTML con su identificador de soporte.
- `mpc-A/respuesta.json` y `informe.json`: catálogo Microsoft Planetary Computer, 21:36:25 UTC, **8 fichas**, septiembre de 2024; sin siguiente página anunciada. Original de 122 405 bytes.
- `mpc-B/respuesta.json` y `informe.json`: mismo catálogo, 21:36:27 UTC, **12 fichas de una primera página**, marzo de 2025; **hay paginación pendiente**. Original de 183 897 bytes. No es un inventario completo de marzo.
- `area.json`: intersecciones positivas del rectángulo técnico con las geometrías locales del catálogo. Hash de municipios `d9293e6f8805fced5eca24b9b90882bc461c5a1390ddac4cd1cfc4a1dd06c83d`; no se interpreta como cobertura de un producto ni certificación legal.
- `inventario-local.json`: rutas generales y patrones revisados, sin datos personales ni inventario de documentos ajenos. La carpeta de imágenes original permanece por confirmar.

Los informes conservan fecha, URL/ventana/bbox, bytes y SHA-256 del JSON recibido, IDs y nubosidad de escena. Los activos SCL se **anuncian** en el catálogo; no se verificó su lectura ni se solicitaron firmas SAS. Nubosidad local y agua quedan null. Varias fichas son tiles de una misma adquisición; no son veinte observaciones independientes ni una pareja aprobada.

Reproducir offline con `python herramientas/inundaciones/catalogo.py revisar` desde la raíz. El comando comprueba hashes/contenido y reconstruye los resúmenes MPC. CDSE conserva solo un diagnóstico de fallo, no un original JSON verificable. Tests unitarios y CI no hacen peticiones externas. Las consultas nuevas se guardan en directorios nuevos de `tmp/`, conservando esta evidencia.
