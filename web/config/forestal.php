<?php

return [
    'revision_local' => env('FORESTAL_REVISION_LOCAL', false),
    'directorio_revisiones' => env('FORESTAL_REVISION_DIR', storage_path('app/private/forestal/revisiones')),
];
