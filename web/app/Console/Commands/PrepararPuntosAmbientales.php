<?php

namespace App\Console\Commands;

use App\Models\PuntoAmbiental;
use App\Services\Datos\CatalogoAmbiental;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PrepararPuntosAmbientales extends Command
{
    protected $signature = 'geosur:preparar-puntos';

    protected $description = 'Recupera puntos de referencia de EnviroSense sin inventar mediciones.';

    public function handle(CatalogoAmbiental $catalogo): int
    {
        $cantidad = DB::transaction(function () use ($catalogo): int {
            $nuevos = 0;
            foreach ($catalogo->leer()['puntos'] as $p) {
                $punto = PuntoAmbiental::firstOrCreate(['codigo' => $p['codigo']], array_intersect_key($p, array_flip(['nombre', 'pais', 'latitud', 'longitud'])) + ['tipo' => 'referencia']);
                if ($punto->wasRecentlyCreated) {
                    $nuevos++;
                }
            }

            return $nuevos;
        });
        $this->info("Puntos nuevos: $cantidad. Se conservaron los existentes. No se crearon lecturas.");

        return self::SUCCESS;
    }
}
