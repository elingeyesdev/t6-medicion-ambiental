# M6 · Evidencia de API y web

Verificación del 24 de noviembre de 2026, hora de Bolivia. Los ejemplos con datos se consultaron en Laravel local. Las tres rutas que requieren cuenta se probaron con un usuario temporal en una base separada. El contraste web se repitió en el navegador integrado con la copia local. La prueba manual en Android cubre las consultas principales. Queda pendiente repetir el recorrido sobre el commit integrado.

## Recorridos

- **Focos de calor.** `/api/v1/focos-calor?desde=2026-11-16T21:25:51Z&hasta=2026-11-23T21:25:51Z`. Santa Cruz (BO07), 200, 1.765 detecciones en el conjunto y 100 en la primera página. NASA FIRMS, VIIRS NOAA-20 NRT. La detección de ejemplo del 23 de noviembre a las 17:15 UTC reporta 4,2 MW. La cobertura es desconocida y la fuente no publica una hora de actualización en esta respuesta. Un intervalo superior a siete días devuelve 422 con `FILTROS_INVALIDOS`.
- **Sequías.** `/api/v1/sequias?mesFinal=2026-10`. La publicación vencida devolvía 503. Tras renovar desde Open-Meteo/ERA5, la consulta, `/resumen?mesFinal=2026-10` y `/scz-piloto-01?mesFinal=2026-10` devolvieron 200. Ventana de agosto a octubre, SPI-3 de 1,8081424489 y cobertura parcial (1 de 56 municipios con datos). El período de septiembre devuelve `MUESTRAS_NO_DISPONIBLES`. Vigencia de esta renovación hasta el 25 de noviembre a las 22:01 en Bolivia.
- **Inundaciones.** `/api/v1/inundaciones/consulta?territorio=BO071005&referencia=2025-12-06&comparacion=2025-12-18`. San Pedro, 200, recorte parcial NASA OPERA DSWx-S1. Área comparable de 2,241 km² de 2,6766 km². Agua persistente 0,063 km², nueva 0,4968 km² y retirada 0,0567 km². Los mismos días con BO070101 devuelven 404 `TERRITORIO_SIN_COBERTURA`. El catálogo sin sesión devuelve 401.
- **Forestal.** `/api/v1/forestal/piloto?desde=2020&hasta=2024`. 200, recorte técnico, cinco años inclusivos. 2.838 píxeles arbolados en la base y 215 con pérdida en el período. El contrato declara el recorte como no validado. Sin años devuelve 422 y sin sesión devuelve 401. El visor departamental web tiene una capa satelital distinta. El recorte móvil y la capa departamental requieren superficies compatibles antes de comparar sus cifras.
- **Aire.** `/api/v1/aire?latitud=-17.7833&longitud=-63.1821`. 200, CAMS global mediante Open-Meteo, 24 registros horarios, índice 36 y PM2,5 de 12,9 µg/m³ en la hora 02:00 UTC del 25 de noviembre. Latitud 91 devuelve 422 y una petición sin sesión devuelve 401.

Focos y Sequías conservan provincia y municipio compatibles. Inundaciones acepta ese territorio y conserva sus propias comparaciones. Forestal usa años y Aire usa coordenadas y su ventana horaria. En el navegador se comprobó la conservación de San Pedro al navegar de Focos a Sequías e Inundaciones, y la URL de Forestal conservó el intervalo 2020–2024.

## Regresiones

- PHP en la rama sin configuración del servidor local. 162 pruebas y 708 aserciones aprobadas con `--filter 'ConsultaFocosTest|ConsultaAguaTest|CalidadAireTest|ParametrosConsultaForestalTest'`.
- JavaScript. 64 pruebas aprobadas de Focos, Sequías, Inundaciones, Forestal, Aire, contexto y procedencia. El caso nuevo comprueba que la ficha usa la fuente del registro y preserva un 0 MW informado.
- Portal de incendios, por HTTP con base temporal. Registro inmediato, rechazo del correo duplicado y de contraseñas distintas, salida y reingreso, consulta pública, 403 al intentar tomar un aviso sin sesión, rechazo de un aviso vencido y de una segunda toma, y publicación en historial del resultado registrado por la cuenta asignada.

