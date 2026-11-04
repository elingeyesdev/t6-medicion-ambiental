<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$carpeta = $argv[1];
$indice = $argv[2];
if (is_file($carpeta.'/postgres.json')) {
    $c = json_decode(file_get_contents($carpeta.'/postgres.json'), true, 8, JSON_THROW_ON_ERROR);
    if (getenv('GEOSUR_PRUEBA_POSTGRES') !== '1' || ($c['host'] ?? '') !== '127.0.0.1' ||
        ($c['database'] ?? '') !== 'geosur_m3_tests' || ! preg_match('/^concurrencia_[0-9a-f]{16}$/D', $c['search_path'] ?? '')) {
        throw new RuntimeException('La conexión no pertenece a una prueba aislada.');
    }
    config(['database.default' => 'pgsql', 'database.connections.pgsql' => $c + ['driver' => 'pgsql', 'password' => getenv('DB_PASSWORD'),
        'url' => null, 'charset' => 'utf8', 'prefix' => '', 'prefix_indexes' => true, 'sslmode' => 'prefer']]);
} else {
    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $carpeta.'/prueba.sqlite',
        'database.connections.sqlite.url' => null, 'database.connections.sqlite.busy_timeout' => 1000]);
}
config(['cache.default' => 'array', 'mail.default' => 'array']);
DB::purge();
file_put_contents($carpeta.'/listo-'.$indice, 'listo');
$limite = microtime(true) + 10;
while (! is_file($carpeta.'/iniciar')) {
    if (microtime(true) > $limite) {
        exit(3);
    }
    usleep(5000);
}
$codigo = Artisan::call('geosur:importar-historial', ['archivo' => $carpeta.'/lote-'.$indice.'.json', '--actor' => 1, '--confirmar-saneado' => true]);
echo json_encode(['codigo' => $codigo, 'salida' => Artisan::output()], JSON_THROW_ON_ERROR);
exit($codigo);
