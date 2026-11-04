<?php

return [
    'atmosfera' => [
        'habilitado' => env('GEOSUR_REPORTES_ATMOSFERA_HABILITADO', false),
        'url' => env('GEOSUR_REPORTES_METEO_URL', 'https://api.open-meteo.com/v1/forecast'),
        'aire_url' => env('GEOSUR_REPORTES_AIRE_URL', 'https://air-quality-api.open-meteo.com/v1/air-quality'),
        'clave' => env('GEOSUR_REPORTES_METEO_CLAVE'),
    ],
];
