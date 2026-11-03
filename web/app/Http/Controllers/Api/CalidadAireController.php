<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Aire\ConsultarCalidadAire;
use Illuminate\Http\Request;

class CalidadAireController extends Controller
{
    public function __invoke(Request $request, ConsultarCalidadAire $aire)
    {
        $datos = $request->validate(['latitud' => ['required', 'numeric', 'between:-90,90'], 'longitud' => ['required', 'numeric', 'between:-180,180']]);

        return response()->json($aire->ejecutar((float) $datos['latitud'], (float) $datos['longitud']))->header('Cache-Control', 'no-store');
    }
}
