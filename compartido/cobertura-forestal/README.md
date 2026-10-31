# Contrato forestal propuesto — MET-03 / #116

Estado `propuesta-met03-1`, pendiente de Jaziel. [Método, comprobación y límites](../../docs/forestal116/README.md). Este directorio no implementa un endpoint ni sustituye el OpenAPI de las consultas actuales.

## Consulta prevista

La operación futura recibirá territorio y años inicial/final inclusivos. Solo se admitirán años publicados por la versión adoptada. No se usarán días, meses ni la ventana de siete días del mapa meteorológico. El período y la versión deben formar parte de la identidad del resultado y de su caché.

El ejemplo disponible contiene el departamento **del proveedor** GADM 4.1 BOL/8 y todos los años 2001–2025; no contiene una equivalencia aprobada con nuestro departamento ni con municipios. Un territorio no soportado deberá producir `sin_cobertura`, no heredar el resultado departamental. No se asigna aún un nombre de ruta hasta adoptar método y geometría.

## Respuesta propuesta

`ejemplo-propuesto.json` se genera del registro real de acceso. Es un ejemplo para revisar el contrato, con `integrado_en_api: false`, `estado_metodo: pendiente_revision` y `criterio.adoptado: false`.

- `fuente`: producto y versión fijos, años, frecuencia, resolución nominal, atribución y licencia de los datos. No usa `latest` ni promete datos de 2026.
- `criterio`: año de cobertura base, campo, operador, valor y estado de adopción. El parámetro del ensayo no es una definición legal de bosque.
- `territorio`: identificadores del proveedor y su versión separados del catálogo local. La equivalencia pendiente y la ausencia de resultados municipales son explícitas.
- `unidad`: `ha`; `decimales`: `cadena_decimal`. El área informada se transmite como texto decimal para conservar lo recibido; web y Flutter la formatearán para lectura sin presentar decimales como exactitud de medición. Nunca se etiqueta el mismo número como km².
- `serie`: un elemento por año publicado, con `anio`, `estado` y `perdida_ha`. Cero válido produce `dato`; un valor nulo o un año ausente produce `no_informado`, con área nula. Se rechazan años fuera de versión, duplicados, negativos, booleanos y números no finitos.
- `resumen`: cantidad de años con dato, años faltantes, subtotal de los datos informados y total del período. Este último solo existe si todos sus años tienen un dato válido. No se suman años de diferentes versiones o criterios.
- `cobertura`: fracción de observaciones válidas desconocida si la fuente no la publica. Una serie con todos sus años no implica observación espacial completa.
- `capa`: nula en esta muestra; no inventar geometrías de pérdida ni presentar el mapa base como una capa analítica.
- `limites`: significado e incertidumbre que afectan la interpretación del resultado. La respuesta técnica conserva todo; la interfaz usará textos cortos y un detalle útil.

## Estados a implementar cuando corresponda

La futura API distinguirá `dato`, `sin_cobertura`, `producto_no_disponible`, `consulta_invalida` y `error_proveedor`. La autenticación y permisos serán los ya existentes. Un error del proveedor no será una serie vacía exitosa ni se reemplazará por simulación. Los errores de red, HTML en vez de JSON, cambios de esquema o de período requieren revisión; no se completan con cero.

Estos estados y sus respuestas de error deben incorporarse a OpenAPI y contrastarse con Jaziel al implementar H. No hay API ni pruebas Flutter acreditadas por este ejemplo. El procesamiento #117 espera el cierre de G / #115.
