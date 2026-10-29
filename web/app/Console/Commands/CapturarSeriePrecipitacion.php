<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Sequias\Experimental\CapturadorPrecipitacion;
use App\Services\Sequias\SeriePrecipitacionInvalida;
use Illuminate\Console\Command;

final class CapturarSeriePrecipitacion extends Command
{
    protected $signature = 'sequias:capturar-serie {muestra} {--longitud=} {--latitud=} {--desde=} {--hasta=} {--renovar : Ignorar la captura reciente y solicitar otra versión}';

    protected $description = 'Captura candidata Open-Meteo/ERA5: requiere Internet salvo reutilización de captura reciente';

    public function handle(CapturadorPrecipitacion $capturador): int
    {
        try {
            $r = $capturador->ejecutar((string) $this->argument('muestra'), $this->option('longitud'), $this->option('latitud'),
                (string) $this->option('desde'), (string) $this->option('hasta'), (bool) $this->option('renovar'));
            unset($r['version']['informe']['meses']);
            $this->line(json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));

            return $r['version']['informe']['aptoParaAgregacionMensual'] ? self::SUCCESS : self::FAILURE;
        } catch (SeriePrecipitacionInvalida $e) {
            $this->line(json_encode(['error' => ['codigo' => $e->codigo, 'mensaje' => $e->getMessage()]], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            return self::INVALID;
        }
    }
}
