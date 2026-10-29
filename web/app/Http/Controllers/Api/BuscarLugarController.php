<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Mapas\BuscarLugar;
use Illuminate\Http\Request;

class BuscarLugarController extends Controller
{
    public function __invoke(Request $request, BuscarLugar $buscador)
    {
        $datos = $request->validate(['texto' => ['required', 'string', 'min:2', 'max:120']]);

        return response()->json($buscador->ejecutar($datos['texto']))->header('Cache-Control', 'no-store');
    }
}
