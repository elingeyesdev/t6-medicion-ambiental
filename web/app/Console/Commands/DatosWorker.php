<?php

namespace App\Console\Commands;

use App\Services\Api\ErrorConsulta;
use App\Services\Datos\ProcesarBandejaDatos;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class DatosWorker extends Command
{
    protected $signature = 'geosur:datos-worker {directorio} {--actor=} {--ejecutar} {--daemon} {--intervalo=5} {--ciclos=0}';

    protected $description = 'Procesa archivos saneados locales; solo arranca con --ejecutar.';

    public function handle(ProcesarBandejaDatos $worker): int
    {
        if (! $this->option('ejecutar') || ! ctype_digit((string) $this->option('actor')) ||
            ! ctype_digit((string) $this->option('ciclos')) || ! ctype_digit((string) $this->option('intervalo')) ||
            (int) $this->option('intervalo') < 1 || (int) $this->option('intervalo') > 60 ||
            ! app()->environment(['local', 'testing'])) {
            $this->error('La tarea requiere entorno local, --ejecutar, --actor e intervalo de 1 a 60 segundos.');

            return self::INVALID;
        }
        $ciclo = 0;
        do {
            try {
                $r = $worker->ejecutar($this->argument('directorio'), (int) $this->option('actor'));
            } catch (ErrorConsulta|ValidationException|AuthorizationException $e) {
                $this->error('Tarea detenida. Revisa el archivo pendiente y los permisos de la cuenta. Los archivos anteriores pueden estar cargados.');

                return self::INVALID;
            }
            $this->info('Archivos: '.$r['archivos'].'. Nuevas: '.$r['nuevas'].'. Repetidas: '.$r['repetidas'].'.');
            $ciclo++;
            if (! $this->option('daemon') || ((int) $this->option('ciclos') > 0 && $ciclo >= (int) $this->option('ciclos'))) {
                break;
            }
            sleep((int) $this->option('intervalo'));
        } while (true);

        return self::SUCCESS;
    }
}
