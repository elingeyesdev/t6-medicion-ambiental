<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('capas:actualizar --consultar-noaa --programada')
    ->hourly()->withoutOverlapping(10)->when(fn () => config('capas.actualizacion.habilitada'));
