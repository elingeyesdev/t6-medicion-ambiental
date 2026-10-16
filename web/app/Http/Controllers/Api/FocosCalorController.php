<?php

namespace App\Http\Controllers\Api;

use App\Services\Api\ParametrosConsulta;
use App\Services\FocosCalor\ConsultaFocos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class FocosCalorController
{
    public function __invoke(Request $request, ConsultaFocos $consulta): JsonResponse
    {
        $p = ParametrosConsulta::leer($request, ['provincia', 'municipio', 'desde', 'hasta', 'modo', 'confianza', 'pagina', 'tamanioPagina', 'versionConjunto']);

        return response()->json($consulta->ejecutar($p))->header('Cache-Control', 'no-store');
    }
}
