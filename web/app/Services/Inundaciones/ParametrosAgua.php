<?php

namespace App\Services\Inundaciones;

use App\Services\Api\ErrorConsulta;
use App\Services\Api\ParametrosConsulta;
use App\Services\Territorios\CatalogoTerritorial;
use App\Services\Territorios\FiltroTerritorialInvalido;
use Illuminate\Http\Request;

final class ParametrosAgua
{
    public function __construct(private CatalogoTerritorial $catalogo) {}

    public function leer(Request $request): array
    {
        $p = ParametrosConsulta::leer($request, ['territorio', 'referencia', 'comparacion']);
        foreach (['territorio', 'referencia', 'comparacion'] as $campo) {
            if (! isset($p[$campo])) {
                throw new ErrorConsulta('FILTROS_INVALIDOS', 'Elige lugar y las dos fechas.');
            }
        }
        if (! preg_match('/^BO07(?:[0-9]{2}){0,2}$/D', $p['territorio'])) {
            throw new ErrorConsulta('FILTROS_INVALIDOS', 'Elige un lugar del catálogo disponible.');
        }
        try {
            $territorio = $this->catalogo->buscar($p['territorio']);
        } catch (FiltroTerritorialInvalido) {
            throw new ErrorConsulta('TERRITORIO_NO_DISPONIBLE', 'El lugar no está disponible.', 404);
        }
        $fechas = [];
        foreach (['referencia', 'comparacion'] as $campo) {
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $p[$campo])) {
                throw new ErrorConsulta('FILTROS_INVALIDOS', 'Revisa las fechas.');
            }
            $fecha = ParametrosConsulta::instante($p[$campo].'T00:00:00Z');
            if ($fecha > now('UTC')->toDateTimeImmutable()) {
                throw new ErrorConsulta('FILTROS_INVALIDOS', 'Las fechas no pueden ser futuras.');
            }
            $fechas[$campo] = $p[$campo];
        }
        if ($fechas['referencia'] >= $fechas['comparacion']) {
            throw new ErrorConsulta('FILTROS_INCOMPATIBLES', 'La fecha de referencia debe ser anterior.');
        }

        return ['territorio' => $territorio['identificador'], ...$fechas];
    }
}
