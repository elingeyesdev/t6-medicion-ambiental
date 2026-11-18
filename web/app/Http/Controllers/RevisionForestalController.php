<?php

namespace App\Http\Controllers;

use App\Services\Api\ErrorConsulta;
use App\Services\Api\ParametrosConsulta;
use App\Services\Forestal\RepositorioRevisionForestal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RevisionForestalController extends Controller
{
    public function index(Request $request): Response
    {
        $valores = ['id' => '', 'desde' => '', 'hasta' => ''];
        $error = null;
        $estado = 200;
        try {
            $valores = [...$valores, ...$this->parametros($request, false)];
        } catch (ErrorConsulta $e) {
            $error = $e->getMessage();
            $estado = $e->estado;
        }

        return response()->view('revision.forestal', [
            'modulos' => array_filter(config('geocruz.modulos'), fn ($modulo) => $modulo['habilitado']),
            'valores' => $valores, 'error' => $error,
        ], $estado)->header('Cache-Control', 'no-store');
    }

    public function consultar(Request $request): JsonResponse
    {
        try {
            $p = $this->parametros($request, true);
            $resultado = $this->consultarPaquete($p['id'], (int) $p['desde'], (int) $p['hasta']);

            return response()->json($resultado, 200, [], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION)
                ->header('Cache-Control', 'no-store');
        } catch (ErrorConsulta $e) {
            return response()->json(['error' => ['codigo' => $e->codigo, 'mensaje' => $e->getMessage(), 'campos' => $e->campos]], $e->estado)
                ->header('Cache-Control', 'no-store');
        }
    }

    private function parametros(Request $request, bool $obligatorios): array
    {
        $p = ParametrosConsulta::leer($request, ['id', 'desde', 'hasta']);
        if ($obligatorios && count($p) !== 3) {
            throw new ErrorConsulta('FILTROS_INVALIDOS', 'Se requieren id, desde y hasta.');
        }
        if (isset($p['id']) && ! preg_match('/^[a-f0-9]{64}$/D', $p['id'])) {
            throw new ErrorConsulta('FILTROS_INVALIDOS', 'El código del paquete no es válido.');
        }
        foreach (['desde', 'hasta'] as $campo) {
            if (isset($p[$campo]) && ! preg_match('/^[0-9]{4}$/D', $p[$campo])) {
                throw new ErrorConsulta('FILTROS_INVALIDOS', 'desde y hasta deben ser años de cuatro cifras.');
            }
        }
        if (isset($p['desde'], $p['hasta']) && (int) $p['desde'] > (int) $p['hasta']) {
            throw new ErrorConsulta('FILTROS_INVALIDOS', 'desde no puede ser posterior a hasta.');
        }

        // La ventana disponible pertenece al paquete y se valida en su lector.
        return $p;
    }

    /** Punto de sustitución en pruebas HTTP, sin reemplazar el lector real. */
    protected function consultarPaquete(string $id, int $desde, int $hasta): array
    {
        if (! class_exists(RepositorioRevisionForestal::class)) {
            throw new ErrorConsulta('REVISION_NO_DISPONIBLE', 'El paquete de revisión no está disponible.', 503);
        }

        return (new RepositorioRevisionForestal(config('forestal.directorio_revisiones')))->consultar($id, $desde, $hasta);
    }
}
