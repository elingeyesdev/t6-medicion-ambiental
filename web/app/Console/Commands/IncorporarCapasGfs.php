<?php

namespace App\Console\Commands;

use App\Services\Mapas\Radar\IncorporarGfs;
use Illuminate\Console\Command;
use Throwable;

class IncorporarCapasGfs extends Command
{
    protected $signature = 'capas:incorporar {lote}';

    protected $description = 'Incorpora un lote GFS completo conservando los cortes anteriores.';

    public function handle(IncorporarGfs $incorporar): int
    {
        try {
            $this->line(json_encode($incorporar->ejecutar($this->argument('lote')), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        } catch (Throwable $error) {
            $this->line(json_encode(['estado' => 'fallido', 'mensaje' => $error->getMessage()], JSON_UNESCAPED_UNICODE));

            return self::FAILURE;
        }
    }
}
