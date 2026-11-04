<?php

namespace App\Console\Commands;

use App\Services\Notificaciones\ProcesarOutboxAvisos;
use Illuminate\Console\Command;

final class ProcesarAvisos extends Command
{
    protected $signature = 'avisos:procesar {--procesar : Autorizar esta ejecución} {--limite=50 : Máximo de envíos (1 a 100)}';

    protected $description = 'Procesar un lote de la outbox de avisos con los canales habilitados';

    public function handle(ProcesarOutboxAvisos $outbox): int
    {
        if (! $this->option('procesar') || ! config('avisos.procesar')) {
            $this->warn('Procesamiento desactivado. Requiere AVISOS_PROCESAR=true y --procesar.');

            return self::FAILURE;
        }
        $limite = filter_var($this->option('limite'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
        if ($limite === false) {
            $this->error('El límite debe estar entre 1 y 100.');

            return self::INVALID;
        }
        $this->info('Registros procesados: '.$outbox->ejecutar($limite).'. Consulta los estados; procesado no significa recibido.');

        return self::SUCCESS;
    }
}
