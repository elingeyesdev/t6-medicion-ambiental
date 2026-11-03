<?php

// Publicador local: recibe PNG existentes y una receta explícita. No hace peticiones de red.
if (PHP_SAPI !== 'cli' || count($argv) !== 3) {
    fwrite(STDERR, "Uso: php docs/integracion62/publicar-capas.php DIRECTORIO_PNG RECETA_JSON\n");
    exit(2);
}
require __DIR__.'/../../web/vendor/autoload.php';
$app = require __DIR__.'/../../web/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    $archivo = realpath($argv[2]);
    $raiz = realpath($argv[1]);
    if (! $archivo || ! $raiz || ! is_dir($raiz) || ! is_file($archivo) || filesize($archivo) > 2097152) {
        throw new UnexpectedValueException('Revisa la receta y el directorio de PNG.');
    }
    $receta = json_decode(file_get_contents($archivo), true, 32, JSON_THROW_ON_ERROR);
    $cantidad = (new App\Services\Mapas\Radar\PublicadorRaster)->publicar($raiz, $receta);
    fwrite(STDOUT, "Publicados $cantidad cuadros verificados.\n");
} catch (Throwable $e) {
    fwrite(STDERR, "No se publicó el catálogo. Revisa los archivos y el contrato de capas.\n");
    exit(1);
}
