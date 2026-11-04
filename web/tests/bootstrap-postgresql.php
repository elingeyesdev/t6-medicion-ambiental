<?php

// Este archivo nunca abre la base de la demo. Requiere una base de pruebas nombrada.
if (getenv('GEOSUR_PRUEBA_POSTGRES') !== '1' || getenv('DB_DATABASE') !== 'geosur_m3_tests' ||
    getenv('DB_HOST') !== '127.0.0.1' || getenv('DB_CONNECTION') !== 'pgsql' || getenv('DB_URL')) {
    throw new RuntimeException('Usa el entorno PostgreSQL aislado de pruebas antes de ejecutar esta configuración.');
}

require dirname(__DIR__).'/vendor/autoload.php';
