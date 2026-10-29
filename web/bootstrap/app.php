<?php

use App\Http\Middleware\CuentaActiva;
use App\Services\Api\ErrorConsulta;
use App\Services\Territorios\FiltroTerritorialInvalido;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
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
        $middleware->alias(['cuenta.activa' => CuentaActiva::class, 'abilities' => CheckAbilities::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport([ErrorConsulta::class]);
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/v1/*', 'mapa/puntos', 'mapa/tiempo', 'mapa/buscar')) {
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
            } elseif ($e instanceof AuthenticationException) {
                $estado = 401;
                $codigo = 'NO_AUTENTICADO';
                $mensaje = 'Vuelve a ingresar para continuar.';
            } elseif ($e instanceof AuthorizationException) {
                $estado = 403;
                $codigo = 'ACCESO_DENEGADO';
                $mensaje = 'Tu cuenta no tiene permiso para hacer este cambio.';
            } elseif ($e instanceof ValidationException) {
                $estado = 422;
                $codigo = 'FILTROS_INVALIDOS';
                $mensaje = 'Revisa los datos del formulario.';
                $campos = $e->errors();
            } elseif ($e instanceof FiltroTerritorialInvalido) {
                $estado = 422;
                $codigo = $e->codigo === 'FILTROS_INCOMPATIBLES' ? $e->codigo : 'FILTROS_INVALIDOS';
                $mensaje = $e->getMessage();
            } elseif ($e instanceof HttpExceptionInterface) {
                $estado = $e->getStatusCode();
                $codigo = match ($estado) {
                    401 => 'NO_AUTENTICADO', 403 => 'ACCESO_DENEGADO', 404 => 'RUTA_NO_ENCONTRADA', 405 => 'METODO_NO_ADMITIDO', 429 => 'SOLICITUDES_EXCESIVAS', default => 'ERROR_HTTP'
                };
                $mensaje = match ($estado) {
                    401 => 'Vuelve a ingresar para continuar.', 403 => 'Tu cuenta no tiene permiso para hacer este cambio.',
                    404 => 'Ruta no encontrada.', 405 => 'Método no admitido.', 429 => 'Demasiadas consultas. Espera un minuto.', default => 'No se pudo completar la solicitud.'
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
            fn (Request $request): bool => $request->is('api/*', 'mapa/puntos', 'mapa/tiempo', 'mapa/buscar') || $request->expectsJson()
        );
    })->create();
