<?php

// Ejecuta las clases reales de #62 sin bootstrap, cuenta, proveedor o conexión a base.
$raizRepo = dirname(__DIR__, 4);
$directorio = $argv[1];
$esperados = isset($argv[2]) ? (int) $argv[2] : 1;
function config(string $clave): mixed
{
    global $directorio;

    return ['capas.directorio' => $directorio, 'capas.max_cuadros' => 512,
        'capas.max_bytes' => 5242880, 'capas.max_pixeles' => 2200000][$clave] ?? null;
}
require $raizRepo.'/web/app/Services/Api/ErrorConsulta.php';
require $raizRepo.'/web/app/Services/Mapas/Capas/VariablesRaster.php';
foreach (['ArchivoRaster', 'ArchivoPublicacionRaster', 'CatalogoRaster', 'BloqueoRaster', 'PublicadorRaster'] as $clase) {
    require $raizRepo.'/web/app/Services/Mapas/Radar/'.$clase.'.php';
}
$receta = json_decode(file_get_contents($directorio.'/receta.json'), true, 32, JSON_THROW_ON_ERROR);
$cantidad = (new App\Services\Mapas\Radar\PublicadorRaster)->publicar($directorio, $receta);
$catalogo = (new App\Services\Mapas\Radar\CatalogoRaster)->cargar();
$cuadro = $catalogo['cuadros'][0];
$bytes = App\Services\Mapas\Radar\ArchivoRaster::leer($directorio.'/'.$cuadro['archivo']);
if ($cantidad !== $esperados || count($catalogo['cuadros']) !== $esperados || $catalogo['faltantes'] !== 0 || hash('sha256', $bytes) !== $cuadro['sha256']) {
    throw new RuntimeException('El productor no cumple el publicador real.');
}
echo json_encode(['cuadros' => $cantidad, 'ancho' => $cuadro['ancho'], 'alto' => $cuadro['alto'],
    'sha256' => $cuadro['sha256'], 'rejilla' => $cuadro['rejilla']], JSON_THROW_ON_ERROR).PHP_EOL;
