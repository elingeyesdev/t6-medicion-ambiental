# Entrega local M3 · #52 y #64

Rama `feature/52-simulacion-continua`, creada desde `c8b6b7a69f7aadc9e88361c38e63968ea6623f0a`. Worktree propio: `tmp/m3-simulaciones-web`. Se consultó la fuente activa `tmp/panel-envirosense` y se mantuvo inmutable `referencias/envirosense/codigo`. No se editaron móvil, Datos, Capas, Alertas, Cuentas, rutas compartidas ni bootstrap. No hay PR, publicación, mensajes en GitHub ni fusiones.

## Comportamiento entregado

- Recorrido global con todas las localidades del catálogo y las cinco variables originales. Se adaptaron las ecuaciones de `Backend/Src/modules/simulacion/simulacion.service.js`: ciclo diurno, correlaciones y horas punta. Ruido e inicialización usan SHA256 con semilla explícita; son exclusivamente simulaciones, nunca observaciones.
- Preparar no inicia. Iniciar, pausar, detener, reiniciar e inyectar requieren acción y permiso. Solo controla su propietario. Otra cuenta puede preparar un nuevo recorrido cuando el anterior está detenido. Revisión obsoleta: 409; control sobre recorrido ajeno: 404. El estado global no devuelve identificadores ni datos de cuentas.
- Estado compartido por consultas HTTP breves cada tres segundos, sin sockets abiertos ni bucles en una petición. Cada consulta procesa como máximo veinte pasos pendientes (tope de configuración: cincuenta). Una pausa fija el final del tramo activo; los pasos vencidos se recuperan por bloques sin saltarlos. El avance por escenario también usa reloj del servidor y se ve al abrir otra pantalla. Ninguna consulta inicia una simulación.
- Inyecciones acotadas a las cinco variables y a sus límites absolutos originales. Se distingue ajuste manual/modelo y se retienen los últimos veinte ajustes del recorrido actual. Reiniciar asigna una versión nueva. No se envían avisos ni se escriben lecturas ambientales desde el modo global.
- `base=continua` guarda el último estado persistido como corte inmutable, con versión, revisión, paso, método, semilla y ajustes. No contiene toda la trayectoria del modo global. Los escenarios existentes siguen permitiendo exportar su serie completa y publicar explícitamente lecturas pasadas como simuladas.
- `base=atmosfera` consulta por acción explícita las localidades de referencia elegidas. Incluye temperatura, sensación, humedad, lluvia, probabilidad, viento, dirección, visibilidad, nieve reciente, nieve acumulada, nubosidad, presión, UV y AQI. Hasta nueve localidades, 72 horas y mil valores solicitados, sin recortes silenciosos.
- Fuente meteorológica: Open-Meteo; aire: Open-Meteo/CAMS. Son estimaciones de modelos. No se decodifican píxeles GFS ni se afirma equivalencia científica entre esos productos; la procedencia y unidades del producto consultado se guardan en cada fila. Nieve acumulada en **m**, nieve reciente en **cm**, lluvia por intervalo horario en **mm**. No se convierte lluvia a una tasa instantánea.
- Faltantes separados de cero; no entran en promedios y cortan las curvas. Se conserva su detalle en la web y en la hoja `No disponibles` de Excel. PDF incorpora gráficos SVG locales. La web ofrece líneas y barras; Excel conserva lecturas/resumen/faltantes, sin gráficos nativos incrustados.
- Las condiciones siguen exigiendo misma localidad, instante, origen y fuente: no se juntan automáticamente temperatura meteorológica y AQI de CAMS. PDF/XLSX y reportes ya guardados no vuelven a consultar proveedores. Las instantáneas previas conservan su contenido y configuración.
- Visualizador mantiene su rol: consulta estado global desde Reportes y sus reportes propios; no prepara, controla ni genera. Analista/Administrador conservan sus permisos. Sin IA ni chatbot. Sin dependencias nuevas.

## Integración exacta de rutas web

Dentro del grupo **ya existente** `Route::middleware(['auth', 'cuenta.activa'])` de `web/routes/web.php`, añadir lo siguiente. Se usan clases completas para no requerir cambios de imports:

