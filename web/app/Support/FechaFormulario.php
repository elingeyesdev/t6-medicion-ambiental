<?php

namespace App\Support;

use App\Services\Api\ErrorConsulta;

final class FechaFormulario
{
    public static function utc(string $valor): string
    {
        $fecha = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $valor, new \DateTimeZone('America/La_Paz'));
        if (! $fecha || $fecha->format('Y-m-d\TH:i') !== $valor) {
            throw new ErrorConsulta('FILTROS_INVALIDOS', 'Revisa la fecha elegida.');
        }

        return $fecha->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }
}
