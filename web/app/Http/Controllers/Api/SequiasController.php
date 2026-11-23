<?php

namespace App\Http\Controllers\Api;

use App\Services\Api\ErrorConsulta;
use App\Services\Api\ParametrosConsulta;
use App\Services\Sequias\ConsultaSequias;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\AcceptHeader;

final class SequiasController
{
    public function index(Request $request, ConsultaSequias $consulta): JsonResponse
    {
        return $this->responder($request, $consulta->ejecutar($this->parametros($request)));
    }

    public function detalle(Request $request, string $muestra, ConsultaSequias $consulta): JsonResponse
    {
        $p = $this->parametros($request);
        if (isset($p['muestra']) && $p['muestra'] !== $muestra) {
            throw new ErrorConsulta('FILTROS_INCOMPATIBLES', 'La muestra de la ruta y la del filtro deben coincidir.');
        }

        return $this->responder($request, $consulta->ejecutar([...$p, 'muestra' => $muestra]));
    }

    public function resumen(Request $request, ConsultaSequias $consulta): JsonResponse
    {
        $r = $consulta->ejecutar($this->parametros($request));

        return $this->responder($request, array_diff_key($r, array_flip(['datos', 'paginacion', 'representacionMapa'])));
    }

    private function parametros(Request $request): array
    {
        return ParametrosConsulta::leer($request, ['mesFinal', 'provincia', 'municipio', 'muestra']);
    }

    private function responder(Request $request, array $r): JsonResponse
    {
        $respuesta = response()->json($r, 200, [], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

        $contenido = $respuesta->getContent();
        $codificaciones = AcceptHeader::fromString($request->headers->get('Accept-Encoding'));
        $gzip = $codificaciones->get('gzip');

        if (strlen($contenido) >= 4096 && $gzip !== null && $gzip->getQuality() > 0 && function_exists('gzencode')) {
            $comprimido = gzencode($contenido);
            if ($comprimido !== false) {
                $respuesta->setContent($comprimido);
                $respuesta->header('Content-Encoding', 'gzip');
            }
        }

        $respuesta->setVary('Accept-Encoding', false);

        return $respuesta->header('Cache-Control', 'no-store')
            ->header('Content-Length', (string) strlen($respuesta->getContent()));
    }
}
