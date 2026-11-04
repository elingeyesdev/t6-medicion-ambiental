# Pantalla completa del mapa · #126

Recupera el control del mapa original de EnviroSense en
`#monitoreo-contenedor`, conservando mapa, capas, filtros, ajustes y fichas.
La cabecera y la navegación exterior quedan fuera del área ampliada.

## Comportamiento

- Con soporte disponible, el botón intenta la API nativa dentro del gesto del
  usuario. Su nombre, icono y `aria-pressed` siguen el estado real del navegador,
  incluida la salida con Escape. No cierra una pantalla completa ajena.
- Si falta soporte, el navegador lo restringe o rechaza la entrada, amplía el
  mismo mapa dentro de la página. El botón pasa a **Restaurar mapa**; el botón
  y Escape devuelven el tamaño anterior. Este respaldo conserva la barra del
  navegador y no cambia sus permisos.
- El respaldo aísla temporalmente el exterior para teclado y lectores de
  pantalla, bloquea el desplazamiento de la página y devuelve foco y estilos
  al restaurar. La navegación adaptable conserva su propio estado.
- El aviso de ampliación se anuncia de forma accesible, sin añadir otra tarjeta
  sobre el mapa. Los errores de salida nativa sí permanecen visibles.
- La barra de herramientas es hija directa del contenedor, por encima de la
  ficha: no queda atrapada en el apilado de los filtros. En vistas estrechas o
  bajas pasa a una fila inferior; ficha, estado y leyenda conservan espacio.
- Se mantiene la instancia Leaflet. El ajuste de tamaño comparte planificador
  con `ResizeObserver`; el control no recrea capas ni consulta proveedores.
  Las entradas/salidas pendientes, reinicialización y limpieza están cubiertas.

## Validación

Desde `web`:

```powershell
node --test tests/Js/*.test.mjs
node --check public/js/mapas/pantalla-completa.js
node --check public/js/mapas/monitoreo.js
git diff --check
```

La suite JavaScript final pasó con **168 pruebas**. Incluye seis regresiones
con ambos componentes para los cambios escritorio ↔ móvil y menú abierto,
con salida por botón y Escape. La ejecución acotada pasó **39 pruebas**.
El corte anterior `dc01e83` también pasó **20 pruebas PHP / 159 aserciones** del
mapa, historial y configuración; no se atribuye esa ejecución al nuevo corte.

### Navegador y evidencia

Se usó una copia aislada en 5793, con un punto de prueba simulado en Bolivia y
su ficha abierta. La política `Permissions-Policy: fullscreen=()` se aplicó
solo en un router temporal ignorado para probar el respaldo con restricción
nativa. No forma parte de la aplicación ni del PR.

La comprobación del respaldo incluye entrada, salida por botón y Escape,
conservación del punto y filtros, restauración de foco/desplazamiento y vistas
1280 × 720, 450 × 800 y 900 × 420. Las capturas y el registro se guardan en
[evidencia de #126](evidencias/pantalla126/README.md).

**Pendiente:** comprobar la entrada nativa real, salida por botón/Escape y
conservación de paneles con un gesto normal en navegador. Las pruebas con API
simulada y la ampliación CSS no acreditan ese recorrido. El PR #129 continúa
como borrador hasta completar esa comprobación y pedir la revisión de Jaziel.

Seguimiento [#126](https://github.com/elingeyesdev/t6-medicion-ambiental/issues/126).
No se modificó Flutter, la cuenta demo ni el servidor 5791. M3 sigue abierto;
main conserva M2, aprobado por el ingeniero.
