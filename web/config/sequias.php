<?php

return [
    // Originales y derivados privados; se preparan fuera de las peticiones HTTP.
    'directorio_series' => storage_path('app/private/sequias/series'),
    'archivo_muestras' => base_path('../compartido/sequias/muestras.json'),
    'directorio_publicaciones' => storage_path('app/private/sequias/publicaciones'),
];