```php
Route::get('/simulacion-continua/estado', [\App\Http\Controllers\SimulacionContinuaController::class, 'estado'])
    ->middleware(['can:datos.consultar', 'throttle:120,1']);
Route::post('/simulacion-continua/accion', [\App\Http\Controllers\SimulacionContinuaController::class, 'accion'])
    ->middleware(['can:simulaciones.gestionar', 'throttle:30,1']);
Route::get('/simulaciones/{id}/estado', [\App\Http\Controllers\SimulacionesController::class, 'mostrar'])
    ->middleware(['can:simulaciones.gestionar', 'throttle:120,1']);
```

Las rutas existentes de Reportes y Simulaciones conservan sus nombres y verbos. No sustituirlas. La interfaz de este lote usa las tres URL anteriores; integrar las rutas junto al commit antes de probar los controles en navegador.

## Integración exacta de API

Dentro del grupo `v1` **ya autenticado** con `auth:sanctum`, `cuenta.activa`, `abilities:cuenta` en `web/routes/api.php`:

```php
Route::get('/simulacion-continua/estado', [\App\Http\Controllers\SimulacionContinuaController::class, 'estado'])
    ->withoutMiddleware('throttle:30,1')
    ->middleware(['can:datos.consultar', 'throttle:120,1']);
Route::post('/simulacion-continua/accion', [\App\Http\Controllers\SimulacionContinuaController::class, 'accion'])
    ->middleware('can:simulaciones.gestionar');
```

Se conserva el límite general de treinta acciones API por minuto. Solo la lectura continua recibe un límite propio para permitir varias pantallas. No se modificó el cliente móvil.

## Bootstrap, migración y tarea opcional

**`web/bootstrap/app.php`: ningún cambio requerido.** Laravel descubre `app/Console/Commands/AvanzarSimulacionContinua.php`; la suite ejecuta el comando y verifica que no prepare ni inicie un recorrido.

Migración reservada: `web/database/migrations/2026_10_29_003000_simulacion_continua.php`. Crea una fila global independiente y añade `reloj_ms`/`posicion_base` a sesiones por escenario; no modifica sus resultados ni reportes guardados. No se ejecutó sobre la base local del usuario: solo en SQLite en memoria de pruebas. Ejecutar las migraciones normales durante la integración autorizada.

El modo activo recupera sus pasos al consultar. Si se quiere mantener el estado materializado sin navegadores abiertos, añadir opcionalmente a `web/routes/console.php`:

```php
\Illuminate\Support\Facades\Schedule::command('simulaciones:avanzar')
    ->everyMinute()->withoutOverlapping();
```

Requiere un scheduler del despliegue. El comando realiza un solo bloque, no duerme, no prepara ni inicia sesiones. No se instaló ni ejecutó un scheduler real. Tras inactividad larga puede haber varios bloques pendientes; `pendientes` indica cuántos restan. Un reinicio explícito descarta el recorrido anterior, preservando los reportes que ya se guardaron.

## Puntos de integración con otros propietarios

Para mostrar el mismo estado en el mapa, el propietario de esa vista puede incluir, dentro de su contenido:

```blade
@include('simulaciones.continua')
```

Y en sus scripts (solo una vez por página):

```blade
<script type="module" src="{{ asset('js/simulaciones/continua.js') }}"></script>
```

El elemento `[data-simulacion-continua]` emite `simulacion-actualizada` con `event.detail` igual al JSON de estado. Hook exacto para una capa propia: escuchar ese evento, leer `detail.puntos[*].codigo/latitud/longitud/valores` y `detail.origen=simulado`; pintar una capa identificada como simulada, separada de las observaciones. No conectar a escritura de Datos ni a notificaciones. Las pantallas propias de Simulaciones y Reportes ya incluyen el componente.

