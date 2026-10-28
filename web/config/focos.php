<?php

return [
    'clave' => env('NASA_FIRMS_CLAVE', ''),
    'producto' => 'VIIRS_SNPP_NRT',
    'version_contrato' => '1.0-m2-candidata',
    'datos_territoriales' => dirname(base_path()).'/compartido/territorios/datos',
    'cache_segundos' => 300,
    'conjunto_segundos' => 900,
    'maximo_filas' => 50000,
];
