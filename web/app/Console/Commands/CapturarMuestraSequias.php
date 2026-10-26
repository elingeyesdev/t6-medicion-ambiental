<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Sequias\Experimental\CapturaMuestraCatalogada;
use App\Services\Sequias\SeriePrecipitacionInvalida;
use Illuminate\Console\Command;

final class CapturarMuestraSequias extends Command
{
    protected $signature = 'sequias:capturar-muestra {muestra} {--desde=} {--hasta=} {--provincia=} {--municipio=} {--renovar}';

    protected $description = 'Capturar lluvia candidata usando una muestra registrada y su contexto territorial';

    public function handle(CapturaMuestraCatalogada $capturador): int
    {
        try {
            $r = $capturador->ejecutar((string) $this->argument('muestra'), (string) $this->option('desde'), (string) $this->option('hasta'),
                (bool) $this->option('renovar'), $this->option('provincia'), $this->option('municipio'));
            unset($r['version']['informe']['meses']);
            $this->line(json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));

            return $r['version']['informe']['aptoParaAgregacionMensual'] ? self::SUCCESS : self::FAILURE;
        } catch (SeriePrecipitacionInvalida $e) {
            $this->line(json_encode(['error' => ['codigo' => $e->codigo, 'mensaje' => $e->getMessage()]], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            return self::INVALID;
        }
    }
}
