<?php

namespace App\Console\Commands;

use App\Services\Datos\CatalogoAmbiental;
use App\Services\Datos\RegistrarLecturas;
use Illuminate\Console\Command;

class DatosAmbientalesDemo extends Command
{
    protected $signature = 'geosur:datos-demo {--confirmar}';

    protected $description = 'Prepara 24 horas simuladas para tres ciudades en local; conserva los datos existentes.';

    public function handle(CatalogoAmbiental $catalogo, RegistrarLecturas $registrar): int
    {
        if (! app()->environment('local', 'testing') || ! $this->option('confirmar')) {
            $this->error('Disponible solo en local/testing con --confirmar.');

            return self::FAILURE;
        }
        $this->call('geosur:preparar-puntos');
        $filas = [];
        foreach (array_values(array_filter($catalogo->leer()['puntos'], fn ($p) => in_array($p['codigo'], ['santacruz', 'lapaz', 'buenos_aires'], true))) as $indice => $punto) {
            for ($i = 24; $i >= 1; $i--) {
                $fecha = now('UTC')->startOfHour()->subHours($i);
                foreach ($punto['rangosSimulacion'] as $metrica => [$minimo, $maximo]) {
                    $filas[] = ['punto' => $punto['codigo'], 'metrica' => $metrica, 'valor' => round($minimo + (0.5 + 0.3 * sin(($fecha->hour + $indice) * M_PI / 12)) * ($maximo - $minimo), 1),
                        'instante' => $fecha->toIso8601ZuluString(), 'origen' => 'simulado', 'fuente' => 'demo-local'];
                }
            }
        }
        $resultado = $registrar->ejecutar($filas);
        $this->info('Demostración simulada. Lecturas nuevas: '.$resultado['nuevas'].'. Repetidas: '.$resultado['repetidas'].'.');

        return self::SUCCESS;
    }
}
