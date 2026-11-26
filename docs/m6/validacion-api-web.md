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

## Contraste con los filtros de Android · #193

Se repitieron las consultas en la API local con una cuenta temporal para las rutas autenticadas. Los cinco casos devolvieron HTTP 200.

- **Focos.** Santa Cruz (BO07), desde 2026-11-18 02:58 UTC hasta 2026-11-25 02:58 UTC, con extremo final excluido. El conjunto contiene 2.123 detecciones y 25 por página, equivalentes a 85 páginas en Android. La ficha de San Ignacio (BO070301), del 24/11 a las 14:38 en Bolivia, corresponde a `firms-5fd251df1d58652e562a8a8a74fb7eda2d52e7e417b4067840d443122ded5232`, NASA FIRMS NOAA-20 NRT, con 7,67 MW.
- **Sequías.** Muestra `scz-piloto-01`, mes final `2026-10`, acumulado agosto–octubre. API, web y Android coinciden en 326,4 mm. El SPI-3 de 1,8081424489 de la API aparece como 1,81 en ambos clientes.
- **Inundaciones.** San Pedro (BO071005), 6–18 de diciembre de 2025. La API devuelve 2,241 km² comparables, 83,725622 % de cobertura y 0,4968 km² de agua nueva. Web muestra 2,24 km², 83,73 % y 0,50 km². Android muestra 2,241 km², 83,726 % y 0,497 km². Las cifras coinciden al aplicar la precisión de cada cliente.
- **Aire.** Centro del mapa de Android, latitud −16,5 y longitud −63,5. Para las 23:00 del 24/11 en Bolivia, API y Android muestran 37 EAQI y PM2,5 de 13,6 µg/m³. La API devuelve 24 registros de CAMS global vía Open-Meteo. El 25/11 se abrió la consulta web autenticada /mapa/aire en el navegador integrado de Codex, con las mismas coordenadas. En la serie horaria, el instante 2026-11-25T03:00:00Z (24/11 a las 23:00 en Bolivia) devolvió los mismos 37 EAQI y 13,6 µg/m³.
- **Forestal.** Recorte técnico de Santa Rosa, 2024–2025 y copa ≥30 %. API y Android coinciden en 26 polígonos y un total de 1,91489180 ha que la app muestra como 1,915 ha. El primer polígono de 2024 tiene 0,07365255 ha y aparece como 0,074 ha. El 25/11 se cargó el mismo paquete y período en el visor técnico /revision/forestal del navegador integrado. El visor mostró 26 polígonos, 1,915 ha y 0,074 ha en el primer polígono de 2024. Al repetir manualmente Android, la primera petición agotó el tiempo de espera y Reintentar recuperó el mapa y las cifras.

Tras la fusión, repetir los casos sobre el SHA de `develop`, registrar ese SHA y conservar las capturas y las respuestas de API correspondientes a cada filtro. Para Aire, usar las mismas coordenadas y la misma hora en ambos clientes. Para Forestal, comparar únicamente el recorte y el período equivalentes.

## Regresión manual del portal · #194

El 25/11 se usó el navegador integrado de Codex con una segunda base SQLite aislada. Las cuentas y los avisos eran de prueba.

- La ficha pública mostró ubicación aproximada, hora de Bolivia y NASA FIRMS. Al registrar la primera cuenta desde la ficha, volvió al mismo aviso y permitió tomarlo. Pasó a En verificación y mostró el formulario de resultado para su responsable.
- Tras cerrar sesión, el registro con el mismo correo redirigió a Acceso de brigada, conservó el aviso en la URL y mostró «Si ya tienes una cuenta, puedes iniciar sesión».
- Una segunda cuenta registrada con otro correo volvió a la misma ficha. Vio «Una brigada ya se hizo cargo del aviso», sin botones para tomar el aviso ni guardar un resultado.
- Sin iniciar sesión, el aviso de prueba publicado hacía 72 horas y un minuto quedó fuera de Activos y apareció en Historial con el estado Vencido sin atención.

Queda repetir estos escenarios sobre el commit integrado en develop.

## Regresión móvil y consulta de siete días · 25/11

- Sequías en Android rechazó un mes mal formado. Septiembre de 2026 mostró el mensaje de falta de datos sin reutilizar la muestra de octubre. Al pasar a Inundaciones se conservaron su territorio y su comparación propios.
- En modo avión, Inundaciones mostró el error de consulta, Focos ofreció reintentar el catálogo y Aire ofreció repetir la consulta. Tras reconectar, Aire volvió a mostrar el índice y la serie horaria. Inundaciones recuperó San Pedro con 2,241 km² comparables y cobertura del 83,726 %.
- Focos devolvía HTTP 500 en PHP con 128 MB al consultar los últimos siete días. El registro señaló memoria agotada en ConsultaFocos. Se cambió a procesar y liberar cada bloque FIRMS antes de solicitar el siguiente, con deduplicación y límites acumulados para el período. El índice espacial vive solo hasta terminar la preparación, antes de serializar el conjunto para la caché. La misma petición dio HTTP 200 con 2.340 detecciones; en el navegador integrado y por API se comprobaron dos páginas de 25 registros, con el mismo total y sin identificadores repetidos. Se usó SQLite para la caché y se mantuvo el límite de 128 MB. En Android, Reintentar en una ventana de siete días distinta recuperó 2.352 detecciones y el mapa.

