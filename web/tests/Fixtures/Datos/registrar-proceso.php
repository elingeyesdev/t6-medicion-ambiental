<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$carpeta = $argv[1];
$indice = $argv[2];
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $carpeta.'/prueba.sqlite',
    'database.connections.sqlite.url' => null, 'database.connections.sqlite.busy_timeout' => 1000,
    'cache.default' => 'array', 'mail.default' => 'array']);
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
