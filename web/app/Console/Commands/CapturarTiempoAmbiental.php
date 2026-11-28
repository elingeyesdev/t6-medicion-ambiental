<?php

namespace App\Console\Commands;

use App\Models\PuntoAmbiental;
use App\Services\Datos\CapturarTiempo;
use Illuminate\Console\Command;

class CapturarTiempoAmbiental extends Command
{
    protected $signature = 'geosur:capturar-tiempo {punto?} {--todos : Capturar los puntos de referencia activos} {--consultar-proveedor}';

    protected $description = 'Actualiza temperatura y humedad modeladas de un punto o del catálogo de referencia.';

    public function handle(CapturarTiempo $capturar): int
    {
        if (! $this->option('consultar-proveedor')) {
            $this->error('Añade --consultar-proveedor para solicitar la captura.');

            return self::FAILURE;
        }
        if ($this->option('todos')) {
            if ($this->argument('punto')) {
                $this->error('Elige un punto o --todos.');

                return self::INVALID;
            }
            if (! config('datos.tiempo_habilitado')) {
                $this->error('Habilita GEOSUR_TIEMPO_HABILITADO para consultar el proveedor.');

                return self::FAILURE;
            }
            $fallos = 0;
            $cantidad = 0;
            foreach (PuntoAmbiental::where('activo', true)->where('tipo', 'referencia')->orderBy('codigo')->cursor() as $referencia) {
                $cantidad++;
                try {
                    $resultado = $capturar->ejecutar($referencia);
                    $this->info($referencia->codigo.': '.$resultado['nuevas'].' nuevas, '.$resultado['repetidas'].' repetidas.');
                } catch (\Throwable $e) {
                    $fallos++;
                    $this->error($referencia->codigo.': captura no disponible. Se conserva su historial.');
                }
                usleep(250000);
            }
            $this->info('Puntos consultados: '.$cantidad.'. Fallos: '.$fallos.'.');

            return $cantidad > 0 && $fallos === 0 ? self::SUCCESS : self::FAILURE;
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
