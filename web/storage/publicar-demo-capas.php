<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$raiz = config('capas.directorio');
if (! is_dir($raiz)) {
    mkdir($raiz, 0700, true);
}

$chunk = fn ($t, $s) => pack('N', strlen($s)).$t.$s.hash('crc32b', $t.$s, true);
$png = "\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', 2, 2, 8, 6, 0, 0, 0)).
    $chunk('IDAT', gzcompress("\0\0\0\0\xff\xff\0\0\xff\0\x80\0\0\xff\0\0\0\0")).$chunk('IEND', '');

$rejilla = [
    'proyeccion' => 'EPSG:4326',
    'registro' => 'centros',
    'filas' => 'sur-norte',
    'oeste' => -82.0,
    'este' => -34.0,
    'sur' => -56.0,
    'norte' => 13.0,
];

// Misma lista que VariablesRaster::todas() — demo local #62.
$defs = [
    'temperatura' => 'temp-era5',
    'lluvia' => 'lluvia-escalones',
    'lluvia_intensidad' => 'lluvia-escalones',
    'viento' => 'viento-uv',
    'visibilidad' => 'vis-etl',
    'nieve' => 'nieve-etl',
    'aqi' => 'aqi-textura',
    'humedad' => 'humedad-etl',
    'presion' => 'presion-etl',
    'radiacion_solar' => 'solar-wm2',
    'evaporacion_potencial' => 'evap-pot-wm2',
];

$cuadros = [];
$i = 0;
foreach ($defs as $capa => $codificacion) {
    foreach (['2026-11-03T12:00:00Z', '2026-11-04T12:00:00Z'] as $j => $instante) {
        $archivo = sprintf('%s-%d.png', $capa, $j + 1);
        file_put_contents($raiz.'/'.$archivo, $png);
        $cuadros[] = [
            'id' => sprintf('demo-%s-%d', str_replace('_', '-', $capa), $j + 1),
            'capa' => $capa,
            'archivo' => $archivo,
            'instante' => $instante,
            'codificacion' => $codificacion,
            'fuente' => 'Demo local #62',
            'producto' => 'Textura tecnica de prueba',
            'rejilla' => $rejilla,
        ];
        $i++;
    }
}

$n = (new App\Services\Mapas\Radar\PublicadorRaster)->publicar($raiz, ['version' => 1, 'cuadros' => $cuadros]);
$c = (new App\Services\Mapas\Radar\CatalogoRaster)->cargar();
echo "publicado=$n capas_con_cuadros=".count(array_unique(array_column($c['cuadros'], 'capa')))."\n";
