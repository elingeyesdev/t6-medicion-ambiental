<?php

namespace App\Http\Controllers\Api;

use App\Services\Api\ErrorConsulta;
use App\Services\Inundaciones\ParametrosAgua;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class InundacionesController
{
    public function catalogo(): JsonResponse
    {
        return response()->json([
            'versionContrato' => 'agua-0.1-candidata',
            'estado' => 'sin_productos_validados',
            'productos' => [],
        ])->header('Cache-Control', 'no-store');
    }

    public function consultar(Request $request, ParametrosAgua $parametros): never
    {
        $parametros->leer($request);
        // #111 aún no publica productos: no usar fixtures, calcular ni descargar al consultar.
        throw new ErrorConsulta('PRODUCTO_NO_DISPONIBLE', 'No hay una comparación disponible para ese lugar y fechas.', 503);
    }
}
