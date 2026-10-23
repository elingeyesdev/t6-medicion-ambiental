<?php

// Comprobación puntual contra el catálogo aceptado; no acredita cobertura del municipio.
require __DIR__.'/../../web/app/Services/Territorios/CatalogoTerritorial.php';
require __DIR__.'/../../web/app/Services/Territorios/GeometriaTerritorial.php';

$ruta = __DIR__.'/../../compartido/territorios/datos';
$catalogo = new App\Services\Territorios\CatalogoTerritorial($ruta);
$geometria = new App\Services\Territorios\GeometriaTerritorial($catalogo);
$capa = json_decode(file_get_contents($ruta.'/limites/municipios.geojson'), true, flags: JSON_THROW_ON_ERROR);
$puntos = ['solicitada' => [-63.1821, -17.7833], 'celda' => [-63.25, -17.75]];
$esperados = ['solicitada' => ['BO070101'], 'celda' => ['BO070103']];
$resultado = ['catalogo' => $capa['versionCatalogo'], 'pertenencia' => []];
foreach ($puntos as $nombre => [$x, $y]) {
    $coinciden = [];
    foreach ($capa['features'] as $f) {
        if ($geometria->contiene($f['geometry'], $x, $y)) {
            $coinciden[] = $f['id'];
        }
    }
    if ($coinciden !== $esperados[$nombre]) {
        throw new RuntimeException($nombre.': pertenencia distinta: '.json_encode($coinciden));
    }
    $resultado['pertenencia'][$nombre] = ['coordenada' => [$x, $y], 'municipios' => $coinciden,
        'nombres' => array_map(fn ($id) => $catalogo->buscar($id)['nombre'], $coinciden)];
}
$resultado['advertencia'] = 'El centro de celda y el punto solicitado pertenecen a distintos municipios; no reasignar el contexto ni inferir cobertura municipal.';
echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
