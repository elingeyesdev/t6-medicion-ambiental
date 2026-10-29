# Revisión de la comparación de sequías

29/10/2026. Revisé el avance de Jaziel en el PR #88 después de integrar #87. La evidencia de su equipo y la captura web local se identifican por separado: una publicación recalculada puede tener otra entrada de lluvia y no debe presentarse como el mismo ensayo.

## Ajustes encontrados

El índice de aristas acelera la consulta territorial, pero su caché comparaba solo cinco vértices del primer contorno. Dos geometrías distintas podían compartir esa firma. Lo reproduje cambiando un hueco, otra parte de un multipolígono y un vértice intermedio: fallaron los tres casos. Ahora se conserva y compara la geometría completa antes de reutilizar el índice. Las cuatro pruebas pasan, con 193 comprobaciones, incluidos bordes y latitudes negativas. Se mantiene el descarte rápido de aristas y la regla original de límites.

El cliente móvil también intentaba repetir una petición fallida contra otro servidor local. Retiré ese cambio automático: la dirección se configura explícitamente y un error no repite registros, cambios de cuenta o credenciales en otra dirección. Añadí pruebas de GET, POST y PUT. Se conserva el tiempo de espera ampliado y la conexión local por adb reverse.

La configuración Android permite HTTP para los destinos locales declarados y conserva HTTPS para los demás. Referencia: [configuración de red de Android](https://developer.android.com/privacy-and-security/security-config). No se cambiaron las autoridades de confianza ni los certificados.

Corregí el rótulo del trimestre, que decía Bolivia aunque el cálculo usa meses UTC, y mejoré la lectura de la ficha. Los valores SPI, la lluvia, los faltantes y la cobertura no se alteraron.

Completé la captura web con sesión y renové las dos entradas públicas de lluvia. La publicación del 29/10 coincide con la entrada de Jaziel y el contraste independiente SciPy 1.18.1: diferencia `2.886579864025407e-15`. Quedaron guardados los treinta acumulados, la respuesta general y las capturas en esta carpeta. La cobertura es un municipio con datos de 56; el resto conserva sus faltantes. El oráculo antiguo sigue separado.

Laravel: 383 pruebas, 3733 comprobaciones correctas. JavaScript de sequías: ocho pruebas correctas. El análisis y las pruebas Flutter pasaron en CI, pero la compilación Android falló porque el manifiesto debug declara HTTP local y el atributo de main añadido en mi revisión entra en conflicto. Dejé a Jaziel el ajuste concreto en el PR; él conserva la responsabilidad del código móvil. Falta esa compilación correcta y revisar su siguiente commit para aprobar #88 y cerrar #39.
