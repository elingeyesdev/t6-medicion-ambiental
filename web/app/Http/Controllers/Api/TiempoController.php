<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Tiempo\ConsultarPronostico;
use Illuminate\Http\Request;

class TiempoController extends Controller
{
    public function __invoke(Request $request, ConsultarPronostico $pronostico)
    {
        $datos = $request->validate(['latitud' => ['required', 'numeric', 'between:-90,90'], 'longitud' => ['required', 'numeric', 'between:-180,180']]);

        return response()->json($pronostico->ejecutar((float) $datos['latitud'], (float) $datos['longitud']))->header('Cache-Control', 'no-store');
    }
}
