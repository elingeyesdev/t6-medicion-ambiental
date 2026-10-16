# GEO-01 · Catálogo y límites territoriales

Issue #16, rama `feature/16-catalogo-territorial`, M2, EDT 3.1 / actividad C. Preparación técnica de Josué Padilla, 16/10/2026. **La revisión y el acuerdo de Jaziel permanecen pendientes**.

## Resultado preparado

Se seleccionaron de la fuente OCHA/HDX COD-AB Bolivia v02 las 72 unidades de Santa Cruz: 1 departamento, 15 provincias y 56 unidades de nivel municipal. Cada registro conserva el PCODE y padre de origen, su geometría completa y la procedencia. Se contrastaron uno a uno los 56 nombres con el portal INE del Censo 2024, manteniendo también los nombres originales de la geometría y las denominaciones de autonomía de Charagua/Gutiérrez.

Los PCODE son de OCHA/HDX, no códigos oficiales INE. Las coordenadas publicadas datan de 2013; el publicador registra validez para uso humanitario desde 2024 y revisión de 2025. Esas fechas permanecen visibles en datos, documentación y herramienta de revisión.

## Código y recursos

- `compartido/territorios/datos/`: catálogo y tres capas GeoJSON.
- `compartido/territorios/fuentes/`: origen, licencia, fechas, conciliación nominal e instantánea de nombres.
- `web/app/Services/Territorios/CatalogoTerritorial.php`: búsqueda, listado, relación padre-hijo, resolución de filtros y recuperación de geometrías. Sin dependencia de Laravel, base de datos o servicios externos.
- `web/app/Services/Territorios/FiltroTerritorialInvalido.php`: errores de selección explícitos, sin sustitución por proximidad.
- `compartido/territorios/herramientas/`: reconstrucción determinista y validación geométrica completa.
- `compartido/territorios/revisar_catalogo.php`: herramienta local para revisar la propuesta y comprobar selecciones; no publica la API ambiental de #18.

El catálogo compartido puede consumirse desde Flutter; la clase PHP se reserva al backend. No se duplicó el catálogo como listas escritas a mano en cada cliente.

## Pruebas ejecutadas

- **244 comprobaciones PHP**: cobertura completa, todas las 56 relaciones municipales, inferencia de padres, rechazo de cruces incompatibles, IDs ajenos/vacíos, niveles incorrectos y ausencia de aceptación inventada.
- **72 geometrías válidas**, con los 323.254 pares longitud/latitud comprobados y correspondencia unívoca con sus registros.
- **16 divisiones completas**: unión de las 15 provincias respecto del departamento y unión de municipios de cada provincia. Comparaciones en EPSG:6933; residuo numérico admitido de 1 m², sin atribuirle precisión cartográfica.
- Coordenadas de las **72 geometrías idénticas numéricamente a la fuente ZIP fijada**. No se repararon, simplificaron o recortaron polígonos.
- Reconstrucción desde el mismo ZIP: manifiesto y datos idénticos byte a byte.
- **Seis consultas HTTP locales** verificadas: sin filtros, selección válida, selección incompatible, inferencia de provincia, ID de otro departamento y parámetro de tipo array. Se registraron 200/422 según el caso.
- Recorrido real en Chrome: selección válida, rechazo de Warnes con Andrés Ibáñez e inferencia de Andrés Ibáñez al seleccionar Cotoca.
- El servidor del #15 conserva respuesta HTTP 200 en `/api/salud` durante la preparación aislada de #16.

Entorno local: Windows, PHP 8.3.30, Python 3.12, Shapely 2.1.2 / GEOS registrado en el informe, PyProj 3.7.2 y NumPy 2.3.5. CI verifica reglas en PHP 8.2/8.3 y todas las geometrías en Python 3.12; sus resultados remotos constan en el PR.

## Cómo probar manualmente

Desde la raíz de esta rama, `php compartido/territorios/comprobar.php` debe imprimir los resultados correctos y la revisión pendiente. Iniciar con `.\revisar-territorios.ps1` y abrir **http://127.0.0.1:8001/revisar_catalogo.php**.

1. Sin filtros: Santa Cruz `BO07`.
2. Andrés Ibáñez `BO0701` + Santa Cruz de La Sierra `BO070101`: selección válida.
3. Andrés Ibáñez `BO0701` + Warnes `BO070201`: error `FILTROS_INCOMPATIBLES`.
4. Sin provincia + Cotoca `BO070102`: provincia obtenida desde el catálogo: Andrés Ibáñez.
5. Abrir Cordillera: siete unidades con las denominaciones INE de autonomía visibles.

## Evidencia

- [Comprobaciones PHP](evidencias/geo01/comprobaciones.txt).
- [Validación geométrica completa](evidencias/geo01/validacion_geometrica.json).
- [Resultados HTTP](evidencias/geo01/http.json).
- [Selección válida](evidencias/geo01/seleccion_valida.png).
- [Rechazo de selección incompatible](evidencias/geo01/seleccion_incompatible.png).

## Decisión pendiente

Se mantienen para revisión del equipo la adopción de los PCODE y de esta versión de los límites, la representación del nivel municipal con autonomías y la regla final de puntos en fronteras compartidas en #18. Seguimiento explícito en [Decisiones de M2](DECISIONES_M2.md), #14 y #16. Las verificaciones técnicas no sustituyen esa revisión ni acreditan deslinde legal vigente, exactitud de eventos ambientales o integración del cliente móvil.
