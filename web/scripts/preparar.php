<?php

// Instalación local repetible: conserva la configuración y la clave existentes.
$root = dirname(__DIR__);
$envPath = $root.'/.env';

if (! is_file($envPath) && ! copy($root.'/.env.example', $envPath)) {
    throw new RuntimeException('No se pudo crear .env. Revise los permisos de web/.');
}

$contents = file_get_contents($envPath);
if ($contents === false) {
    throw new RuntimeException('No se pudo leer .env.');
}

if (preg_match('/^APP_KEY=[ \t]*\r?$/m', $contents)) {
    $key = 'base64:'.base64_encode(random_bytes(32));
    $contents = preg_replace('/^APP_KEY=[ \t]*\r?$/m', 'APP_KEY='.$key, $contents, 1);
    if (file_put_contents($envPath, $contents) === false) {
        throw new RuntimeException('No se pudo guardar APP_KEY en .env.');
    }
}

if (! is_file($root.'/database/database.sqlite') && ! touch($root.'/database/database.sqlite')) {
    throw new RuntimeException('No se pudo crear la base SQLite local.');
}

echo "Configuración local preparada. La clave y la base existentes se conservan.\n";
