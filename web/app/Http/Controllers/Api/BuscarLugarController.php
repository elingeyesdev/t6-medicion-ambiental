<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Mapas\BuscarLugar;
use Illuminate\Http\Request;

class BuscarLugarController extends Controller
{
    public function __invoke(Request $request, BuscarLugar $buscador)
    {
        $datos = $request->validate([
            'texto' => ['required', 'string', 'min:2', 'max:120'],
            'tipo' => ['sometimes', 'string', 'in:lugar,sitio'],
            'latitud' => ['sometimes', 'required_with:longitud', 'numeric', 'between:-90,90'],
            'longitud' => ['sometimes', 'required_with:latitud', 'numeric', 'between:-180,180'],
        ]);

        return response()->json($buscador->ejecutar($datos['texto'], $datos['tipo'] ?? 'lugar', isset($datos['latitud']) ? (float) $datos['latitud'] : null, isset($datos['longitud']) ? (float) $datos['longitud'] : null))->header('Cache-Control', 'no-store');
    }
}
