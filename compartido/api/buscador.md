# Buscador compartido del mapa

Candidato de #131, continuación de #62 y #128. Mismo contrato para sesión web y API autenticada; sin cambios en Flutter.

## Petición

- Web: GET /mapa/buscar, sesión y cuenta activa.
- API: GET /api/v1/mapa/buscar, Sanctum con capacidad cuenta.
- Ambos exigen permiso datos.consultar, respuesta privada sin almacenamiento y límite compartido de doce consultas por minuto por cuenta.

Parámetros:

- texto: obligatorio, de 2 a 120 caracteres y hasta veinte palabras; sin controles ni punto y coma.
- tipo: opcional, lugar (predeterminado) o sitio. lugar conserva la búsqueda de ciudades/direcciones; sitio busca establecimientos y sitios de interés.
- latitud y longitud: opcionales, siempre juntas; números finitos dentro de [-90,90] y [-180,180]. Solo sitio las envía como proximidad al proveedor. Son un sesgo del centro elegido, no un filtro territorial ni ubicación certificada del usuario.

Ejemplo de petición controlada: GET /api/v1/mapa/buscar?texto=Parque&tipo=sitio&latitud=0&longitud=0. No copiar credenciales o datos de una cuenta al query.

## Respuesta

Se conservan los campos fuente (Mapbox) y datos (lista de cero a seis resultados). Cada resultado contiene nombre, descripcion, latitud, longitud y limites opcionales [oeste,sur,este,norte] o null. Los ceros son coordenadas válidas. La geometría de un sitio debe ser Point y su tipo POI; el backend adapta ese formato al contrato existente.

Los ejemplos de las pruebas son sintéticos: Parque de prueba, Ciudad de prueba y coordenadas [0,0]. No son una respuesta real del proveedor ni un lugar publicado. Los clientes muestran nombre y descripción como texto. Seleccionar un sitio consulta el tiempo de sus coordenadas sin convertirlo en un punto ambiental registrado.

## Vacíos, errores y privacidad

200 con lista vacía significa búsqueda procesada sin coincidencias del proveedor; no acredita que no existan sitios en el territorio. 401/403 se conservan para acceso, 422 para parámetros inválidos, 429 para frecuencia y 503 para falta de configuración, rechazo o respuesta inválida del proveedor. Los errores no incluyen claves ni mensajes privados del transporte.

Cache-Control sigue siendo no-store, private. No guardar los resultados temporales en base, almacenamiento local, caché, logs o URL/historial; no crear estaciones. Conservar la atribución Mapbox. La petición ocurre al confirmar la búsqueda, no al abrir el mapa ni por cada letra.

## Cobertura y evidencia

[Implementación y límites comprobados](../../docs/SITIOS_INTERES131.md). El ensayo de un establecimiento en Bolivia no establece cobertura exhaustiva. La búsqueda de lugar predeterminada no cambia ni queda limitada por el país del historial.
