<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Sequias\Experimental\EnsayoSpi3;
use App\Services\Sequias\SeriePrecipitacionInvalida;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Console\Command;

final class EnsayarSpi3 extends Command
{
    protected $signature = 'sequias:ensayar-spi {base : ID local de serie de referencia} {objetivo : ID local de serie objetivo} {mes : Mes final AAAA-MM}';

    protected $description = 'Ensayo offline del SPI-3 propuesto: pendiente de revisión, no habilita la API';

    public function handle(EnsayoSpi3 $ensayo): int
    {
        try {
            $resultado = $ensayo->ejecutar((string) $this->argument('base'), (string) $this->argument('objetivo'),
                (string) $this->argument('mes'), new DateTimeImmutable('now', new DateTimeZone('UTC')));
            $this->line(json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));

            return $resultado['resultado']['valor'] !== null ? self::SUCCESS : self::FAILURE;
        } catch (SeriePrecipitacionInvalida $e) {
            $this->line(json_encode(['error' => ['codigo' => $e->codigo, 'mensaje' => $e->getMessage()]], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            return self::INVALID;
        }
    }
}
