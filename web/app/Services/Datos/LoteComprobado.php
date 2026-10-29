<?php

namespace App\Services\Datos;

use RuntimeException;

/** Resultado interno: obliga a revertir la transacción de comprobación. */
final class LoteComprobado extends RuntimeException
{
    public function __construct(public readonly array $resultado)
    {
        parent::__construct('Lote comprobado sin guardar.');
    }
}
