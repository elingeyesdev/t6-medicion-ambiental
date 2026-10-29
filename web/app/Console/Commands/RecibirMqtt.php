<?php

namespace App\Console\Commands;

use App\Services\Api\ErrorConsulta;
use App\Services\Datos\AutorizarIngestion;
use App\Services\Datos\Mqtt\ClienteMqtt;
use App\Services\Datos\Mqtt\ConfiguracionMqtt;
use App\Services\Datos\Mqtt\IngerirMensajeMqtt;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class RecibirMqtt extends Command
{
    protected $signature = 'geosur:mqtt-recibir {--actor=} {--conectar} {--max-mensajes=1} {--segundos=30}';

    protected $description = 'Recibe MQTT explícitamente, con temas permitidos y duración acotada.';

    public function handle(ConfiguracionMqtt $configuracion, ClienteMqtt $cliente, IngerirMensajeMqtt $ingestion, AutorizarIngestion $autorizar): int
    {
        if (! $this->option('conectar') || ! ctype_digit((string) $this->option('actor')) ||
            ! ctype_digit((string) $this->option('max-mensajes')) || (int) $this->option('max-mensajes') > 10000 ||
            ! ctype_digit((string) $this->option('segundos')) || (int) $this->option('segundos') < 1 || (int) $this->option('segundos') > 3600) {
            $this->error('Indica --conectar, --actor, hasta 10000 mensajes y de 1 a 3600 segundos.');

            return self::INVALID;
        }
        $actor = (int) $this->option('actor');
        $cerrojo = null;
        $nuevas = $repetidas = 0;
        try {
            $autorizar->ejecutar($actor);
            $config = $configuracion->conexion();
            $temas = array_keys($configuracion->temas());
            // Evita que dos receptores locales se desconecten mutuamente con el mismo clientId.
            $ruta = storage_path('framework/cache/mqtt-'.hash('sha256', $config['direccion'].'/'.$config['cliente']).'.lock');
            $cerrojo = @fopen($ruta, 'c');
            if (! $cerrojo || ! flock($cerrojo, LOCK_EX | LOCK_NB)) {
                throw new ErrorConsulta('MQTT_OCUPADO', 'Ya hay una recepción local con ese cliente.', 409);
            }
            $recibidos = $cliente->recibir($config, $temas, function (string $tema, string $contenido) use ($actor, $ingestion, &$nuevas, &$repetidas): void {
                $r = $ingestion->ejecutar($tema, $contenido, $actor);
                $nuevas += $r['nuevas'];
                $repetidas += $r['repetidas'];
            }, (int) $this->option('max-mensajes'), (int) $this->option('segundos'));
        } catch (ErrorConsulta|ValidationException|AuthorizationException $e) {
            $this->error(($e instanceof ValidationException ? 'Mensaje o configuración inválidos.' : $e->getMessage()).' Recepción detenida. Nuevas: '.$nuevas.'. Repetidas: '.$repetidas.'.');

            return self::INVALID;
        } finally {
            if (is_resource($cerrojo)) {
                flock($cerrojo, LOCK_UN);
                fclose($cerrojo);
            }
        }
        $this->info('Mensajes: '.$recibidos.'. Nuevas: '.$nuevas.'. Repetidas: '.$repetidas.'.');

        return self::SUCCESS;
    }
}
