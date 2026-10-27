<?php

use App\Services\Api\ErrorConsulta;
use App\Services\Territorios\FiltroTerritorialInvalido;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('mapa'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport([ErrorConsulta::class]);
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/v1/*')) {
                return null;
            }
            $estado = 500;
            $codigo = 'ERROR_INTERNO';
            $mensaje = 'No se pudo completar la consulta.';
            $campos = [];
            if ($e instanceof ErrorConsulta) {
                $estado = $e->estado;
                $codigo = $e->codigo;
                $mensaje = $e->getMessage();
                $campos = $e->campos;
            } elseif ($e instanceof FiltroTerritorialInvalido) {
                $estado = 422;
                $codigo = $e->codigo === 'FILTROS_INCOMPATIBLES' ? $e->codigo : 'FILTROS_INVALIDOS';
                $mensaje = $e->getMessage();
            } elseif ($e instanceof HttpExceptionInterface) {
                $estado = $e->getStatusCode();
                $codigo = match ($estado) {
                    404 => 'RUTA_NO_ENCONTRADA', 405 => 'METODO_NO_ADMITIDO', 429 => 'SOLICITUDES_EXCESIVAS', default => 'ERROR_HTTP'
                };
                $mensaje = match ($estado) {
                    404 => 'Ruta no encontrada.', 405 => 'Solo se admiten consultas GET.', 429 => 'Demasiadas consultas. Espera un minuto.', default => 'No se pudo completar la solicitud.'
                };
            } elseif ($e instanceof RuntimeException || $e instanceof JsonException) {
                $estado = 503;
                $codigo = 'CATALOGO_NO_DISPONIBLE';
                $mensaje = 'No se puede leer el catálogo, sus límites o el almacenamiento de consulta.';
            }
            $respuesta = response()->json(['error' => ['codigo' => $codigo, 'mensaje' => $mensaje, 'identificadorConsulta' => (string) Str::uuid(), 'campos' => $campos]], $estado)->header('Cache-Control', 'no-store');
            if ($e instanceof HttpExceptionInterface) {
                $respuesta->withHeaders($e->getHeaders());
            }

            return $respuesta;
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson()
        );
    })->create();
