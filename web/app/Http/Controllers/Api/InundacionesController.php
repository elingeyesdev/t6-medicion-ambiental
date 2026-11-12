<?php

namespace App\Http\Controllers\Api;

use App\Services\Inundaciones\CatalogoProductosAgua;
use App\Services\Inundaciones\ParametrosAgua;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class InundacionesController
{
    public function catalogo(CatalogoProductosAgua $productos): JsonResponse
    {
        return response()->json($productos->catalogo())->header('Cache-Control', 'no-store');
    }

    public function consultar(Request $request, ParametrosAgua $parametros, CatalogoProductosAgua $productos): JsonResponse
    {
        $producto = $productos->consultar($parametros->leer($request));

        return response()->json($producto['dato'])->header('Cache-Control', 'no-store')
            ->header('X-Producto-SHA256', $producto['sha256']);
    }
}
