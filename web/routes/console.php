<?php

use App\Services\Api\ErrorConsulta;
use App\Services\PortalIncendios\ImportarAvisos;
use App\Services\Seguimiento\EvaluarZona;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('capas:actualizar --consultar-noaa --programada')
    ->hourly()->withoutOverlapping(10)->when(fn () => config('capas.actualizacion.habilitada'));

Artisan::command('portal-incendios:importar', function (ImportarAvisos $importador): int {
    try {
        $this->info($importador->ejecutar().' avisos nuevos publicados.');
    } catch (ErrorConsulta $error) {
        $this->error($error->getMessage());

        return 1;
    }

    return 0;
})->purpose('Consultar detecciones reales recientes de NASA FIRMS y actualizar el portal de demostración');

Schedule::command('portal-incendios:importar')
    ->everyThirtyMinutes()->withoutOverlapping(10)
    ->when(fn () => config('portal_incendios.importacion_programada'));

Artisan::command('seguimiento:vigilar', function (EvaluarZona $evaluador): int {
    $errores = 0;
    foreach (DB::table('zonas_seguimiento')->lazyById() as $zona) {
        try {
            $resultado = $evaluador->consultar($zona, null);
            $evaluador->guardarVigilancia($zona, $resultado);
        } catch (ErrorConsulta) {
            $errores++;
        }
    }

    if ($errores > 0) {
        $this->warn('Zonas que no pudieron actualizarse: '.$errores);
    }

    return $errores > 0 ? 1 : 0;
})->purpose('Actualizar las alarmas de focos en las zonas guardadas');

Schedule::command('seguimiento:vigilar')->everyTenMinutes()->withoutOverlapping(10);
