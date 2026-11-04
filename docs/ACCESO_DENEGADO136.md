# Acceso denegado claro · #136

En el recorrido de M3 abrí Administración con una cuenta Visualizador. El permiso se respetaba, pero aparecía el mensaje genérico en inglés, sin una opción clara para volver.

Añadí la página 403 en español usando el mismo layout y Tabler que la página 404 existente. Ofrece Volver al mapa y Volver al inicio. No cambia permisos, roles, cuentas ni los errores JSON.

Probé los enlaces y el idioma, la denegación HTML y el JSON de la misma ruta, además de dos regresiones de autenticación y administración de la API. En la rama del arreglo pasaron 4 pruebas y39 comprobaciones; las salidas están en [la evidencia](evidencias/acceso136/regresion-html-json.txt) y [permisos](evidencias/acceso136/permisos-api.txt).

También repetí el recorrido en la copia conjunta local7163a0e: [captura](evidencias/acceso136/acceso-denegado.png). Sigue negando el acceso y el enlace devuelve al mapa. En esa copia se comprobaron4casos/38aserciones diferentes; su registro queda en el índice #41, sin sumar ni trasladar ambos conteos.

La base es el candidato #96; falta la revisión de Jaziel y no se cambia main.
