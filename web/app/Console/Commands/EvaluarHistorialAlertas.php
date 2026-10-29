<?php

namespace App\Console\Commands;

use App\Models\LecturaAmbiental;
use App\Services\Alertas\EvaluarAlertas;
use App\Services\Api\ErrorConsulta;
use App\Services\Api\ParametrosConsulta;
use Illuminate\Console\Command;

class EvaluarHistorialAlertas extends Command
{
    protected $signature = 'alertas:evaluar {--desde=} {--hasta=}';

    protected $description = 'Evalúa hasta diez mil lecturas locales, sin enviar avisos externos';

    public function handle(EvaluarAlertas $motor): int
    {
        try {
            $hasta = $this->option('hasta') ? ParametrosConsulta::instante($this->option('hasta')) : now('UTC')->toDateTimeImmutable();
            $desde = $this->option('desde') ? ParametrosConsulta::instante($this->option('desde')) : $hasta->modify('-7 days');
            if ($desde >= $hasta || $hasta->getTimestamp() > now()->timestamp) {
                throw new ErrorConsulta('FILTROS_INVALIDOS', 'Elige un período pasado, desde incluido y hasta excluido.');
            }
            $filas = LecturaAmbiental::with('punto')->where('instante', '>=', $desde->format('Y-m-d H:i:s'))->where('instante', '<', $hasta->format('Y-m-d H:i:s'))
                ->orderBy('instante')->orderBy('id')->limit(10001)->get();
            if ($filas->count() > 10000) {
                $this->error('Hay más de diez mil lecturas. Reduce el período; no se evaluó parcialmente.');

                return self::FAILURE;
            }
            $nuevas = $motor->ejecutar($filas->all());
            $this->info('Lecturas revisadas: '.$filas->count().'. Alertas nuevas: '.$nuevas.'.');

            return self::SUCCESS;
        } catch (ErrorConsulta $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
