<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use Throwable;

class ActualizarCapasGfs extends Command
{
    protected $signature = 'capas:actualizar {--consultar-noaa} {--programada} {--energia-superficie}';

    protected $description = 'Obtiene y publica el último ciclo GFS con margen de disponibilidad.';

    public function handle(): int
    {
        if ($this->option('programada') && ! config('capas.actualizacion.habilitada')) {
            $this->info('La actualización programada de capas está apagada.');

            return self::SUCCESS;
        }
        if (! $this->option('consultar-noaa')) {
            $this->error('Indica --consultar-noaa para obtener los recortes públicos.');

            return self::INVALID;
        }
        $python = config('capas.actualizacion.python');
        if (! is_string($python) || ! is_file($python) || ! is_executable($python)) {
            $this->error('Configura el ejecutable Python del entorno raster.');

            return self::INVALID;
        }
        $energia = $this->option('energia-superficie');
        $trabajo = config('capas.actualizacion.trabajo').($energia ? '-energia' : '');
        $argumentos = [$python, base_path('../herramientas/datos/raster/actualizar.py'), '--trabajo', $trabajo,
            '--php', PHP_BINARY, '--artisan', base_path('artisan'), '--consultar-noaa'];
        if ($energia) {
            $argumentos[] = '--energia-superficie';
        }
        if ($ini = php_ini_loaded_file()) {
            array_push($argumentos, '--php-ini', $ini);
        }
        try {
            $proceso = new Process($argumentos, base_path(), timeout: 180);
            $proceso->run(fn ($tipo, $texto) => $this->output->write($texto));

            return $proceso->isSuccessful() ? self::SUCCESS : self::FAILURE;
        } catch (Throwable) {
            $this->error('La actualización no terminó. Se conserva el catálogo publicado; revisa el estado local.');

            return self::FAILURE;
        }
    }
}
