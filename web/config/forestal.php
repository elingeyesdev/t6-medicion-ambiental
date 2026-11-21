<?php

return [
    // Identidad reproducible del recorte revisado de Santa Rosa.
    'piloto_id' => '78a62654465d330e4aa0f241d74e237bfa65de709dee827099dc8594d4e88730',
    'revision_local' => env('FORESTAL_REVISION_LOCAL', false),
    'directorio_revisiones' => env('FORESTAL_REVISION_DIR', storage_path('app/private/forestal/revisiones')),
];
