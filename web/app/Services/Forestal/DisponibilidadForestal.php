<?php

declare(strict_types=1);

namespace App\Services\Forestal;

use InvalidArgumentException;

/** Años declarados por una versión; no descubre ni publica productos. */
final readonly class DisponibilidadForestal
{
    /** @param list<int> $anios */
    public function __construct(public string $version, public array $anios)
    {
        if (trim($version) === '' || $anios === [] || ! array_is_list($anios)) {
            throw new InvalidArgumentException('La disponibilidad requiere versión y una lista de años publicados.');
        }

        $vistos = [];
        foreach ($anios as $anio) {
            if (! is_int($anio) || $anio < 1000 || $anio > 9999 || isset($vistos[$anio])) {
                throw new InvalidArgumentException('Los años publicados deben ser enteros de cuatro cifras sin duplicados.');
            }
            $vistos[$anio] = true;
        }
    }
}
