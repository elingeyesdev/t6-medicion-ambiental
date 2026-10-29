<?php

namespace App\Http\Controllers\Api;

use App\Services\Api\ErrorConsulta;
use App\Services\Api\ParametrosConsulta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PaisesController
{
    public function __invoke(Request $request): JsonResponse
    {
        $parametros = ParametrosConsulta::leer($request, ['codigo']);
        $catalogo = json_decode(file_get_contents(base_path('../compartido/geografia/paises-envirosense.json')), true, 512, JSON_THROW_ON_ERROR);
        $datos = $catalogo['datos'];
        if (isset($parametros['codigo'])) {
            $datos = array_values(array_filter($datos, fn ($pais) => $pais['codigo'] === $parametros['codigo']));
            if ($datos === []) {
                throw new ErrorConsulta('TERRITORIO_NO_ENCONTRADO', 'Ese país no figura en el catálogo recuperado de EnviroSense.', 404);
            }
        }

        return response()->json([
            'versionCatalogo' => $catalogo['version'],
            'datos' => $datos,
            'fuentes' => $catalogo['fuentes'],
            'alcance' => 'Catálogo del sistema original. No acredita cobertura de datos ambientales en cada país.',
            'consultasDisponibles' => [['pais' => 'BO', 'territorio' => 'BO07', 'nombre' => 'Santa Cruz', 'consultas' => ['focos-calor', 'sequias']]],
        ])->header('Cache-Control', 'no-store');
    }
}
