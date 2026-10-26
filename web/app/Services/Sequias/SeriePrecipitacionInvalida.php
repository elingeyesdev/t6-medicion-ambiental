<?php

declare(strict_types=1);

namespace App\Services\Sequias;

use InvalidArgumentException;

final class SeriePrecipitacionInvalida extends InvalidArgumentException
{
    public function __construct(public readonly string $codigo, string $mensaje)
    {
        parent::__construct($mensaje);
    }
}
