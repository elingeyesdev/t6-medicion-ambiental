# Preparación de filtros forestales para #148

Este avance lee y valida la entrada de una consulta futura. Está basado en la preparación de #147, PR #157. No registra un endpoint ni publica el recorte técnico como producto aprobado.

## Entrada y disponibilidad

ParametrosConsultaForestal::leer recibe un Request, una DisponibilidadForestal y la lista de territorios publicados para esa versión. Solo admite territorio, desde y hasta, obligatorios y con un valor textual. Reutiliza ParametrosConsulta::leer para detectar parámetros repetidos, desconocidos, arrays y valores vacíos, incluso después del colapso de claves de PHP.

Los años deben tener cuatro cifras ASCII. El intervalo es inclusivo y todos sus años, no solo los extremos, deben estar disponibles en la versión recibida. No depende del reloj ni limita una versión nueva con fechas fijas. La disponibilidad es inmutable, con versión no vacía y años enteros únicos. Un año publicado no significa por sí solo observación completa.

El territorio debe coincidir exactamente con la lista aportada por el llamador. No hereda cifras departamentales para municipios ni convierte identificadores GADM a códigos locales. El resultado solo contiene territorio y los dos años normalizados. La procedencia y la relación entre producto, versión y territorio todavía deben comprobarse al integrar el catálogo.

## Errores propuestos

- FILTROS_INVALIDOS, 422: entrada ausente, ambigua, año con formato incorrecto, intervalo invertido o año no publicado.
- TERRITORIO_SIN_COBERTURA, 404: identidad no publicada para la versión.
- PRODUCTO_NO_DISPONIBLE, 503: disponibilidad ausente.

Son excepciones del servicio interno. No acreditan respuestas HTTP, autenticación o permisos probados. El método, producto, catálogo, ruta y contrato público siguen pendientes de revisión.

## Verificación

Pasaron 112 casos y 241 aserciones de PHPUnit. Se comprobaron los años extremos, disponibilidad variable y con huecos, días y meses, exponentes, booleanos, arrays, parámetros repetidos o codificados, territorio exacto y ausencia de disponibilidad. Los territorios de prueba son ficticios.

Desde web, con las dependencias instaladas:

~~~powershell
php vendor/phpunit/phpunit/phpunit --filter ParametrosConsultaForestal --no-progress
~~~

Las pruebas están incluidas en la suite Feature y no arrancan Laravel ni usan red o procesos desde PHP. La ejecución local reutilizó vendor mediante un bootstrap privado que apunta a las clases de este worktree. Se comprobaron sintaxis y formato con Pint. No se modificó móvil.

Las capturas y los resultados del recorte permanecen en docs/forestal147. Este avance no completa #148 ni sustituye la aceptación del método y del piloto.

## Resumen anual preparado

ResumenForestal::acotar recibe la serie del contrato propuesto y años inclusivos. Verifica años, duplicados, estados, unidad y cadenas decimales antes de seleccionar la ventana. Conserva los metadatos y el estado de revisión; no acredita la procedencia ni la aceptación científica del producto. El catálogo deberá validar esas partes antes de invocarlo.

Las áreas se suman como dígitos decimales, sin float ni nuevas extensiones. La entrada admite hasta 40 cifras enteras y 30 decimales, sin signo, exponentes ni ceros iniciales ambiguos. Se conserva la escala decimal. Un cero informado produce un total cero. Un año ausente o no_informado produce un total nulo y solo el subtotal conocido; si no hay datos, ambos quedan nulos. No se reutiliza el total anterior del producto. Una serie corrupta falla con PRODUCTO_INVALIDO incluso si el problema queda fuera del intervalo elegido.

Pasaron 118 casos y 308 aserciones de PHP. Las seis pruebas nuevas contrastan los resultados guardados de Python para 2001–2025 y 2024–2025, conservación de identidad, cero, faltantes, duplicados, años ajenos, unidades incorrectas y suma exacta por encima de 28 cifras. No se añadieron rutas ni se cambió el estado de aprobación del producto.