No se editó el catálogo común. El catálogo adicional vive en `App\Services\Reportes\VariablesReporte::metricas()`; `atmosfericas()` fija nombres de proveedor, unidades y límites. Para incorporar sus variables a Datos/Capas, el responsable debe enlazar esas claves **y** adaptar primero validación/almacenamiento/agregación/unidades de su módulo. No basta añadir etiquetas. Claves adicionales sobre las cinco comunes: `sensacion`, `lluvia`, `probabilidad`, `viento`, `direccion`, `visibilidad`, `nieve`, `nieve_acumulada`, `nubosidad`, `presion`, `uv`. La unidad canónica de nieve acumulada es `m`. Este lote ya permite reportar esas métricas presentes en almacenamiento sin modificar el catálogo ajeno; no las importa ni crea automáticamente.

## Contratos de ejemplo

Preparar y luego iniciar son dos acciones distintas:

```http
POST /simulacion-continua/accion
{"accion":"preparar","revision":0,"semilla":"revision-m3","intervalo_ms":3000}

POST /simulacion-continua/accion
{"accion":"iniciar","revision":1}
```

Las acciones posteriores siempre envían la `revision` de la última respuesta. `GET /simulacion-continua/estado` devuelve `estado`, `revision`, `propia`, `origen`, `version`, `semilla`, `metodo`, `intervalo_ms`, `instante`, `paso`, `puntos`, `ajustes`, `pendientes`. Sin preparar: estado `sin_preparar`, revisión cero y puntos vacíos; no se crea ninguna fila.

```json
{"accion":"inyectar","revision":2,"punto":"santacruz","valores":{"temperatura":35,"aqi":0}}
```

Reportes reutiliza el endpoint existente `POST /api/v1/reportes` (web: `POST /reportes` con CSRF):

```json
{"titulo":"Corte global","base":"continua","punto":"santacruz","metrica":"aqi"}
```

```json
{"titulo":"Pronóstico por hora","base":"atmosfera","puntos":["santacruz","lapaz"],"variables":["lluvia","viento","visibilidad","nieve","nieve_acumulada","aqi"],"horas":24}
```

Errores: 401 sin sesión/cuenta activa, 403 sin permiso/capacidad, 404 propiedad/control ajeno o recorrido inexistente, 409 revisión/transición/recorrido aún recuperándose, 422 filtros/valores/límites, 503 proveedor deshabilitado, timeout, HTTP fallido o contrato malformado. No se devuelve el cuerpo privado del proveedor ni una clave.

## Conexiones externas y configuración

**No se ejecutó una consulta real a Open-Meteo/CAMS.** Se verificaron contratos con `Http::fake`, incluida URL alternativa y clave ficticia. No hay credenciales reales, avisos a personas, llamadas a IA ni red de simulación. La consulta atmosférica queda apagada por defecto.

Añadir al entorno de despliegue (no a Git) cuando se autorice su uso:

```dotenv
GEOSUR_REPORTES_ATMOSFERA_HABILITADO=true
GEOSUR_REPORTES_METEO_URL=https://api.open-meteo.com/v1/forecast
GEOSUR_REPORTES_AIRE_URL=https://air-quality-api.open-meteo.com/v1/air-quality
GEOSUR_REPORTES_METEO_CLAVE=
```

Los endpoints públicos no requieren clave; una instalación con suscripción puede configurar sus endpoints y clave. Máximo dos requests concurrentes por reporte, timeout de ocho segundos y sin redirects ni reintentos. El cliente no puede elegir URL ni enviar resultados propios. Se validan horas UTC, orden, longitud, unidades, valores, orden de localidades y tamaño de respuesta. Una respuesta fallida no crea un reporte parcial. La consulta no certifica mediciones de estaciones ni reproduce el radar raster del referente.

