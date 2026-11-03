<?php

return [
    // Publicación privada y local: nunca public/ ni una URL recibida del cliente.
    'directorio' => env('MAPAS_RASTER_DIRECTORIO', storage_path('app/private/mapas/raster')),
    'max_cuadros' => 512,
    'max_bytes' => 5242880,
    'max_pixeles' => 2200000,
    'actualizacion' => [
        'habilitada' => env('MAPAS_ACTUALIZACION_HABILITADA', false),
        'python' => env('MAPAS_ACTUALIZACION_PYTHON'),
        'trabajo' => env('MAPAS_ACTUALIZACION_TRABAJO', storage_path('app/private/mapas/actualizacion')),
        'fechas_por_capa' => 48,
    ],
    'poi_habilitado' => env('MAPAS_POI_HABILITADO', false),
    'poi_token' => env('MAPAS_POI_TOKEN', env('MAPBOX_PUBLIC_TOKEN')),
    // Subconjunto explícito de la cobertura documentada de Search Box.
    'poi_paises' => [
        'us' => 'Estados Unidos', 'ca' => 'Canadá', 'es' => 'España',
        'pt' => 'Portugal', 'fr' => 'Francia', 'de' => 'Alemania',
        'gb' => 'Reino Unido', 'it' => 'Italia', 'nl' => 'Países Bajos',
    ],
];
