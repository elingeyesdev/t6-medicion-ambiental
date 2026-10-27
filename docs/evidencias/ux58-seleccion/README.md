# #58 · Selección que lleva al mapa

Comprobación real del 27/10/2026 con la consulta del 26 al 27/10, hora de Bolivia: 9 detecciones y un grupo de 8.

- `01-charagua.png`: primera detección elegida desde Ubicaciones, con marco centrado en la llama.
- `02-concepcion.png`: al elegir la segunda fila el mapa muestra otra zona, Concepción, y cambia a Detección 2 de 9. El mapa queda centrado en la elección; el marco permanece en el área visible mientras cambia el terreno.
- `03-desde-grupo.png`: elección de otra detección desde el grupo, con la fila marcada y la ubicación visible entre los paneles.
- `04-enfoque-movil.png`: pantalla real después de elegir desde la lista en viewport de 390 × 844. La página lleva al mapa y deja todo su alto visible. No hay desbordamiento horizontal. También se eligió desde un grupo y se comprobó que se cierra para no tapar la selección.

La selección usa un marco blanco y naranja de 64 px alrededor del centro visual de la llama, sin alterar la coordenada de su pie. Se reemplaza el marco y la etiqueta al cambiar de registro. La etiqueta indica qué registro se eligió; coordenadas repetidas siguen en el mismo lugar real. El encabezado de filtros ahora tiene un chevrón SVG en un recuadro y cambia al abrir o cerrar.

Pasaron 46 pruebas JavaScript. Los casos verifican que cada elección encuadra su coordenada, que un zoom mayor se conserva, que los paneles dejan sitio y que un zoom manual no vuelve a encuadrar una selección anterior. Las selecciones repetidas y el cierre dejan un solo marco/etiqueta o ninguno. También pasaron 24 pruebas Laravel de acceso y visor (193 comprobaciones) y formato. Los archivos de Tabler y los datos ambientales no se modificaron. Revisión en PR #55.
