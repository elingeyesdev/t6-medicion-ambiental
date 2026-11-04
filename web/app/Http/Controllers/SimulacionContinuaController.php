<?php

namespace App\Http\Controllers;

use App\Services\Api\ErrorConsulta;
use App\Services\Simulaciones\ContinuarSimulacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SimulacionContinuaController extends Controller
{
    public function estado(Request $request, ContinuarSimulacion $motor)
    {
        Gate::authorize('datos.consultar');

        return response()->json($motor->estado($request->user()->id))->header('Cache-Control', 'no-store');
    }

    public function accion(Request $request, ContinuarSimulacion $motor)
    {
        Gate::authorize('simulaciones.gestionar');
        try {
            return response()->json($motor->accion($request->except('_token'), $request->user()->id))->header('Cache-Control', 'no-store');
        } catch (ErrorConsulta $e) {
            return response()->json(['error' => ['codigo' => $e->codigo, 'mensaje' => $e->getMessage()]], $e->estado)->header('Cache-Control', 'no-store');
        }
    }
}
