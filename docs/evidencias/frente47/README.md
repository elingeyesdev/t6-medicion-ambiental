# Volver al frente de EnviroSense · tarea #47

Se recorrió el original antes de trasladar su presentación. El pedido es conservar EnviroSense como sistema principal, con la marca GeoSur y nuestros análisis dentro. Esta corrección reemplaza la portada verde y el panel con un segundo lateral permanente.

## Referencia revisada

Fuente: `referencias/envirosense/codigo`, commit de origen `ab4f93a48c2b8a082909feb3310db35296f6cbaa`. Se ejecutó la copia de escritorio en puertos 5173/3000, con PostgreSQL temporal en 5433, una cuenta ficticia y sin ingestión automática ni bots. No se importaron cuentas, contactos o lecturas privadas a GeoSur.

Se visitaron inicio, ingreso, registro, mapa, capas/preferencias, simulación por territorio, reportes (dashboard, atmósfera e historial), administración, alertas, notificaciones y plantillas. Las capturas del original prueban la referencia visual, no el funcionamiento de servicios externos ni la validez de sus datos.

## Qué se trasladó

- Inicio: imagen meteorológica original, ondas, navegación, secciones y paleta azul/turquesa. Las cifras de red y las promesas de IA se sustituyeron por información comprobable.
- Acceso y registro: composición dividida, imagen de clima y formularios del referente. El registro permite elegir ubicación y canales disponibles; se guarda en una transacción con consentimiento explícito. Un error no deja una cuenta creada a medias. Sin JavaScript conserva el registro simple y permite completar las preferencias al ingresar.
- Panel: Space Grotesk local, barra lateral de 232 px, cabecera de 56 px, iconos del origen y colores de ambos temas. El símbolo sigue siendo el GeoSur elegido por Josué.
- Mapa: lienzo completo, navegación flotante, herramientas, ajustes con Capas/Preferencias, búsqueda, unidades y fondos. Claro usa `light-v11`; se conserva la preferencia previa del usuario. La rueda sigue controlando el mapa.
- Simulaciones: entrada propia inmediatamente después del mapa, dentro de Monitoreo; consola, escenarios por zona, intensidad, reproducción y datos. Un Visualizador consulta el recorrido compartido; Analista/Administrador conservan los controles. La consulta no expone las sesiones privadas de otras cuentas.
- Reportes, plantillas, usuarios, cuenta, alertas y avisos: cabeceras, pestañas, filtros y tablas siguiendo el referente. Los gráficos y exportaciones usan cortes guardados; no se rellenan con lecturas de ejemplo.
- Focos y sequías: permanecen dentro de Análisis GeoSur. No sustituyen al mapa principal.

## Código y plantilla

Se mantiene Laravel/Blade según el Planner. No se ejecuta el React de referencia dentro de Laravel: sus pantallas, composición y recursos se trasladaron a componentes Blade. Por eso hay adaptación de marcado y controles, no equivalencia literal de DOM ni de todos los estados visuales del original.

- `web/resources/views/layouts/`: estructura pública, acceso y panel.
- `web/resources/views/components/publico/enviro-*`: piezas de la portada y acceso.
- `web/resources/views/components/panel/`: menú, cabecera, marca e iconos.
- `web/public/css/envirosense/`: fuente y presentación global del referente; `css/panel/` conserva la estructura y `css/paginas/` la presentación de cada pantalla.
- `web/public/js/mapas/interfaz-original.js`: apertura/cierre y pestañas de ajustes. Las consultas conservan sus módulos existentes.
- `web/public/js/simulaciones/presentacion.js`: tarjetas y pestañas; sin nuevas consultas ni permisos.
- `web/public/img/envirosense/`: imágenes e iconos del código original. Space Grotesk conserva su licencia SIL OFL en `web/public/fonts/envirosense/OFL.txt`.

**Tabler 1.7.1 sigue siendo la base de componentes internos**, sin modificar sus archivos. La portada y el acceso ahora recuperan el diseño propio de EnviroSense; no se presentan como una plantilla comercial nueva.

## Comprobaciones realizadas

- 480 pruebas PHP, 5782 aserciones, con SQLite de pruebas y vistas compiladas separadas de la aplicación local.
- 99 pruebas JavaScript, incluida la consola sin formulario de Analista.
- Pint y comprobación de sintaxis de los scripts nuevos.
- Inicio de sesión con la demo existente; navegación de mapa, ajustes, fondo Claro, país, simulación de consulta, reportes, plantillas, alertas y preferencias de avisos.
- Vista web a 390 × 844 y escritorio; controles del mapa sin superposición con la leyenda. No equivale a probar Flutter.
- Ningún error de JavaScript observado en los recorridos comprobados de portada y simulación de consulta.

Los permisos de creación, ejecución y edición se verifican en pruebas aisladas. La sesión demo del navegador permanece Visualizador; no se elevó para producir capturas de administración ni se crearon datos para llenar pantallas vacías.

## Capturas

- [Inicio original](original-inicio.png) y [GeoSur](geosur-inicio.png).
- [Acceso original](original-acceso.png) y [GeoSur](geosur-acceso.png).
- [Registro GeoSur](geosur-registro.png), con selección de ubicación comprobada y canales no configurados deshabilitados.
- [Mapa original](original-mapa.png), [controles originales](original-controles-mapa.png) y [GeoSur](geosur-mapa.png).
- [Simulación territorial original](original-simulacion-territorio.png) y [consulta GeoSur](geosur-simulaciones.png). Son estados y permisos diferentes; no prueban igualdad de ejecución.
- [Reportes originales](original-reportes-dashboard.png) y [GeoSur sin cortes guardados](geosur-reportes.png).
- [Plantillas originales](original-plantillas.png) y [alertas GeoSur](geosur-alertas.png).
- [Inicio estrecho](geosur-inicio-estrecho.png) y [mapa estrecho](geosur-mapa-estrecho.png).

## Lo que todavía requiere seguimiento

La corrección visual no acredita toda la migración operativa. Siguen los insumos y conexiones descritos en `docs/entrega-m3/README.md`: AQI raster, productores/actualización, historial autorizado, sensores reales y canales externos. Las capas GFS locales son cortes fijos. La interfaz conserva estados sin datos cuando corresponde. El chatbot está excluido por decisión de Josué.

El cambio requiere revisión de Jaziel. Se propone sobre el candidato #96; M2 permanece en main y Flutter no se modifica.
