<?php

namespace App\Console\Commands;

use App\Services\Api\ErrorConsulta;
use App\Services\Datos\ImportarCsv;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class ImportarLecturasAmbientales extends Command
{
    protected $signature = 'geosur:importar-lecturas {archivo}';

    protected $description = 'Importa hasta 1000 lecturas CSV de forma completa, sin sobrescribir el historial.';

    public function handle(ImportarCsv $importar): int
    {
        try {
            $resultado = $importar->ejecutar($this->argument('archivo'));
        } catch (ErrorConsulta|ValidationException $e) {
            $this->error('No se importó el archivo. Revisa sus columnas, valores, fechas y puntos.');

            return self::INVALID;
        }
        $this->info('Lecturas nuevas: '.$resultado['nuevas'].'. Repetidas: '.$resultado['repetidas'].'.');

        return self::SUCCESS;
    }
}
