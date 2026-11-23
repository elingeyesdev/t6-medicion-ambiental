<?php

namespace App\Services\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\AcceptHeader;

final class RespuestaJsonComprimible
{
    public static function preparar(Request $request, JsonResponse $respuesta): JsonResponse
    {
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

        return $respuesta->header('Content-Length', (string) strlen($respuesta->getContent()));
    }
}
