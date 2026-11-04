<?php

namespace App\Console\Commands;

use App\Services\Api\ErrorConsulta;
use App\Services\Datos\Mqtt\IngerirMensajeMqtt;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class ReproducirMqtt extends Command
{
    protected $signature = 'geosur:mqtt-reproducir {archivo} {--actor=} {--ejecutar}';

    protected $description = 'Reproduce mensajes locales como simulados, sin conectarse a un broker.';

    public function handle(IngerirMensajeMqtt $ingestion): int
    {
        if (! $this->option('ejecutar') || ! ctype_digit((string) $this->option('actor')) || ! app()->environment(['local', 'testing'])) {
            $this->error('La reproducción requiere entorno local, --ejecutar y --actor.');

            return self::INVALID;
        }
        try {
            $r = $ingestion->reproducir($this->argument('archivo'), (int) $this->option('actor'));
        } catch (ErrorConsulta|ValidationException|AuthorizationException $e) {
            $this->error('No se reprodujo el lote. Revisa los temas, mensajes y permisos.');

            return self::INVALID;
        }
        $this->info('Ejemplos simulados. Nuevas: '.$r['nuevas'].'. Repetidas: '.$r['repetidas'].'.');

        return self::SUCCESS;
    }
}
