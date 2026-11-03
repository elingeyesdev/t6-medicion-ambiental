# Guion de demostración web M3 · 03/11/2026

Copia local de comprobación: 7163a0e94be04bc49a6ad1312364617909da740b. Reúne los PR publicados para probarlos juntos; no es un corte aprobado ni una fusión remota. La revisión de Jaziel y la aceptación del ingeniero siguen pendientes.

## Recorrido de 8 a 10 minutos

1. **Inicio y acceso (1 min).** Explicar: “Adaptamos EnviroSense a la tecnología del Planner y conservamos sus recorridos. GeoSur incorpora nuestros análisis dentro de ese sistema”. Entrar primero como Visualizador; mostrar que Administración está restringida y se puede volver al mapa.
2. **Mapa (2 min).** Elegir Bolivia y origen simulado; abrir Santa Cruz. Activar capas A/B y comparar temperatura del 03/11, 05:00 y 08:00 de Bolivia. Explicar que la capa GFS es una estimación y la ficha es una lectura simulada distinta. Ampliar con el botón y salir con Escape sin perder la selección.
3. **Simulaciones y mediciones (2 min).** Con una cuenta de Analista, abrir el escenario de comparación Santa Cruz/La Paz. Reiniciar, reproducir y esperar la confirmación activa antes de pausar. Mostrar el historial de esas lecturas: siguen etiquetadas como simuladas. El recorrido global también tiene preparación, inicio y pausa.
4. **Reportes (1 min).** Mostrar el reporte de 50 lecturas simuladas, sus dos series, gráfico y resumen. Abrir el PDF y Excel descargados por la aplicación; no llamarlos observaciones.
5. **Sequías y focos (2 min).** Consultar septiembre de 2024: 114,5 mm, SPI-3 aproximado -0,64 y condición Normal en la muestra. Señalar cobertura de 1 de 56 municipios y que la celda está en Porongo aunque la consulta sea Santa Cruz. En focos, mostrar 24 h / 7 días y el límite de futuro. Esta consulta real devolvió 0 detecciones; no prueba ausencia de incendios.
6. **Cuenta y avisos (1 min).** Explicar que las unidades se guardan por cuenta y persistieron al recargar. La bandeja respeta consentimiento y preferencias. Los canales externos están apagados en esta copia; no presentar una entrega por correo/WhatsApp como comprobada.

## Lo que ambos debemos saber

- Diferenciar observaciones, estimaciones de modelos y simulaciones. No se mezclan para aparentar cobertura.
- El catálogo base contempla Sudamérica; focos y sequías tienen el alcance comprobado de Santa Cruz. Una muestra no representa todo un municipio.
- Los roles de EnviroSense se conservan: Visualizador consulta; Analista gestiona las funciones ambientales autorizadas; Administrador añade gestión de cuentas.
- Josué comprueba web/API y Jaziel desarrolla y verifica Flutter. Esta guía no acredita una prueba nueva del móvil.

## Preparación y resultados

[Resultado del recorrido](resultado.json) · [20 comprobaciones visuales](recorrido-ui.json) · [backend](backend-resultado.json) · [corrección 403](backend-fix136.json).

- En 92615f6: 509 casos PHP elegibles / 6273 aserciones; 14 casos de dos clases que necesitan procesos quedaron excluidos, sin contarlos aprobados. Pint: 255 archivos.
- En 7163a0e, tras corregir 403: 4 regresiones / 38 aserciones correctas; no se volvió a atribuir la suite de 509 a ese SHA.
- JavaScript: 181 correctas; el árbol JS no cambió con la página 403.
- Se probaron PDF y Excel reales del reporte guardado. [PDF de la aplicación](reporte-simulado.pdf) · [Excel](reporte-simulado.xlsx).
- Se reutilizaron capas NOAA publicadas; la base ERA5 histórica se conservó y solo se renovó la captura del trimestre objetivo. No hubo descarga de imágenes pesadas ni conexiones a dispositivos externos.

## Cómo se reunió la copia

Partió de #133 (08aa6c4). Se combinaron localmente #134 (d59cd75), #135 (6c0df1c), #129 (637e846), #96 (b7ed148) y #94 (329dbe7), conservando ambos manejadores de errores. La integración local quedó en 92615f6. Se aplicó después la corrección fuente de #136 (c2b520d), obteniendo 7163a0e. Los PR históricos #104/#106/#108/#121/#125/#128 ya son ancestros de #133.

Dependencias, versión base y lista de commits están en resultado.json. No se publicó esta rama como versión aprobada ni se cambió main. Las cuentas y bases de ensayo son privadas, distintas de la demo habitual; el paquete no incluye credenciales. Para repetir la prueba hace falta preparar una nueva base de ensayo y sus datos, no copiar sesiones ni cuentas del original.

## Pendientes que debemos mencionar

- Revisión e integración de los PR con Jaziel y nuevo recorrido sobre el SHA aceptado.
- Validación móvil actual, método/avance de inundaciones y aceptación académica.
- Conexiones reales de sensores y canales externos, histórico original saneado y productos raster aún identificados como pendientes.
- La prueba manual abrió Plantillas/Alertas y sus estados vacíos; no creó plantillas ni reconoció alertas. Los casos de backend se conservan aparte.

La guía PDF enlazada documenta el ensayo local, no reemplaza el corte final que se presentará después de las aprobaciones.
