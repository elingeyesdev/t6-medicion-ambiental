<?php

namespace App\Services\Datos\Mqtt;

use App\Services\Api\ErrorConsulta;
use App\Services\Datos\ArchivoDatos;
use App\Services\Datos\AutorizarIngestion;
use App\Services\Datos\RegistrarLecturas;
use Illuminate\Support\Facades\Validator;

final class IngerirMensajeMqtt
{
    public function __construct(private ConfiguracionMqtt $configuracion, private RegistrarLecturas $registrar, private AutorizarIngestion $autorizar) {}

    public function lectura(string $tema, string $contenido, bool $ejemplo = false): array
    {
        $destino = $this->configuracion->temas()[$tema] ?? null;
        if (! $destino) {
            throw new ErrorConsulta('MQTT_TEMA_NO_PERMITIDO', 'El tema recibido no está autorizado.');
        }
        if (strlen($contenido) > 4096) {
            throw new ErrorConsulta('MQTT_MENSAJE_INVALIDO', 'El mensaje supera el tamaño permitido.');
        }
        $datos = ArchivoDatos::json($contenido);
        Validator::make(['mensaje' => $datos], [
            'mensaje' => ['required', 'array:valor,instante'],
            'mensaje.valor' => ['required', 'numeric'],
            'mensaje.instante' => ['required', 'string'],
        ])->validate();

        return ['punto' => $destino['punto'], 'metrica' => $destino['metrica'], 'valor' => $datos['valor'], 'instante' => $datos['instante'],
            'origen' => $ejemplo ? 'simulado' : $destino['origen'], 'fuente' => $ejemplo ? 'mqtt-ejemplo-local' : $destino['fuente']];
    }

    public function ejecutar(string $tema, string $contenido, int $actor): array
    {
        $this->autorizar->ejecutar($actor);

        return $this->registrar->ejecutar([$this->lectura($tema, $contenido)], $actor);
    }

    public function reproducir(string $archivo, int $actor): array
    {
        $this->autorizar->ejecutar($actor);
        $datos = ArchivoDatos::json(ArchivoDatos::leer($archivo));
        Validator::make(['ejemplo' => $datos], [
            'ejemplo' => ['required', 'array:formato,mensajes'],
            'ejemplo.formato' => ['required', 'in:geosur.mqtt.v1'],
            'ejemplo.mensajes' => ['required', 'array', 'list', 'min:1', 'max:1000'],
            'ejemplo.mensajes.*' => ['required', 'array:tema,contenido'],
            'ejemplo.mensajes.*.tema' => ['required', 'string'],
            'ejemplo.mensajes.*.contenido' => ['required', 'array:valor,instante'],
        ])->validate();
        $filas = [];
        foreach ($datos['mensajes'] as $mensaje) {
            $filas[] = $this->lectura($mensaje['tema'], json_encode($mensaje['contenido'], JSON_THROW_ON_ERROR), true);
        }

        return $this->registrar->ejecutar($filas, $actor);
    }
}
