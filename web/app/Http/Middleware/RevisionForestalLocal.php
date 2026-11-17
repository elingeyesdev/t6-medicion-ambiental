<?php

namespace App\Http\Middleware;

use App\Services\Api\ErrorConsulta;
use Closure;
use Illuminate\Http\Request;

/** Cierre por petición: también protege rutas que hayan sido cacheadas. */
class RevisionForestalLocal
{
    public function handle(Request $request, Closure $next): mixed
    {
        abort_unless(app()->environment(['local', 'testing']) && config('forestal.revision_local') === true, 404);

        $json = $request->routeIs('revision.forestal.consulta');
        if ($json) {
            $request->headers->set('Accept', 'application/json');
        }
        $respuesta = $next($request);
        // El pipeline de Laravel ya convirtió el error de CuentaActiva en respuesta.
        // Normalizar solo este endpoint, sin modificar el handler global.
        $error = $respuesta->exception ?? null;
        if ($json && $error instanceof ErrorConsulta) {
            $respuesta = response()->json(['error' => ['codigo' => $error->codigo, 'mensaje' => $error->getMessage(), 'campos' => $error->campos]], $error->estado);
        }

        return $respuesta->header('Cache-Control', 'no-store');
    }
}
