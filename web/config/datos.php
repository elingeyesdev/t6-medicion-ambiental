<?php

return [
    'tiempo_habilitado' => (bool) env('GEOSUR_TIEMPO_HABILITADO', false),
    'mqtt' => [
        'habilitado' => (bool) env('GEOSUR_MQTT_HABILITADO', false),
        'url' => env('GEOSUR_MQTT_URL', ''),
        'usuario' => env('GEOSUR_MQTT_USUARIO', ''),
        'clave' => env('GEOSUR_MQTT_CLAVE', ''),
        'cliente' => env('GEOSUR_MQTT_CLIENTE', 'geosur-datos-local'),
        'ca' => env('GEOSUR_MQTT_CA', ''),
        'espera' => (int) env('GEOSUR_MQTT_ESPERA', 5),
        'temas' => env('GEOSUR_MQTT_TEMAS', '{}'),
    ],
];
