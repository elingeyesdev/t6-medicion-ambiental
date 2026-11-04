<?php

namespace App\Http\Controllers;

use App\Services\Datos\CatalogoAmbiental;
use App\Services\Territorios\RegionesEnvirosense;
use Illuminate\Http\Request;

class MapaMonitoreoController extends Controller
{
    public function __invoke(Request $request, CatalogoAmbiental $catalogo, RegionesEnvirosense $regiones)
    {
        // Los enlaces antiguos de focos conservan su destino al separar el mapa principal.
        if ($request->has('modo') || $request->has('confianza') || $request->has('territorio')) {
            return redirect()->route('modulos.mostrar', ['modulo' => 'focos-calor'] + $request->query());
        }

        return view('mapa.index', [
            'modulos' => array_filter(config('geocruz.modulos'), fn ($modulo) => $modulo['habilitado']),
            'paises' => $catalogo->paises(), 'metricas' => $catalogo->metricas(),
            'configuracionMonitoreo' => [
                'api' => route('mapa.puntos'), 'historial' => route('sensores'), 'tiempo' => route('mapa.tiempo'), 'buscar' => route('mapa.buscar'),
                'aire' => route('mapa.aire'),
                'metricas' => $catalogo->metricas(), 'paises' => $catalogo->paises(),
                'origenes' => CatalogoAmbiental::ORIGENES,
                'regiones' => array_map(fn ($r) => array_intersect_key($r, array_flip(['codigo', 'pais', 'nombre', 'nivel'])), $regiones->listado()),
            ],
        ]);
    }
}
