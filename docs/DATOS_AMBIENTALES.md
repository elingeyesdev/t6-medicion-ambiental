# Mediciones e historial de GeoSur

Avance de #66 sobre la recuperación de EnviroSense (#61). La pantalla **Mediciones** permite consultar puntos, fechas, variables, mapa, gráfico por hora y registros. Se abre con sesión y conserva Tabler 1.7.1, componentes compartidos y texto de 16 px.

## Qué recuperamos del original

Se revisaron `sensores`, `historial`, `radar`, MQTT, ETL y el esquema de `referencias/envirosense/codigo/`. El referente conserva 61 localidades de simulación, cinco variables y rangos. No son 61 estaciones físicas. Su lectura de un punto arbitrario podía recurrir a valores aleatorios; esa salida no se incorporó como observación real.

`herramientas/datos/recuperar-catalogo.py` extrae las constantes sin ejecutar JavaScript ni SQL. Guarda `compartido/sensores/catalogo-envirosense.json`, con huellas de los tres archivos revisados. `--comprobar` reproduce y compara el catálogo sin escribirlo. Son doce países y Guyana Francesa, conservada como territorio.

Variables y límites de entrada del código original:

- Temperatura: −40 a 60 °C. El esquema SQL anterior menciona −50; se adoptó el límite de ejecución de `constants/metricas.js`, −40.
- Humedad: 0 a 100 %.
- Aire: AQI entre 0 y 500.
- Agua: ICA entre 0 y 100.
- Ruido: 0 a 140 dB.

Estos límites validan la entrada; no prueban la calidad científica de una lectura. El AQI europeo que usa otra parte del referente tiene una escala distinta y no se incorporó como AQI equivalente. Un ICA calculado o simulado no sustituye un análisis de laboratorio. No hay conversión automática de voltaje/gas de un dispositivo a un índice ambiental.

## Persistencia y origen

`puntos_ambientales` guarda código, nombre, país/territorio, coordenadas, tipo y estado. `lecturas_ambientales` conserva variable, valor, instante UTC, hora UTC, origen, fuente, coordenadas del momento y usuario que registró. Archivar un punto bloquea nuevas lecturas y conserva el historial; reactivarlo es posible. No hay borrado masivo.

Orígenes separados en la base, API, gráfico y resumen:

- `observado`: registro declarado por un operador autorizado; la etiqueta visible es **Registrado**. No supone que GeoSur haya certificado el dispositivo.
- `estimado`: salida de un modelo, identificada con su proveedor.
- `simulado`: demostración o escenario, nunca una lectura física.

La identidad es punto + variable + instante + origen + fuente. Un reenvío con el mismo valor no duplica. Un valor diferente con la misma identidad devuelve conflicto y conserva el anterior. El lote se valida completo y se guarda en una transacción; una fila inválida o un conflicto revierten todo el lote. La coordenada histórica no cambia si después se modifica el punto. Los promedios nunca mezclan fuentes u orígenes.

## Preparación local

Antes de migrar una base existente, hacer un respaldo privado. Desde `web/`, con PHP y las dependencias instaladas:

```sh
php artisan migrate
php artisan geosur:preparar-puntos
```

Preparar puntos es repetible y no crea lecturas ni sobrescribe puntos existentes. La migración añade tablas; no importa bases o usuarios del original. La ejecución local de esta entrega conservó las dos cuentas existentes, con respaldo fuera de Git.

Demostración opcional, solo en `local` o `testing`:

```sh
php artisan geosur:datos-demo --confirmar
```

Prepara 24 horas deterministas para Santa Cruz, La Paz y Buenos Aires: **360 lecturas simuladas**, fuente `demo-local`. Repetir dentro de la misma hora no duplica; una ejecución posterior añade las horas nuevas y conserva las anteriores. No se ejecuta al abrir la web ni al instalar la aplicación.

## Cargar lecturas

Administradores y analistas pueden agregar puntos, guardar una lectura, importar un CSV y archivar/reactivar. Visualizadores consultan; no reciben controles de escritura. Las rutas comprueban los permisos aunque alguien intente llamar directamente a la API.

La plantilla está en `web/public/ejemplos/lecturas.csv`. Columnas exactas:

```csv
punto,metrica,valor,instante,origen,fuente
santacruz,temperatura,25.5,2026-10-27T12:00:00Z,simulado,ejemplo-local
```

El ejemplo es simulado. Sustituir fecha y valor por los de la carga; no registrar fechas futuras. UTC o zona explícita en ISO 8601 con segundos. Hasta 1000 filas y 2 MB; el archivo completo se valida antes de escribir. La fuente es un identificador breve, sin URLs, claves o datos privados. CSV vacío, columnas distintas, unidades/variables desconocidas, valores fuera del rango y datos faltantes se rechazan.

