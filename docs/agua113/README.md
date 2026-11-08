# Revisión web de agua · #113

Preparación sobre develop, con el contrato #112 del PR #138 ya integrado. Todavía no incluye el resultado ambiental real de #111. Conserva el mapa, menú, botones, cuentas y tipografía del frente recuperado de EnviroSense. No añade una entrada pública pendiente.

## Abrir localmente

En la copia de esta rama, configurar APP_ENV=local y AGUA_REVISION_LOCAL=true en el entorno local, iniciar Laravel con la configuración habitual y entrar con una cuenta activa. Abrir /revision/agua. No se cambia la cuenta demo ni su clave. Sin la opción, o en producción aunque la opción esté activa, devuelve 404. /mapa normal no incorpora los ejemplos. Los assets no contienen los ejemplos; se incluyen solo en la vista local autenticada.

## Comprobar

Cambiar entre Cambios de agua, Sin cambios, Sin datos comparables y Error de consulta. Los tres primeros usan figuras controladas del contrato; el error también es controlado y borra el resultado anterior. El aviso Datos de prueba permanece incluso al cerrar el panel. Seleccionar una ubicación centra su figura y resalta la selección. Cerrar/reabrir conserva el mapa. Las clases se diferencian por color y borde; el área sin comparación no equivale a cero agua. No se calculan superficies en el navegador.

Las capturas en ../evidencias/agua113 corresponden a HTML renderizado por Laravel con cuenta de prueba, servido por un servidor estático temporal sin proveedor cartográfico configurado. Por eso el fondo se ve neutro; se verifican las figuras, selección y paneles, no la carga de imágenes externas ni un resultado real. Se revisaron escritorio y 390×844, selección, cierre/reapertura, falta de datos y error.

Pruebas: php artisan test --compact --filter="RevisionAguaTest|ConsultaAguaTest|BaseWebTest"; node --test tests/Js/agua.test.mjs desde web.

Faltan producto real, catálogo de pares aprobados, consulta operativa y prueba de punta a punta. No se da #113 por terminado ni se habilita Inundaciones para usuarios. Flutter queda a cargo de Jaziel.
