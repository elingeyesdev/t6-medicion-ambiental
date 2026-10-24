<?php

declare(strict_types=1);

namespace App\Services\Sequias\Experimental;

/** Entrada interna inmutable, producida tras verificar las versiones locales. */
final readonly class PreparacionSpi3
{
    public function __construct(public array $informe) {}
}
