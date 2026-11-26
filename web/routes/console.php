<?php

use App\Services\Api\ErrorConsulta;
use App\Services\PortalIncendios\ImportarAvisos;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('capas:actualizar --consultar-noaa --programada')
    ->hourly()->withoutOverlapping(10)->when(fn () => config('capas.actualizacion.habilitada'));

Schedule::command('geosur:capturar-tiempo --todos --consultar-proveedor')
    ->hourly()->withoutOverlapping(45)
    ->when(fn () => config('datos.tiempo_habilitado') && config('datos.tiempo_programado'));

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
