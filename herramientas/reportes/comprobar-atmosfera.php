<?php

/** Consulta pública y exportación real en SQLite en memoria, sin usar cuentas o reportes de la demo. */
use App\Models\User;
use App\Services\Reportes\ExportarReporte;
use App\Services\Reportes\PrepararReporte;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;

$opciones = getopt('', ['consultar-proveedor', 'salida:']);
if (PHP_SAPI !== 'cli' || ! array_key_exists('consultar-proveedor', $opciones) || ! is_string($opciones['salida'] ?? null)) {
    fwrite(STDERR, "Uso: php herramientas/reportes/comprobar-atmosfera.php --consultar-proveedor --salida=tmp/captura-nueva\n");
    exit(2);
}
$raiz = dirname(__DIR__, 2);
require $raiz.'/web/vendor/autoload.php';
$app = require $raiz.'/web/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('local', 'testing')) {
    fwrite(STDERR, "La comprobación solo está disponible en local o testing.\n");
    exit(2);
}
$parent = realpath(dirname($opciones['salida']));
$nombre = basename($opciones['salida']);
if ($parent === false || ! preg_match('/\A[a-zA-Z0-9_-]{1,64}\z/D', $nombre) || file_exists($parent.'/'.$nombre) || is_link($parent.'/'.$nombre)) {
    fwrite(STDERR, "La salida debe ser una carpeta nueva dentro de una carpeta existente.\n");
    exit(2);
}
$salida = $parent.'/'.$nombre;
try {
    config(['database.default' => 'comprobacion_reportes', 'database.connections.comprobacion_reportes' => [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
    ], 'reportes.atmosfera.habilitado' => true, 'reportes.atmosfera.clave' => null,
        'reportes.atmosfera.url' => 'https://api.open-meteo.com/v1/forecast',
        'reportes.atmosfera.aire_url' => 'https://air-quality-api.open-meteo.com/v1/air-quality',
        'mail.default' => 'array']);
    $conexion = DB::connection();
    if ($conexion->getDriverName() !== 'sqlite' || $conexion->getDatabaseName() !== ':memory:' ||
        collect($conexion->select('pragma database_list'))->contains(fn ($base) => $base->file !== '')) {
        throw new RuntimeException('La comprobación no está aislada en memoria.');
    }
    if (Artisan::call('migrate', ['--force' => true]) !== 0) {
        throw new RuntimeException('No se pudo preparar la base temporal.');
    }
    $actor = new User(['name' => 'Comprobación local', 'email' => 'comprobacion@geosur.invalid', 'password' => bin2hex(random_bytes(24))]);
    $actor->forceFill(['rol' => 'analista', 'activo' => true])->save();
    if (! Gate::forUser($actor)->allows('reportes.crear')) {
        throw new RuntimeException('La cuenta temporal no puede generar reportes.');
    }
    if (! mkdir($salida, 0700)) {
        throw new RuntimeException('No se pudo crear la carpeta de captura.');
    }
    $peticiones = [];
    Event::listen(ResponseReceived::class, function (ResponseReceived $evento) use (&$peticiones, $salida): void {
        $host = parse_url($evento->request->url(), PHP_URL_HOST);
        if (! in_array($host, ['api.open-meteo.com', 'air-quality-api.open-meteo.com'], true)) {
            throw new RuntimeException('Proveedor no previsto en esta comprobación.');
        }
        $grupo = $host === 'api.open-meteo.com' ? 'tiempo' : 'aire';
        $bytes = $evento->response->body();
        if (file_put_contents($salida.'/proveedor-'.$grupo.'.json', $bytes) !== strlen($bytes)) {
            throw new RuntimeException('No se guardó el recibo del proveedor.');
        }
        $peticiones[] = ['grupo' => $grupo, 'endpoint' => strtok($evento->request->url(), '?'),
            'parametros' => $evento->request->data(), 'estadoHttp' => $evento->response->status(),
            'recibidoUtc' => now('UTC')->toIso8601ZuluString(), 'sha256' => hash('sha256', $bytes)];
    });
    $reporte = app(PrepararReporte::class)->crear(['titulo' => 'Pronóstico de Santa Cruz - dos escalas del aire', 'base' => 'atmosfera',
        'puntos' => ['santacruz'], 'variables' => ['temperatura', 'aqi', 'aqi_europeo'], 'horas' => 3], $actor->id);
    $recibidas = count($peticiones);
    $exportador = app(ExportarReporte::class);
    $pdf = $exportador->pdf($reporte);
    $excel = $exportador->excel($reporte);
    if ($recibidas !== 2 || count($peticiones) !== $recibidas || ! str_starts_with($pdf, '%PDF-') || ! str_starts_with($excel, 'PK')) {
        throw new RuntimeException('No se verificaron ambos proveedores y exportaciones sin consultas adicionales.');
    }
    file_put_contents($salida.'/atmosfera.pdf', $pdf);
    file_put_contents($salida.'/atmosfera.xlsx', $excel);
    file_put_contents($salida.'/reporte.json', json_encode($reporte->paraCliente(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    $resultado = ['base' => 'SQLite temporal en memoria; demo intacta', 'proveedores' => $peticiones,
        'consultadoUtc' => $reporte->parametros['consultadoEl'], 'filas' => count($reporte->contenido['filas']),
        'faltantes' => count($reporte->contenido['faltantes']), 'peticionesAlExportar' => count($peticiones) - $recibidas,
        'sha256Pdf' => hash('sha256', $pdf), 'sha256Excel' => hash('sha256', $excel)];
    file_put_contents($salida.'/recibo.json', json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    echo json_encode(['resultado' => 'correcto', 'filas' => $resultado['filas'], 'faltantes' => $resultado['faltantes'],
        'peticiones' => $recibidas, 'peticionesAlExportar' => $resultado['peticionesAlExportar']], JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, "La comprobación no terminó. Se conservan los archivos capturados; revisa proveedor, configuración y salida. No se usó la base de la demo.\n");
    exit(1);
} finally {
    DB::disconnect('comprobacion_reportes');
}
