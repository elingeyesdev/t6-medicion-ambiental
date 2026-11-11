<?php

declare(strict_types=1);

namespace App\Services\Forestal;

use App\Services\Api\ErrorConsulta;
use App\Services\Api\ParametrosConsulta;
use Illuminate\Http\Request;

/** Preparación interna de entrada, sin catálogo territorial ni respuesta de producto. */
final class ParametrosConsultaForestal
{
    /**
     * El llamador aporta disponibilidad y territorios publicados de la misma versión.
     *
     * @param  list<string>  $territoriosPublicados  Identidades exactas, sin equivalencias implícitas.
     * @return array{territorio: string, desde: int, hasta: int}
     */
    public function leer(Request $request, ?DisponibilidadForestal $disponibilidad, array $territoriosPublicados): array
    {
        $p = ParametrosConsulta::leer($request, ['territorio', 'desde', 'hasta']);
        foreach (['territorio', 'desde', 'hasta'] as $campo) {
            if (! isset($p[$campo])) {
                throw new ErrorConsulta('FILTROS_INVALIDOS', 'Se requieren territorio, desde y hasta.');
            }
        }

        foreach (['desde', 'hasta'] as $campo) {
            if (! preg_match('/^[0-9]{4}$/D', $p[$campo])) {
                throw new ErrorConsulta('FILTROS_INVALIDOS', 'desde y hasta deben ser años de cuatro cifras.');
            }
        }
        $desde = (int) $p['desde'];
        $hasta = (int) $p['hasta'];
        if ($desde > $hasta) {
            throw new ErrorConsulta('FILTROS_INVALIDOS', 'desde no puede ser posterior a hasta.');
        }

        if ($disponibilidad === null) {
            throw new ErrorConsulta('PRODUCTO_NO_DISPONIBLE', 'No se recibió disponibilidad de una versión forestal.', 503);
        }
        if (! in_array($p['territorio'], $territoriosPublicados, true)) {
            throw new ErrorConsulta('TERRITORIO_SIN_COBERTURA', 'El territorio no está publicado para esta versión.', 404);
        }
        for ($anio = $desde; $anio <= $hasta; $anio++) {
            if (! in_array($anio, $disponibilidad->anios, true)) {
                throw new ErrorConsulta('FILTROS_INVALIDOS', 'El intervalo incluye años no publicados por esta versión.');
            }
        }

        return ['territorio' => $p['territorio'], 'desde' => $desde, 'hasta' => $hasta];
    }
}