Contrato contrastado con [Open-Meteo Forecast](https://open-meteo.com/en/docs), [OpenAPI oficial](https://github.com/open-meteo/open-meteo/blob/main/openapi/forecast.yml) y [Air Quality/CAMS](https://open-meteo.com/en/docs/air-quality-api). Las pruebas son la evidencia de esta entrega, no esas conexiones reales.

## Pruebas y evidencia

Desde `web`, con PHP 8.3.30 y vendor **copiado**, `.env` creado únicamente desde `.env.example`:

```powershell
$env:VIEW_COMPILED_PATH = (Join-Path (Get-Location) 'storage/framework/views-pruebas')
..\herramientas\php.ps1 artisan test --compact
```

Resultado de suite completa: **402 pruebas, 5158 comprobaciones**, 77,54 s. Los tests propios instalan los snippets en su aplicación de prueba; no alteran rutas del repositorio. SQLite `:memory:`, HTTP controlado, correo y notificaciones falsificados. Se cubren autorización, propiedad, revisiones antiguas, varios lectores, pausas, recuperación acotada, inyección inválida sin cambios parciales, repetición/rangos, sincronización por escenario, snapshots, faltantes, errores, unidades, orden, límite y ausencia de escrituras ambientales.

Desde la raíz del worktree:

```powershell
node --test web/tests/JavaScript/reportes-grafico.test.cjs
```

Resultado: **3 pruebas JavaScript**; cero no se pierde, faltantes interrumpen la línea, serie totalmente ausente sin cifras inventadas y procedencias separadas. También se comprobó sintaxis de los JS cambiados, Pint sobre los PHP del alcance y `git diff --check`.

La prueba `test_pdf_con_graficos_y_excel_con_faltantes_descargan_el_snapshot_sin_reconsultar`, con `GEOSUR_EVIDENCIA_M3=1`, genera `tmp/evidencia-m3/atmosfera.pdf` y `.xlsx` del propio worktree. El PDF se renderizó e inspeccionó: gráfico con hueco, ceros, unidades, origen y resumen legibles; dos páginas A4. Excel: ZIP válido, XML analizable y tres hojas (`Lecturas`, `Resumen`, `No disponibles`). Estos archivos son temporales de pruebas, sin datos de cuentas, y no se incluyen en Git.

Límites pendientes de integración: unir rutas, ejecutar migración en el entorno elegido, activar/probar proveedores reales si se desea, incorporar el componente al mapa por su propietario y disponer del scheduler opcional si se exige materialización sin lectores. No se declara terminada la recuperación de Capas/Datos, Flutter ni M3 global ajeno a este lote.

## Archivos del lote

- `docs/INTEGRACION_M3_SIMULACIONES_REPORTES.md`
- `docs/REPORTES_ENVIROSENSE.md`
- `docs/SIMULACIONES_ENVIROSENSE.md`
- `web/app/Console/Commands/AvanzarSimulacionContinua.php`
- `web/app/Http/Controllers/ReportesController.php`
- `web/app/Http/Controllers/SimulacionContinuaController.php`
- `web/app/Http/Controllers/SimulacionesController.php`
- `web/app/Models/Simulacion.php`
- `web/app/Models/SimulacionContinua.php`
- `web/app/Services/Reportes/ConsultarAtmosfera.php`
- `web/app/Services/Reportes/ExportarReporte.php`
- `web/app/Services/Reportes/GraficoReporte.php`
- `web/app/Services/Reportes/PrepararReporte.php`
- `web/app/Services/Reportes/VariablesReporte.php`
- `web/app/Services/Simulaciones/ContinuarSimulacion.php`
- `web/app/Services/Simulaciones/MotorContinuo.php`
- `web/app/Services/Simulaciones/SimularEscenario.php`
- `web/config/reportes.php`
- `web/config/simulaciones.php`
- `web/database/migrations/2026_10_29_003000_simulacion_continua.php`
- `web/public/js/reportes/formulario.js`
- `web/public/js/reportes/grafico.js`
- `web/public/js/simulaciones/continua.js`
- `web/public/js/simulaciones/inicio.js`
- `web/resources/views/reportes/index.blade.php`
- `web/resources/views/reportes/mostrar.blade.php`
- `web/resources/views/reportes/pdf.blade.php`
- `web/resources/views/simulaciones/continua.blade.php`
- `web/resources/views/simulaciones/index.blade.php`
- `web/tests/Feature/ReportesAtmosferaTest.php`
- `web/tests/Feature/SimulacionContinuaTest.php`
- `web/tests/JavaScript/reportes-grafico.test.cjs`
