<?php

namespace App\Http\Controllers\Api;

use App\Services\Api\ParametrosConsulta;
use App\Services\Datos\PanoramaAmbiental;
use Illuminate\Http\Request;

class MapaMonitoreoController
{
    public function __invoke(Request $request, PanoramaAmbiental $panorama)
    {
        $filtros = ParametrosConsulta::leer($request, ['pais', 'metrica', 'origen', 'desde', 'hasta', 'pagina']);

        return response()->json($panorama->consultar($filtros))->header('Cache-Control', 'no-store');
    }
}