## Recorrido en el navegador integrado

- **Portal.** Abrí una ficha pública con ubicación y fuente NASA FIRMS. En la copia temporal probé confirmación de contraseña distinta, alta inmediata, toma, resultado Sin incendio e historial público con un estado Resuelto y otro Vencido sin atención. Corregí la vuelta al listado al registrar una cuenta desde una ficha y repetí el alta. La cuenta nueva volvió directamente al aviso y vio el botón Tomar aviso.
- **Focos.** Consulté los últimos siete días y seleccioné San Pedro. Abrí una ficha desde el listado y comprobé NASA FIRMS, VIIRS NOAA-20 NRT, observación satelital, potencia e identificador.
- **Sequías.** El primer arranque sin caché alcanzó el límite de 128 MB de PHP. Reutilicé la geometría verificada en la petición y evité retener los índices que solo se usan al validar las muestras. La consulta de octubre volvió a responder con 128 MB. Comprobé SPI-3 de 1,81, 326,4 mm y cobertura parcial. Septiembre conservó el mapa anterior con un aviso de falta de publicación.
- **Inundaciones.** San Pedro conservó el lugar y cargó su comparación con 83,73 % de área comparable. Al elegir Cotoca, la vista indicó que aún no hay una comparación para ese lugar.
- **Forestal.** La capa Hansen GFC de Global Forest Watch cargó para 2020–2024. Al invertir los años, el visor conservó el resultado anterior y mostró el error del filtro.
- **Aire.** Busqué Santa Cruz de la Sierra y abrí el índice europeo de 36 y PM2,5 de 12,9 µg/m³. La ficha indicó estimación de modelo y atribuyó la fuente a CAMS y Open-Meteo.

Después de los arreglos pasaron 86 pruebas PHP de geografía, Focos y Sequías, con 844 aserciones. Los cambios quedan en la rama local para su integración.

## Tarea adicional

[Issue #194 · Validar el registro y la atención del portal](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/194) quedó abierto dentro de M6 para repetir la validación sobre la versión integrada.

## Recorrido manual en Android

APK debug del PR #195, instalado como variante separada y conectado a la API local con una cuenta desechable.

- Focos. Cargaron 2.123 detecciones en 85 páginas para los últimos siete días. La ficha previa desde el mapa mostró San Ignacio, 24/11/2026 14:38 en Bolivia, confianza nominal, 7,67 MW y NASA FIRMS.
- Sequías. Octubre de 2026 mostró una muestra de 56 municipios. El detalle de Santa Cruz de La Sierra mostró SPI-3 1,81, 326,4 mm y Open-Meteo ERA5. El centro de la celda está en Porongo.
- Inundaciones. San Pedro cargó la comparación del 6 al 18 de diciembre de 2025. Área comparable de 2,241 km², cobertura del 83,726 % y 0,497 km² de agua nueva redondeada.
- Aire. La ficha para el centro del mapa (-16,500, -63,500) mostró 37 EAQI, PM2,5 de 13,6 µg/m³ y serie horaria de CAMS global / Open-Meteo.
- Forestal. Con la variante que habilita la revisión forestal, Santa Rosa mostró 26 polígonos y 1,915 ha en 2024–2025. La vista indica revisión técnica y conserva la fuente Hansen GFC y el umbral de copa ≥30 %. Se abrió la lista y se seleccionó el polígono 1 (2024, 0,074 ha). Al detener la API aislada apareció Reintentar. Al restaurarla y pulsar ese botón se recuperaron el mapa y las cifras.
- Sesión vencida. Se invalidó solo el token de la cuenta de pruebas. Forestal devolvió la app a Iniciar sesión con el mensaje de vencimiento. Tras ingresar otra vez se abrió el mapa.
