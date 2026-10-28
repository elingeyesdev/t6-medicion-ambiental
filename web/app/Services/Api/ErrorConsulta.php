<?php

namespace App\Services\Api;

use RuntimeException;

final class ErrorConsulta extends RuntimeException
{
    public function __construct(public readonly string $codigo, string $mensaje, public readonly int $estado = 422, public readonly array $campos = [])
    {
        parent::__construct($mensaje);
    }
}
