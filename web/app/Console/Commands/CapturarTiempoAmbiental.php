<?php

namespace App\Console\Commands;

use App\Models\PuntoAmbiental;
use App\Services\Datos\CapturarTiempo;
use Illuminate\Console\Command;

class CapturarTiempoAmbiental extends Command
{
    protected $signature = 'geosur:capturar-tiempo {punto} {--consultar-proveedor}';

    protected $description = 'Captura temperatura y humedad modeladas de un punto, solo bajo petición explícita.';

    public function handle(CapturarTiempo $capturar): int
    {
        if (! $this->option('consultar-proveedor')) {
            $this->error('Añade --consultar-proveedor para solicitar la captura.');

            return self::FAILURE;
        }
        $punto = PuntoAmbiental::where('codigo', $this->argument('punto'))->first();
        if (! $punto) {
            $this->error('No encontré ese punto.');

            return self::INVALID;
        }
        try {
            $resultado = $capturar->ejecutar($punto);
        } catch (\Throwable $e) {
            $this->error('No se guardó la captura. Revisa la habilitación del proveedor y su disponibilidad.');

            return self::FAILURE;
        }
        $this->info('Estimaciones guardadas: '.$resultado['nuevas'].'. Repetidas: '.$resultado['repetidas'].'.');

        return self::SUCCESS;
    }
}