También se puede importar desde consola:

```sh
php artisan geosur:importar-lecturas /ruta/privada/lecturas.csv
```

## Captura del tiempo, opcional

El comando recupera temperatura y humedad del modelo de [Open-Meteo](https://open-meteo.com/en/docs). No es una estación física. Exige `GEOSUR_TIEMPO_HABILITADO=true` y una orden explícita:

```sh
php artisan geosur:capturar-tiempo santacruz --consultar-proveedor
```

La bandera está apagada en `.env.example`. Web y API leen la base local y no llaman al proveedor. No hay cron ni captura en segundo plano. Se comprueban respuesta, unidades, fecha reciente, valores y tamaño; si falla, no se inventa un reemplazo.

Se hizo una captura manual en local: dos estimaciones, una por variable, guardadas como `estimado/open-meteo-modelo`. La bandera se habilitó únicamente para ese proceso y quedó apagada después. Evidencia saneada: `evidencias/sensores66/captura-modelo.json`. El clima mostrado no se toma como una predicción de incendio o sequía.

## Contrato para móvil

Base `/api/v1`, sesión o token de Sanctum con habilidad `cuenta`, usuario activo. Se usan los permisos de #63. Las respuestas no se almacenan en cachés compartidas.

- `GET /sensores/catalogo`: variables, unidades y orígenes.
- `GET /sensores`: lista paginada de 50; `pagina`, `pais`, `buscar`, `activos=0|1`. Por defecto solo activos; `0` lista archivados.
- `GET /sensores/cercanos`: `latitud`, `longitud`, `radioKm` opcional, por defecto 25 y máximo 100. Devuelve hasta 20 puntos, no valores inventados para la coordenada consultada.
- `GET /sensores/{codigo}/lecturas`: `desde`, `hasta`, `metrica`, `origen`, `fuente`, `pagina`. Hasta 7 días/168 horas, sin futuro. Desde incluido y hasta excluido; ISO 8601 con zona y segundos. Sin fechas: últimos siete días hasta ahora. Respuesta con punto, datos, resumen separado por fuente/origen, promedios por hora y paginación. Máximo 100 series de resumen y 2000 grupos horarios; acotar filtros si se supera.
- `POST /sensores`: nombre, país, coordenadas y código/tipo opcionales. Tipos nuevos `manual` o `iot`; declarar IoT no conecta un equipo.
- `POST /sensores/lecturas`: objeto `lecturas` con la misma estructura que las filas CSV, hasta 1000 elementos.
- `PATCH /sensores/{codigo}/estado`: `activo` booleano. Conserva las lecturas.

Las primeras cuatro rutas son de consulta; las tres últimas exigen administrador o analista. Parámetros de consulta desconocidos/repetidos se rechazan. Errores: sin sesión 401, sin permiso 403, validación 422, conflicto de lectura 409, captura externa deshabilitada/no disponible 503. No enviar tokens ni contraseñas dentro de URLs o capturas.

La pantalla usa hora de Bolivia; API y almacenamiento usan UTC. Flutter todavía debe conectar estos recorridos con Jaziel. Esta API nueva no cambia el contrato de focos o sequías.

## Organización del código

- `app/Services/Datos/`: catálogo, registro, historial, cercanía, CSV y captura del tiempo.
- `app/Models/`: punto y lectura, con migración aditiva.
- `app/Http/Controllers/{Api/,}SensoresController.php`: adaptación HTTP/API y formularios separados.
- `resources/views/datos/`, `public/css/paginas/datos.css`, `public/js/datos/mediciones.js`: pantalla, estilos y mapa/gráfico. Sin modificar Tabler ni instalar otra plantilla.
- `app/Console/Commands/`: preparación, demostración, CSV y captura explícita.
- `tests/Feature/DatosAmbientalesTest.php`: pruebas de permisos, integridad, datos y proveedores.

## Lo que sigue abierto en #66

MQTT con un equipo/broker real y credenciales privadas; definición/calibración de sus métricas; radar y capas por país; cargas masivas NOAA/CDS y sus tareas; reconstrucción del data-worker; migración de una copia saneada de una base histórica original; prueba real de PostgreSQL y concurrencia; aplicación Flutter y revisión de Jaziel. No se activaron proveedores, notificaciones, descargas grandes ni tareas programadas al recuperar el catálogo. La fecha de recuperación completa no está confirmada.

El `data-worker` conservado aporta un Dockerfile; eso solo no acredita un servicio completo. ETL y radar tienen código de referencia, pero esta entrega no prueba su cobertura ni los declara recuperados. La prueba real se hizo con SQLite local. Railway continúa pospuesto. #66 permanece abierto; este avance no promueve M3 a `main`.
