<?php

namespace App\Services\Mapas\Capas;

use App\Services\Api\ErrorConsulta;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class ErroresConsultaMapa
{
    /** Hook explícito de bootstrap; también cubre errores anteriores al controlador. */
    public function __invoke(Throwable $e, Request $request)
    {
        if (! $request->is('mapa/capas', 'mapa/capas/*', 'mapa/poi')) {
            return null;
        }
        $estado = 500;
        $mensaje = 'No se pudo completar la consulta.';
        $codigo = 'ERROR_CONSULTA_MAPA';
        $headers = [];
        $campos = [];
        if ($e instanceof ErrorConsulta) {
            $estado = $e->estado;
            $mensaje = $e->getMessage();
            $codigo = $e->codigo;
        } elseif ($e instanceof AuthenticationException) {
            $estado = 401;
        } elseif ($e instanceof AuthorizationException) {
            $estado = 403;
        } elseif ($e instanceof ValidationException) {
            $estado = 422;
            $campos = $e->errors();
        } elseif ($e instanceof HttpExceptionInterface) {
            $estado = $e->getStatusCode();
            $headers = $e->getHeaders();
        }
        if (! $e instanceof ErrorConsulta) {
            [$codigo, $mensaje] = match ($estado) {
                401 => ['NO_AUTENTICADO', 'Vuelve a ingresar para continuar.'],
                403 => ['ACCESO_DENEGADO', 'Tu cuenta no tiene permiso para consultar el mapa.'],
                404 => ['CUADRO_NO_ENCONTRADO', 'Esta imagen no está disponible. Elige otra fecha.'],
                422 => ['FILTROS_INVALIDOS', 'Revisa los datos de la consulta.'],
                429 => ['SOLICITUDES_EXCESIVAS', 'Demasiadas consultas. Espera un minuto.'],
                default => [$codigo, $mensaje],
            };
        }

        return response()->json(['error' => ['codigo' => $codigo, 'mensaje' => $mensaje, 'campos' => $campos]], $estado, $headers)
            ->header('Cache-Control', 'no-store, private');
    }
}