## Rendimiento y recorrido de interfaz · 25/11

La consulta de Focos para Santa Cruz del 18 al 25 de noviembre a las 08:33 de Bolivia devolvió 2.340 detecciones. Con PHP limitado a 128 MiB, la primera respuesta midió 4,62 s. Las dos repeticiones en caché demoraron 198 y 162 ms, y la página 2 demoró 171 ms. Son mediciones locales de una ventana, no una prueba de carga.

El índice espacial conserva una referencia al anillo y guarda números de vértice en vez de duplicar las coordenadas de cada arista. En la repetición local, el pico de memoria reservada bajó de 128 a 94 MiB. La consulta con el nuevo índice tardó 6,35 s en esta muestra con el proveedor externo. Se recuperaron cinco páginas de hasta 500 registros para comparar todas las detecciones, las asignaciones y el resumen con la versión anterior. Coincidieron. Dos páginas de la versión base devolvieron 503 de forma transitoria y coincidieron al reintentar. Las mismas 69 pruebas y 611 aserciones de Focos, Sequías y geometría pasaron con ambos índices.

En el navegador integrado se abrió una detección desde la lista de Focos y se comprobó el texto para compartir, con lugar, hora, coordenadas, confianza y NASA FIRMS. El acceso de Reportes abrió Mediciones, Plantillas cambió entre Activas y Archivadas, y el botón principal del portal llevó a los avisos. El historial público conservó los estados Resuelto y Vencido sin atención.

Mejoras visuales pendientes. En paneles estrechos, dar espacio a los nombres completos de los filtros de Focos y mostrar la fuente junto a la fecha en la ficha. En Mediciones, explicar cómo recuperar puntos disponibles y evitar que Ver registros repita una consulta sin punto. En el portal, mostrar los contadores de avisos junto al acceso al mapa para que el estado operativo se vea desde la portada.


## Regresión de integración · 25/11/2026

Se calculó localmente la combinación de `bada8b8` (PR #195) y `b855c18` (develop). Su árbol web coincide con el del PR antes de la corrección de cierre de sesión descrita abajo. Se usó una copia de SQLite y cuentas ficticias.

- **Focos, San Ignacio (BO070301).** Del 18 al 25 de noviembre a las 17:52 de Bolivia. La API devolvió 345 detecciones y 2 de confianza alta. Coincide con el contraste de los tres clientes registrado en #193.
- **Sequías, Santa Cruz.** Agosto–octubre de 2026, Open-Meteo/ERA5. La API devolvió 326,4 mm y SPI-3 1,8081424489 en la única muestra vigente de los 56 municipios seleccionados. Septiembre devolvió 503, `MUESTRAS_NO_DISPONIBLES`.
- **Inundaciones, San Pedro (BO071005).** 6–18 de diciembre de 2025, NASA OPERA. La API devolvió 2,241 de 2,6766 km² comparables y cobertura de 83,725622 %. La web mostró 2,24 de 2,68 km² y 83,73 %. Para Santa Cruz de la Sierra la API devolvió 404, `TERRITORIO_SIN_COBERTURA`.
- **Forestal, recorte técnico de Santa Rosa.** 2024–2025, Hansen GFC v1.13, copa ≥30 %. La API devolvió 26 píxeles y 1,91489180 ha. El visor técnico mostró 26 y 1,915 ha. La capa departamental tiene un alcance distinto.
- **Aire, latitud −16,5 y longitud −63,5.** A las 21:00 del 25/11 en Bolivia, la API y la ruta web autenticada devolvieron 35 EAQI y PM2,5 de 12,7 µg/m³, CAMS vía Open-Meteo. Sin sesión, la API devolvió 401, `NO_AUTENTICADO`.

En #194 se repitieron en el navegador el mapa y la ficha pública, el rechazo de contraseñas distintas y del correo duplicado, el registro de dos brigadas, el retorno a la ficha, la toma y el cierre como Sin incendio. La segunda cuenta vio que el aviso tenía responsable y carecía de controles para tomarlo o cerrarlo. Sin sesión, el historial mostró el aviso resuelto y el aviso sintético publicado 72 horas y un minuto antes como Vencido sin atención. Al salir del portal se perdía también la sesión web de GeoSur. Se corrigió el cierre de brigada para rotar la sesión y el token CSRF conservando la autenticación web. En el navegador se comprobó que el portal vuelve a la vista pública y GeoSur sigue abriendo Inundaciones sin pedir credenciales. La prueba específica de Laravel pasó con 10 aserciones.

Queda repetir la regresión con el SHA de `develop` una vez fusionado el PR #195.
