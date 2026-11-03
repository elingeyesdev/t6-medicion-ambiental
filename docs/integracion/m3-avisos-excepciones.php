<?php

// En withExceptions de bootstrap/app.php registrar con $exceptions->render(...).
// Este archivo devuelve el callback exacto; también se usa en las pruebas aisladas.
use App\Services\Api\ErrorConsulta;
use Illuminate\Http\Request;

return static function (ErrorConsulta $e, Request $request) {
    if ($request->is('avisos/novedades', 'avisos/canales', 'avisos/push', 'cuenta/clave') && $request->expectsJson()) {
        return response()->json(['mensaje' => $e->getMessage()], $e->estado)->header('Cache-Control', 'no-store');
    }

    return null;
};
