<?php

return [
    // La outbox nunca se procesa desde una petición ni desde el scheduler por defecto.
    'procesar' => env('AVISOS_PROCESAR', false),
    'push' => [
        'habilitado' => env('AVISOS_PUSH_HABILITADO', false),
        'proyecto' => env('AVISOS_PUSH_PROYECTO'),
        'cliente_email' => env('AVISOS_PUSH_CLIENTE_EMAIL'),
        'clave_privada' => env('AVISOS_PUSH_CLAVE_PRIVADA'),
        'api_key_publica' => env('AVISOS_PUSH_API_KEY_PUBLICA'),
        'app_id' => env('AVISOS_PUSH_APP_ID'),
        'sender_id' => env('AVISOS_PUSH_SENDER_ID'),
        'vapid_publica' => env('AVISOS_PUSH_VAPID_PUBLICA'),
    ],
    'email' => [
        'habilitado' => env('AVISOS_EMAIL_HABILITADO', false),
        'mailer' => env('AVISOS_EMAIL_MAILER', 'log'),
    ],
    'telegram' => [
        'habilitado' => env('AVISOS_TELEGRAM_HABILITADO', false),
        'token' => env('AVISOS_TELEGRAM_TOKEN'),
    ],
    'whatsapp' => [
        'habilitado' => env('AVISOS_WHATSAPP_HABILITADO', false),
        'token' => env('AVISOS_WHATSAPP_TOKEN'),
        'telefono_id' => env('AVISOS_WHATSAPP_TELEFONO_ID'),
        'version' => env('AVISOS_WHATSAPP_VERSION'),
        'plantilla' => env('AVISOS_WHATSAPP_PLANTILLA'),
        'idioma' => env('AVISOS_WHATSAPP_IDIOMA', 'es'),
    ],
];
