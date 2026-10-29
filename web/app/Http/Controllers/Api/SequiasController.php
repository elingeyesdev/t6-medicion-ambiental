<?php

namespace App\Http\Controllers\Api;

use App\Services\Api\ErrorConsulta;
use App\Services\Api\ParametrosConsulta;
use App\Services\Sequias\ConsultaSequias;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SequiasController
{
    public function index(Request $request, ConsultaSequias $consulta): JsonResponse
    {
        return $this->responder($consulta->ejecutar($this->parametros($request)));
    }

    public function detalle(Request $request, string $muestra, ConsultaSequias $consulta): JsonResponse
    {
        $p = $this->parametros($request);
        if (isset($p['muestra']) && $p['muestra'] !== $muestra) {
            throw new ErrorConsulta('FILTROS_INCOMPATIBLES', 'La muestra de la ruta y la del filtro deben coincidir.');
        }

        return $this->responder($consulta->ejecutar([...$p, 'muestra' => $muestra]));
    }

    public function resumen(Request $request, ConsultaSequias $consulta): JsonResponse
    {
        $r = $consulta->ejecutar($this->parametros($request));

        return $this->responder(array_diff_key($r, array_flip(['datos', 'paginacion', 'representacionMapa'])));
    }

    private function parametros(Request $request): array
    {
        return ParametrosConsulta::leer($request, ['mesFinal', 'provincia', 'municipio', 'muestra']);
    }

    private function responder(array $r): JsonResponse
    {
        return response()->json($r, 200, [], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION)->header('Cache-Control', 'no-store');
    }
}
