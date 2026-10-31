<?php

namespace App\Console\Commands;

use App\Services\Api\ErrorConsulta;
use App\Services\Datos\AutorizarIngestion;
use App\Services\Datos\ImportarCsv;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class ImportarLecturasAmbientales extends Command
{
    protected $signature = 'geosur:importar-lecturas {archivo} {--actor=}';

    protected $description = 'Importa hasta 1000 lecturas CSV de forma completa, sin sobrescribir el historial.';

    public function handle(ImportarCsv $importar, AutorizarIngestion $autorizar): int
    {
        if (! ctype_digit((string) $this->option('actor'))) {
            $this->error('Indica --actor con una cuenta autorizada para cargar datos.');

            return self::INVALID;
        }
        try {
            $actor = $autorizar->ejecutar((int) $this->option('actor'));
            $resultado = $importar->ejecutar($this->argument('archivo'), $actor->id);
        } catch (ErrorConsulta|ValidationException|AuthorizationException $e) {
            $this->error('No se importó el archivo. Revisa sus columnas, valores, fechas y puntos.');

            return self::INVALID;
        }
        $this->info('Lecturas nuevas: '.$resultado['nuevas'].'. Repetidas: '.$resultado['repetidas'].'.');

        return self::SUCCESS;
    }
}
