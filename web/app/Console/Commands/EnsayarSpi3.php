<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Sequias\Experimental\CalculoSpi3ConCache;
use App\Services\Sequias\Experimental\EnsayoSpi3;
use App\Services\Sequias\SeriePrecipitacionInvalida;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Console\Command;

final class EnsayarSpi3 extends Command
{
    protected $signature = 'sequias:ensayar-spi {base : ID local de serie de referencia} {objetivo : ID local de serie objetivo} {mes : Mes final AAAA-MM} {--cache : Reutilizar cálculo de estas mismas versiones} {--recalcular : Rehacer el cálculo y renovar su caché, sin descargar datos}';

    protected $description = 'Ensayo offline del SPI-3 propuesto: pendiente de revisión, no habilita la API';

    public function handle(EnsayoSpi3 $ensayo, CalculoSpi3ConCache $cache): int
    {
        try {
            $base = (string) $this->argument('base');
            $objetivo = (string) $this->argument('objetivo');
            $mes = (string) $this->argument('mes');
            $resultado = $this->option('cache') || $this->option('recalcular')
                ? $cache->ejecutar($base, $objetivo, $mes, (bool) $this->option('recalcular'))
                : $ensayo->ejecutar($base, $objetivo, $mes, new DateTimeImmutable('now', new DateTimeZone('UTC')));
            $this->line(json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));

            return $resultado['resultado']['valor'] !== null ? self::SUCCESS : self::FAILURE;
        } catch (SeriePrecipitacionInvalida $e) {
            $this->line(json_encode(['error' => ['codigo' => $e->codigo, 'mensaje' => $e->getMessage()]], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            return self::INVALID;
        }
    }
}
