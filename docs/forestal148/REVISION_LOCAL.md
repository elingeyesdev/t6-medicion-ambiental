# Lectura local de revisión forestal #148

Servicio interno: RepositorioRevisionForestal(string $directorio), consultar(string $id, int $desde, int $hasta): array. El directorio privado contiene $id/manifiesto.json, resumen.json y perdida.geojson; $id es SHA-256 de los bytes exactos del manifiesto, incluido LF. No registra endpoints ni modifica configuración o móvil.

El manifiesto exige formato revision-forestal-1, estado revision_tecnica, identidad {fuente_version, tesela, captura_sha256, criterio, ventana_pixeles, consulta} y los dos hashes en archivos. Se comprueban ambos archivos antes de filtrar, con identidad estructural y tipos estrictos contra el resumen técnico real. No usa el contrato propuesto de ResumenForestal.

El lector solo admite GFC-2025-v1.13 / 10S_070W, cuadrícula EPSG:4326 de área, 40000×40000, paso 0.00025, oeste -70 y norte -10. Exige los 25 años numéricos únicos 2001–2025 y consulta inclusiva dentro de esos límites. Una consulta debe estar completamente dentro de la consulta del paquete; no crea años ausentes ni transforma desconocido en cero.

Se rechazan rutas/IDs inseguros, enlaces simbólicos y junctions (también ancestros), archivos no regulares, JSON no objeto donde se espera objeto, claves JSON repetidas, tipos incorrectos y datos no finitos. Límites: manifiesto 64 KiB, resumen 2 MiB, capa 32 MiB, ventana/capa hasta 100000 celdas, profundidad JSON 64. La lectura acotada contrasta metadatos del descriptor y del archivo antes/después. El almacenamiento debe ser privado y mantenerse sin escritores concurrentes; estas comprobaciones no proporcionan una apertura atómica sin seguimiento de enlaces.

Valida toda la capa antes de seleccionar años: IDs fila-columna únicos globalmente, año/área, conteos, Polygon de una celda cerrada, coordenadas correspondientes a su ID y dentro de bbox. Contrasta suma de celdas por año con la serie: tolerancia de (número de celdas + 1) × 0.5e-8 ha más 1e-9 para representación binaria. El total geodésico original se contrasta con tolerancia equivalente por número de años. No se realiza aquí un nuevo cálculo geodésico ni contraste raster independiente.

La respuesta contiene exactamente {id, revision: true, resumen, capa}. Conserva el resumen y sus metadatos salvo consulta, serie, total_periodo_ha y conteos_pixeles.perdida_periodo; la capa usa el mismo intervalo. total_periodo_ha es suma decimal de la serie anual, como cadena con ocho decimales, sin acumulación float. Conserva fuente/ETags, bbox, cobertura desconocida y estado recorte_tecnico_no_validado, integrado_en_api=false y criterio.adoptado=false. Integridad local no equivale a autenticidad científica ni aprobación; no añade capacidades ni equivalencias municipales.

Errores internos: FILTROS_INVALIDOS (422) para ID/intervalo inválido o consulta fuera del paquete; PRODUCTO_INVALIDO (503) para paquete ausente, inseguro o inconsistente. No incluyen rutas privadas.

## Verificación local

- PHPUnit 11.5.57 / PHP 8.3.30: 14 pruebas, 434 aserciones, 1 omitida porque PHP no pudo crear enlaces simbólicos en este Windows. Datos sintéticos controlados, áreas ficticias y sin producto aprobado. Incluye 2026 y versión distinta con identidad/manifiesto/hashes recalculados, corrupción fuera del intervalo, ceros/desconocidos, redondeos, tamaños, tipos y duplicados.
- Comprobación adicional mediante Node fs y PHP: junctions con destino válido rechazados en base, paquete y ancestro, sin modificar el paquete real. La prueba de enlaces simbólicos de archivo queda pendiente en un entorno que permita crearlos desde PHP.
- Paquete real 78a62654465d330e4aa0f241d74e237bfa65de709dee827099dc8594d4e88730, en C:/Users/cjosu/Desktop/GeoCruz-oficial/tmp/forestal-revision147-20261109/paquetes: 25 años y 1118 celdas. consultar(2024, 2025): 26 celdas y total 1.91489180 ha; cobertura observacional nula y estado técnico conservados.
- En el intervalo completo, suma anual redondeada: 82.34172107 ha; total geodésico original: 82.34172106 ha; suma de celdas redondeadas: 82.34172115 ha. Para 2024–2025, serie y celdas suman 1.91489180 ha. Los redondeos independientes pueden diferir; estos decimales no representan exactitud de medición ni una estimación definitiva.

Ejecución sin ventanas desde Node REPL, importando file:///C:/Users/cjosu/.codex/scripts/run-hidden.mjs?v=forest-review y usando runHidden con:

- Ejecutable: C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe
- Argumentos: ["-c", "C:/Users/cjosu/Desktop/GeoCruz/tmp/tarea9/recorrido/php.ini", "vendor/phpunit/phpunit/phpunit", "--bootstrap", "C:/Users/cjosu/Desktop/GeoCruz-oficial/tmp/qa-forestal148-20261108/bootstrap.php", "--filter", "RepositorioRevisionForestal", "--no-progress"]
- cwd: C:/Users/cjosu/Desktop/GeoCruz-oficial/tmp/api-forestal148-20261108/web

Sin commit ni push.
