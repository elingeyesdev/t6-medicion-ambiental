<?php

return [
    // Blade usa su sesión/CSRF; estas rutas móviles requieren un Bearer, sin aceptar cookies como alternativa.
    'guard' => [],
    'expiration' => 1440,
];
