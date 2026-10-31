# Pantalla completa del mapa · #126

Recupera el control del mapa original de EnviroSense sobre el contenedor
`#monitoreo-contenedor`. Incluye el mapa de GeoSur, filtros, ajustes, fichas,
comparación, controles y leyendas. La cabecera y la navegación exteriores
permanecen fuera de la pantalla completa.

## Comportamiento

- El botón de la barra usa SVG de expandir/contraer, nombre y título adaptables,
  `aria-pressed` y `aria-busy`. La solicitud nativa comienza directamente en
  el gesto del usuario. El estado se obtiene de `document.fullscreenElement`.
- Escape y las salidas externas se reflejan mediante `fullscreenchange`;
  no se intercepta Escape ni se solicita una segunda salida.
- Sin API o permiso del navegador, el control queda deshabilitado y explica
  la indisponibilidad. Una solicitud denegada muestra un aviso local y permite
  reintentar; conserva el estado real del navegador.
- Una pantalla completa ajena nunca se cierra ni se reemplaza. Las operaciones
  pendientes bloquean solicitudes repetidas, incluso al reinicializar el
  control. La limpieza elimina oyentes y solo intenta cerrar este contenedor.
  Si una entrada termina tras cancelar, se limpia sin actualizar la UI retirada.
- Se conserva la misma instancia Leaflet. Solo se llama a su ajuste de tamaño,
  usando el planificador compartido con `ResizeObserver`; no se recrean capas,
  filtros o fichas ni se realizan consultas HTTP desde el control.

## Archivos

- `web/public/js/mapas/pantalla-completa.js`: control independiente de Leaflet.
- `web/public/js/mapas/monitoreo.js`: inicialización, redimensionado compartido y
  limpieza cuando se destruye el mapa.
- `web/resources/views/mapa/index.blade.php`: identificador del contenedor,
  botón y aviso accesible.
- `web/public/css/paginas/monitoreo.css`: dimensiones nativas, iconos, aviso y
  barra horizontal en pantallas completas de poca altura.
- `web/tests/Js/pantalla-completa.test.mjs`: pruebas conductuales de eventos,
  API simulada, errores, concurrencia y limpieza.
- Este documento.

## Validación ejecutada

Desde `web`:

```powershell
node --test tests/Js/pantalla-completa.test.mjs tests/Js/mapa-base.test.mjs
node --check public/js/mapas/pantalla-completa.js
node --check public/js/mapas/monitoreo.js
git diff --check
```

Resultado: **23 pruebas correctas: 20 nuevas y 3 existentes del mapa base**.
Sintaxis y comprobación del diff correctas. No se añadieron pruebas PHP que
reproduzcan el HTML.

## Comprobación visual pendiente

La suite completa del visor pasó: **152 pruebas JavaScript**, incluidas 20 del
control nuevo. También pasaron **20 pruebas PHP del mapa, historial y
configuración, con 159 comprobaciones**. La revisión independiente del cambio
no encontró fallos accionables.

En una copia local aislada se abrió el mapa con un punto de prueba simulado,
ficha y ajustes. El clic automatizado fue rechazado por la API de pantalla
completa y se mostró el aviso; la selección, el mapa y sus paneles se
conservaron. Eso comprueba el caso de rechazo, no una entrada correcta.

El intento de usar el control nativo de Chrome se detuvo automáticamente
porque no pudo identificar con confianza la URL activa. No se cambió la
política de seguridad del navegador. Por eso queda pendiente la comprobación
con un clic normal, salida mediante el botón y Escape, tamaño del mapa y
conservación de los paneles. También falta la comprobación visual en ventana
estrecha y de poca altura. Los casos de API ausente, concurrencia y pantalla
completa ajena están cubiertos con pruebas controladas.

La regla `:fullscreen` ocupa `100vw × 100dvh`; la barra se vuelve horizontal
con alturas de hasta 480 px. El control conserva la instancia Leaflet y las
referencias existentes. El PR se prepara como borrador hasta terminar la
comprobación visual y solicitar la revisión de Jaziel.

Seguimiento [#126](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/126).
No se modificó Flutter ni la base del servidor 5791. M3 sigue abierto y main
conserva el corte aprobado por el ingeniero.
