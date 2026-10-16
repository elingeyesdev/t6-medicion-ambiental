<?php

declare(strict_types=1);

namespace App\Services\Territorios;

use InvalidArgumentException;

final class FiltroTerritorialInvalido extends InvalidArgumentException
{
    public function __construct(public readonly string $codigo, string $mensaje)
    {
        parent::__construct($mensaje);
    }
}
