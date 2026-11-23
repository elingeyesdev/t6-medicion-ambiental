<?php

return [
    'clave' => env('NASA_FIRMS_CLAVE', ''),
    'producto' => env('NASA_FIRMS_PRODUCTO_RECIENTE', 'VIIRS_NOAA20_NRT'),
    'producto_reciente_respaldo' => 'VIIRS_NOAA21_NRT',
    'producto_historico' => 'VIIRS_SNPP_SP',
    'version_contrato' => '1.0-m2-candidata',
    'datos_territoriales' => dirname(base_path()).'/compartido/territorios/datos',
    'cache_segundos' => 300,
    'conjunto_segundos' => 900,
    'maximo_filas' => 50000,
];
