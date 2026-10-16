<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class SaludController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'servicio' => 'GeoCruz',
            'estado' => 'disponible',
            'alcance' => 'aplicacion',
            'fecha' => now()->utc()->toIso8601String(),
        ])->header('Cache-Control', 'no-store');
    }
}
