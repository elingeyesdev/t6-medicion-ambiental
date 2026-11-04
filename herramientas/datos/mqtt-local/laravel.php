<?php

// Adaptador de pruebas: arranca el kernel y el comando reales, sin modificar app/.
use App\Enums\RolCuenta;
use App\Models\PuntoAmbiental;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Console\Output\BufferedOutput;

$root = dirname(__DIR__, 3);
$run = realpath(getenv('MQTT66_RUN') ?: '');
$runs = realpath($root.'/tmp/mqtt-local66/runs');
if (! $run || ! $runs || dirname($run) !== $runs ||
    ! is_file($run.'/.mqtt66-owned') || getenv('APP_ENV') !== 'testing' ||
    getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== $run.'/prueba.sqlite') {
    fwrite(STDERR, "Se exige un directorio nuevo propiedad del harness y SQLite aislada.\n");
    exit(3);
}
// No cargar .env ni cachés compartidas, incluso al ejecutar en un checkout configurado.
foreach (['APP_CONFIG_CACHE', 'APP_SERVICES_CACHE', 'APP_PACKAGES_CACHE', 'APP_ROUTES_CACHE', 'APP_EVENTS_CACHE'] as $key) {
    $value = $run.'/cache/'.$key.'.php';
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
require $root.'/web/vendor/autoload.php';
$app = require $root.'/web/bootstrap/app.php';
$app->addAbsoluteCachePathPrefix($run);
$app->useEnvironmentPath($run);
$app->loadEnvironmentFrom('no-env');
$app->useStoragePath($run.'/storage');
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
config([
    'database.default' => 'sqlite',
    'database.connections.sqlite.url' => null,
    'database.connections.sqlite.database' => $run.'/prueba.sqlite',
    'cache.default' => 'array', 'session.driver' => 'array',
    'mail.default' => 'array', 'queue.default' => 'sync',
]);
DB::purge();
Http::preventStrayRequests();
Mail::fake();
Notification::fake();

$action = $argv[1] ?? '';
$options = json_decode(getenv('MQTT66_OPTIONS') ?: '{}', true, 32, JSON_THROW_ON_ERROR);
$output = new BufferedOutput;
$exit = 0;
$result = [];
if ($action === 'prepare') {
    // Nunca migrate:fresh sobre un archivo existente ni seeders de la aplicación.
    if (filesize($run.'/prueba.sqlite') !== 0) {
        throw new RuntimeException('La base debe estar vacía.');
    }
    $exit = $kernel->call('migrate', ['--force' => true, '--no-interaction' => true], $output);
    if ($exit !== 0) {
        throw new RuntimeException('No se pudieron ejecutar las migraciones.');
    }
    $user = new User;
    $user->forceFill(['name' => 'MQTT66 sintético', 'email' => 'mqtt66@example.invalid',
        'password' => bin2hex(random_bytes(32)), 'rol' => RolCuenta::Analista, 'activo' => true])->save();
    foreach ([['mqtt66-a', -17.1, -63.1], ['mqtt66-b', -17.2, -63.2]] as [$codigo, $latitud, $longitud]) {
        PuntoAmbiental::create(['codigo' => $codigo, 'nombre' => 'Punto sintético '.$codigo,
            'pais' => 'BO', 'latitud' => $latitud, 'longitud' => $longitud, 'tipo' => 'manual']);
    }
    $result = ['actor' => $user->id, 'puntos' => PuntoAmbiental::count()];
} elseif ($action === 'receive') {
    $exit = $kernel->call('geosur:mqtt-recibir', $options, $output);
} elseif ($action === 'actor') {
    // Solo la única cuenta sintética de esta base; no modifica roles.
    User::where('email', 'mqtt66@example.invalid')->update(['activo' => (bool) $options['activo']]);
} elseif ($action === 'point') {
    PuntoAmbiental::where('codigo', 'mqtt66-b')->update(['activo' => (bool) $options['activo']]);
} elseif ($action === 'snapshot') {
    $result = [
        'lecturas' => DB::table('lecturas_ambientales')->orderBy('id')->get(['punto_id', 'metrica', 'valor',
            'instante', 'hora_utc', 'origen', 'fuente', 'latitud', 'longitud', 'registrado_por'])->toArray(),
        'cuentas' => DB::table('users')->count(), 'puntos' => DB::table('puntos_ambientales')->count(),
        'avisos' => DB::table('avisos_ambientales')->count(), 'envios' => DB::table('envios_avisos')->count(),
        'suscripciones' => DB::table('suscripciones_canal_avisos')->count(),
    ];
} else {
    throw new RuntimeException('Acción de prueba desconocida.');
}
Mail::assertNothingOutgoing();
Notification::assertNothingSent();
echo json_encode(['exit' => $exit, 'output' => $output->fetch(), 'result' => $result,
    'php' => PHP_VERSION, 'laravel' => $app->version()], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
exit($exit);
