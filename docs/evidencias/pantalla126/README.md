# Ampliación del mapa · #126

Comprobación del 31/10/2026 en una copia aislada del mapa, con un punto
simulado de prueba y sus filtros. Se restringió la API nativa solo en un
router temporal ignorado para ejercitar el respaldo CSS. La aplicación
no incorpora esa restricción.

- [Escritorio, 1280 × 720](mapa-ampliado.png): mapa, ficha y ajustes abiertos.
- [Vista estrecha, 450 × 800](vista-estrecha.png): ficha con scroll, leyenda,
  estado y herramientas separados.
- [Poca altura, 900 × 420](vista-baja.png): barra horizontal accesible.
- [Comprobación DOM](comprobacion-dom.json): entrada/salida, rectángulos,
  filtros, punto, paneles, foco, desplazamiento y navegación adaptable.
- [Registro y huellas de archivos](registro.json): fuentes de la prueba y
  capturas, con finales de línea normalizados para las fuentes.

El botón y Escape restauraron el mapa. Al pasar de escritorio a vista
estrecha, el menú cerrado siguió aislado después de salir; al volver al
escritorio recuperó su estado normal. La ficha y los ajustes se conservaron.

**168 pruebas JavaScript correctas**, incluidas las 39 focalizadas.
La entrada nativa real continúa pendiente: este recorrido no la acredita.
La copia de pruebas no modifica la cuenta demo, Flutter ni el servidor 5791.
