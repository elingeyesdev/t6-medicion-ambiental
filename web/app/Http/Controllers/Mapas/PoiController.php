<?php

namespace App\Http\Controllers\Mapas;

use App\Http\Requests\Mapas\PoiRequest;
use App\Services\Api\ErrorConsulta;
use App\Services\Mapas\Capas\BuscarPoi;

final class PoiController
{
    public function __invoke(PoiRequest $request, BuscarPoi $buscador)
    {
        $datos = $request->validated();
        try {
            return response()->json($buscador->ejecutar($datos['texto'], $datos['pais']))->header('Cache-Control', 'no-store, private');
        } catch (ErrorConsulta $e) {
            return response()->json(['error' => ['codigo' => $e->codigo, 'mensaje' => $e->getMessage()]], $e->estado)
                ->header('Cache-Control', 'no-store, private');
        }
    }
}
