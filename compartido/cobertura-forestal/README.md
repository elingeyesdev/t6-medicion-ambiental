# Contrato forestal propuesto — MET-03 / #116

Estado `propuesta-met03-1`, pendiente de Jaziel. [Hoja de decisión](../../docs/forestal116/DECISION-JAZIEL.md) y [método, comprobación y límites](../../docs/forestal116/README.md). Este directorio no implementa un endpoint ni sustituye el OpenAPI de las consultas actuales.

## Consulta prevista

La operación futura recibirá territorio y años inicial/final inclusivos. Solo se admitirán años publicados por la versión adoptada. No se usarán días, meses ni la ventana de siete días del mapa meteorológico. La identidad del resultado y la caché deben incluir producto/versión, criterio completo (base, campo, operador y valor), proveedor/versión/nivel/identificador territorial y ventana solicitada. Una futura geometría local necesita su identificador y versión; cambiar el criterio o el límite no puede reutilizar otra cifra.

El ejemplo disponible contiene el departamento **del proveedor** GADM 4.1 BOL/8 y todos los años 2001–2025; no contiene una equivalencia aprobada con nuestro departamento ni con municipios. Un territorio no soportado deberá producir `sin_cobertura`, no heredar el resultado departamental. No se asigna aún un nombre de ruta hasta adoptar método y geometría.

La primera comprobación propuesta abarca 2001–2025 completos. En futuras subconsultas, `fuente.anios` seguirá indicando la disponibilidad de la versión; `serie` incluirá cada año solicitado y `resumen` solo esa ventana. El verificador y el ejemplo de #116 comprueban exclusivamente la ventana completa. El cálculo local de #147 permite seleccionar años desde esa captura, sin acreditar una API implementada.

## Respuesta propuesta

`ejemplo-propuesto.json` se genera del registro real de acceso. Es un ejemplo para revisar el contrato, con `integrado_en_api: false`, `estado_metodo: pendiente_revision` y `criterio.adoptado: false`.

- `fuente`: producto y versión API fijos (`umd_tree_cover_loss/v1.13`), años disponibles, frecuencia anual, resolución nominal, atribución y licencia de los datos. El metadato del producto remite a GFC-2025-v1.13; su edición raster y la versión GADM son identidades distintas. No usa `latest` ni promete datos de 2026. `resolucion_nominal_m: 30` no define un área constante de píxel: el ejemplo conserva `SUM(area__ha)` del proveedor.
- `criterio`: cobertura base 2000, campo `umd_tree_cover_density_2000__percent`, operador `>=`, valor `30`, `adoptado: false`. La consulta histórica acredita este filtro continuo, que incluye 30 %. No se sustituye por el campo categórico `threshold` ni se equipara a `> 30 %`. Es un parámetro técnico candidato, pendiente de validación en bosque seco; no una definición legal de bosque.
- `territorio`: identificadores del proveedor y su versión separados del catálogo local. La equivalencia pendiente y la ausencia de resultados municipales son explícitas.
- `unidad`: `ha`; `decimales`: `cadena_decimal`. El área informada se transmite como texto decimal para conservar lo recibido; web y Flutter la formatearán para lectura sin presentar decimales como exactitud de medición. La conversión a km² exige dividir por 100; nunca se etiqueta el mismo número como km².
- `serie`: un elemento por año de la ventana solicitada, con `anio`, `estado` y `perdida_ha`; el ejemplo solicita todos los años publicados. Cero válido produce `dato`; un valor nulo o un año ausente produce `no_informado`, con área nula. Se rechazan años fuera de versión, duplicados, negativos, booleanos y números no finitos.
- `resumen`: cantidad de años con dato, años faltantes, subtotal de los datos informados y total del período. Este último solo existe si todos sus años tienen un dato válido. No se suman años de diferentes versiones o criterios.
- `cobertura`: describe disponibilidad de observaciones, no extensión actual de bosque ni densidad de copa. Su fracción válida queda desconocida si la fuente no la publica. Una serie con todos sus años no implica observación espacial completa.
- `capa`: nula en esta muestra; no inventar geometrías de pérdida ni presentar el mapa base como una capa analítica.
- `limites`: significado e incertidumbre que afectan la interpretación del resultado. La respuesta técnica conserva todo; la interfaz usará textos cortos y un detalle útil.

## Estados a implementar cuando corresponda

La futura API distinguirá `dato`, `sin_cobertura`, `producto_no_disponible`, `consulta_invalida` y `error_proveedor`. La autenticación y permisos serán los ya existentes. Un error del proveedor no será una serie vacía exitosa ni se reemplazará por simulación. Los errores de red, HTML en vez de JSON, cambios de esquema o de período requieren revisión; no se completan con cero.

Estos estados y sus respuestas de error deben incorporarse a OpenAPI y contrastarse con Jaziel al implementar H. No hay API ni pruebas Flutter acreditadas por este ejemplo. El procesamiento #117 espera el cierre de G / #115.

## Cálculo temporal local de #147

La [guía del resumen anual](../../docs/forestal147/README.md) explica cómo elegir un intervalo inclusivo, recalcular sus totales con decimales y conservar las identidades y los faltantes. La captura se verifica antes de cada cálculo. Se mantienen integrado_en_api: false, estado_metodo: pendiente_revision y criterio.adoptado: false. Esta preparación no publica el producto ni reemplaza el piloto espacial pendiente.
