<?php

namespace App\Console\Commands;

use App\Services\Api\ErrorConsulta;
use App\Services\Datos\ImportarHistorial;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class ImportarHistorialAmbiental extends Command
{
    protected $signature = 'geosur:importar-historial {archivo} {--actor=} {--confirmar-saneado} {--comprobar}';

    protected $description = 'Comprueba o importa un historial JSON saneado, completo y repetible.';

    public function handle(ImportarHistorial $importar): int
    {
        if (! $this->option('confirmar-saneado') || ! ctype_digit((string) $this->option('actor'))) {
            $this->error('Indica --actor y --confirmar-saneado para el archivo revisado.');

            return self::INVALID;
        }
        try {
            $resultado = $importar->ejecutar($this->argument('archivo'), (int) $this->option('actor'), (bool) $this->option('comprobar'));
        } catch (ErrorConsulta|ValidationException|AuthorizationException $e) {
            $this->error($e instanceof ValidationException ? 'Revisa las columnas, unidades y valores del historial.' : $e->getMessage());

            return self::INVALID;
        }
        $this->info(($this->option('comprobar') ? 'Comprobado sin guardar. ' : 'Importación completa. ').'Nuevas: '.$resultado['nuevas'].'. Repetidas: '.$resultado['repetidas'].'.');

        return self::SUCCESS;
    }
}
