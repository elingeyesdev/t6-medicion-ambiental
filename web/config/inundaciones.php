<?php

return [
    // Solo revisión local; nunca habilita datos ambientales ni una entrada pública.
    'revision_local' => env('AGUA_REVISION_LOCAL', false),
];
