# Consulta de agua · preparación de #112

Contrato candidato `agua-0.1-candidata`, pendiente del método #110 y del producto #111. No acredita #112 terminado ni un resultado real. No cambia el contrato M2 ni habilita la entrada pública de inundaciones.

## Rutas actuales

- `GET /api/v1/inundaciones`: conserva el 503 histórico MODULO_NO_DISPONIBLE.
- `GET /api/v1/inundaciones/catalogo`: exige token Sanctum con habilidad cuenta, cuenta activa y permiso datos.consultar. Devuelve solo productos registrados y comprobados. Sin productos devuelve un catálogo vacío, no un análisis sin cambios.
- `GET /api/v1/inundaciones/consulta?territorio=BO070101&referencia=2024-09-01&comparacion=2025-03-07`: misma protección y límite compartido 30/minuto. Lugar PCODE local; fechas UTC YYYY-MM-DD, referencia anterior y ninguna futura. Los siete días del filtro de focos no se trasladan al par de escenas.

Consulta válida con producto registrado: 200 con el JSON del contrato y cabecera X-Producto-SHA256. Consulta sin producto: 503 PRODUCTO_NO_DISPONIBLE, sin métricas. Con catálogo para otro lugar: 404 TERRITORIO_SIN_COBERTURA; un catálogo o producto alterado: 503 PRODUCTO_INVALIDO. Filtros desconocidos/repetidos/vacíos/arrays: 422 FILTROS_INVALIDOS; orden incompatible: 422 FILTROS_INCOMPATIBLES; lugar desconocido: 404 TERRITORIO_NO_DISPONIBLE. Sobres de error comunes con código, mensaje, identificadorConsulta y campos. Sin acceso: 401; sin habilidad: 403. Sin almacenamiento de respuestas. No acepta URLs/rutas arbitrarias ni modo demo; no descarga ni procesa escenas al consultar.

## Respuesta propuesta

`respuesta.schema.json` y `validar.py` describen el candidato. Los tres ejemplos son **pruebas controladas** con figuras sintéticas y superficies de ejemplo. No proceden de Sentinel ni de mediciones reales y ninguna ruta operativa los devuelve. Sirven para preparar web y Flutter; esta tarea no modifica móvil.

Distingue cambio de agua, comparación sin cambios y zona sin datos. Sin datos conserva métricas null; cero indica comparación realizada. Inundación confirmada queda null hasta contar con evidencia independiente. Coordenadas longitud/latitud y superficies km²; el futuro procesador calcula superficies en el CRS/método acordado, nunca el cliente en grados. Un recorte parcial no representa todo el municipio. La cobertura comparable corresponde al área estudiada. Se declara fuente, par de escenas, método y resolución en metros.

Cuatro clases: agua persistente, nueva, retirada y zona no comparable. El candidato admite Polygon acotado; teselas, MultiPolygon, paginación y límites finales se decidirán con #111. El validador comprueba estructura y coherencia básica, no topología, intersecciones ni doble conteo. Esas comprobaciones corresponden al procesamiento.

Antes de responder 200 real faltan el par aprobado y producto completo de #111, huellas y procedencia original, geometría efectiva y cálculo verificado, registro del producto privado revisado, criterio acordado de cobertura insuficiente y ejemplo real completo. Entonces se fijará la versión operativa y el OpenAPI final. El contrato global M2 conserva su comportamiento.

## Repetir

```text
cd web
php artisan test --compact --filter=ConsultaAguaTest
cd ..
python -m pip install jsonschema==4.25.1
python compartido/api/inundaciones/validar.py
```

Fechas de entrega en #109; revisión de Jaziel antes de integrar.

## Registro local preparado

La [guía de productos](../../../docs/agua112/README.md) describe agua:registrar, las versiones por huella, el reemplazo expreso y los límites. El comando y la API rechazan los ejemplos de prueba; no se incluye un producto real por preparar este recorrido. La validación técnica no certifica el método ni la exactitud ambiental.
