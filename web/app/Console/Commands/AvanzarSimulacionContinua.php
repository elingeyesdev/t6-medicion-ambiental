<?php

namespace App\Console\Commands;

use App\Models\SimulacionContinua;
use App\Services\Simulaciones\ContinuarSimulacion;
use Illuminate\Console\Command;

class AvanzarSimulacionContinua extends Command
{
    protected $signature = 'simulaciones:avanzar';

    protected $description = 'Actualiza un bloque del recorrido global si ya fue iniciado por su propietario';

    public function handle(ContinuarSimulacion $motor): int
    {
        $s = SimulacionContinua::find(1);
        if (! $s || $s->estado !== 'activa') {
            $this->info('No hay una simulación global en marcha.');

            return self::SUCCESS;
        }
        $estado = $motor->estado($s->usuario_id);
        $this->info('Paso '.$estado['paso'].' · Pendientes '.$estado['pendientes']);

        return self::SUCCESS;
    }
}
