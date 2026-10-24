<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Sequias\Experimental\CatalogoMuestrasSequias;
use App\Services\Sequias\SeriePrecipitacionInvalida;
use Illuminate\Console\Command;

final class ListarMuestrasSequias extends Command
{
    protected $signature = 'sequias:muestras {--provincia=} {--municipio=} {--muestra=} {--geojson : Exportar puntos candidatos GeoJSON}';

    protected $description = 'Listar y verificar muestras territoriales candidatas, sin consultar clima ni calcular SPI';

    public function handle(CatalogoMuestrasSequias $catalogo): int
    {
        try {
            $r = $catalogo->consultar($this->option('provincia'), $this->option('municipio'), $this->option('muestra'));
            $this->line(json_encode($this->option('geojson') ? $catalogo->geojson($r) : $r,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (SeriePrecipitacionInvalida $e) {
            $this->line(json_encode(['error' => ['codigo' => $e->codigo, 'mensaje' => $e->getMessage()]], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            return self::INVALID;
        }
    }
}
