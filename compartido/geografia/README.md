# Catálogo recuperado de EnviroSense · #62

`paises-envirosense.json` conserva los códigos y nombres de los seeds de Bolivia y Sudamérica del referente. Incluye doce países y Guyana Francesa como territorio. No contiene mediciones, límites, estaciones ni promesas de cobertura ambiental continental.

Regenerar desde la raíz: `python herramientas/recuperar-paises-envirosense.py`. El generador conserva los archivos originales y registra SHA-256 de cada seed. No conecta con PostgreSQL, no ejecuta SQL ni descarga información.

Laravel expone `GET /api/v1/paises` y `GET /api/v1/paises?codigo=BR`. Los códigos son los del referente; no sustituyen los identificadores `BO07...` del catálogo de Santa Cruz. Los parámetros desconocidos/repetidos no se ignoran. La respuesta diferencia este catálogo de las consultas ambientales disponibles, que siguen en Santa Cruz.

Las regiones, sus geometrías, licencias y consultas por país faltan por recuperar en #62. Flutter puede usar este archivo/endpoint al preparar su catálogo, sin presentar el nombre de un país como dato ambiental disponible.
