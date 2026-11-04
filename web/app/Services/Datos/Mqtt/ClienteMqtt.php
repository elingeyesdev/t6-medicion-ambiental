<?php

namespace App\Services\Datos\Mqtt;

use App\Services\Api\ErrorConsulta;
use Closure;

/** Receptor MQTT 3.1.1 acotado a temas exactos y QoS 0/1. No publica mensajes. */
final class ClienteMqtt
{
    private $socket = null;

    private float $limite;

    private int $esperaLectura;

    public function recibir(array $config, array $temas, Closure $guardar, int $maximo, int $segundos): int
    {
        $this->limite = microtime(true) + $segundos;
        $this->esperaLectura = $config['espera'];
        $ssl = ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false];
        if ($config['ca'] !== '') {
            $ssl['cafile'] = $config['ca'];
        }
        $this->socket = @stream_socket_client($config['direccion'], $codigo, $error, $config['espera'], STREAM_CLIENT_CONNECT, stream_context_create(['ssl' => $ssl]));
        if ($this->socket === false) {
            throw new ErrorConsulta('MQTT_NO_DISPONIBLE', 'No se pudo conectar al broker configurado.', 503);
        }
        stream_set_timeout($this->socket, $config['espera']);
        $recibidos = 0;
        try {
            // Sesión persistente + id estable: PUBACK se envía solo después del commit.
            $flags = ($config['usuario'] !== '' ? 128 : 0) | ($config['clave'] !== '' ? 64 : 0);
            $cuerpo = $this->cadena('MQTT').chr(4).chr($flags).pack('n', 30).$this->cadena($config['cliente']);
            if ($config['usuario'] !== '') {
                $cuerpo .= $this->cadena($config['usuario']);
            }
            if ($config['clave'] !== '') {
                $cuerpo .= $this->cadena($config['clave']);
            }
            $this->enviar(0x10, $cuerpo);
            [$tipo, $respuesta] = $this->paquete();
            if ($tipo !== 0x20 || strlen($respuesta) !== 2 || ! in_array(ord($respuesta[0]), [0, 1], true) || ord($respuesta[1]) !== 0) {
                throw new ErrorConsulta('MQTT_CONEXION_RECHAZADA', 'El broker rechazó la conexión MQTT.', 503);
            }
            $suscripcion = pack('n', 1);
            foreach ($temas as $tema) {
                $suscripcion .= $this->cadena($tema).chr(1);
            }
            $this->enviar(0x82, $suscripcion);
            // Una sesión persistente puede entregar PUBLISH antes de SUBACK.
            $suscrito = false;
            $ultimoPing = microtime(true);
            $pingPendiente = null;
            while (microtime(true) < $this->limite && ($maximo === 0 || $recibidos < $maximo || ! $suscrito)) {
                $lectura = [$this->socket];
                $escritura = $excepcion = [];
                $listos = @stream_select($lectura, $escritura, $excepcion, 0, 200000);
                if ($listos === false) {
                    $this->fallo();
                }
                if ($listos > 0) {
                    [$tipo, $cuerpo] = $this->paquete();
                    if ($tipo === 0x90 && ! $suscrito) {
                        if (strlen($cuerpo) !== count($temas) + 2 || substr($cuerpo, 0, 2) !== pack('n', 1)) {
                            $this->fallo();
                        }
                        foreach (str_split(substr($cuerpo, 2)) as $qos) {
                            if (! in_array(ord($qos), [0, 1], true)) {
                                throw new ErrorConsulta('MQTT_SUSCRIPCION_RECHAZADA', 'El broker rechazó un tema configurado.', 503);
                            }
                        }
                        $suscrito = true;
                    } elseif (($tipo >> 4) === 3) {
                        $qos = ($tipo >> 1) & 3;
                        if ($qos > 1 || ($qos === 0 && ($tipo & 8)) || strlen($cuerpo) < 2) {
                            $this->fallo();
                        }
                        $longitud = unpack('n', substr($cuerpo, 0, 2))[1];
                        $tema = substr($cuerpo, 2, $longitud);
                        $inicio = 2 + $longitud;
                        if ($longitud === 0 || strlen($cuerpo) < $inicio + ($qos ? 2 : 0) || ! in_array($tema, $temas, true)) {
                            $this->fallo();
                        }
                        $id = $qos ? substr($cuerpo, $inicio, 2) : '';
                        if ($qos && $id === "\0\0") {
                            $this->fallo();
                        }
                        $guardar($tema, substr($cuerpo, $inicio + ($qos ? 2 : 0)));
                        if ($qos) {
                            $this->enviar(0x40, $id);
                        }
                        $recibidos++;
                    } elseif ($tipo === 0xD0 && $cuerpo === '' && $pingPendiente !== null) {
                        $pingPendiente = null;
                    } else {
                        $this->fallo();
                    }
                }
                if ($pingPendiente !== null && microtime(true) - $pingPendiente > $config['espera']) {
                    $this->fallo();
                }
                if (microtime(true) - $ultimoPing >= 10 && $pingPendiente === null) {
                    $this->enviar(0xC0, '');
                    $ultimoPing = $pingPendiente = microtime(true);
                }
            }
            if (! $suscrito) {
                throw new ErrorConsulta('MQTT_SIN_CONFIRMACION', 'El broker no confirmó la suscripción.', 503);
            }
            $this->enviar(0xE0, '');

            return $recibidos;
        } finally {
            fclose($this->socket);
            $this->socket = null;
        }
    }

    private function cadena(string $texto): string
    {
        return pack('n', strlen($texto)).$texto;
    }

    private function enviar(int $tipo, string $cuerpo): void
    {
        $longitud = strlen($cuerpo);
        $cabecera = chr($tipo);
        do {
            $byte = $longitud % 128;
            $longitud = intdiv($longitud, 128);
            $cabecera .= chr($byte | ($longitud > 0 ? 128 : 0));
        } while ($longitud > 0);
        $paquete = $cabecera.$cuerpo;
        while ($paquete !== '') {
            $escritos = @fwrite($this->socket, $paquete);
            if ($escritos === false || $escritos === 0) {
                $this->fallo();
            }
            $paquete = substr($paquete, $escritos);
        }
    }

    private function paquete(): array
    {
        $tipo = ord($this->leer(1));
        $longitud = 0;
        $multiplicador = 1;
        for ($i = 0; ; $i++) {
            $byte = ord($this->leer(1));
            $longitud += ($byte & 127) * $multiplicador;
            if ($longitud > 8192 || $i > 2) {
                $this->fallo();
            }
            if (($byte & 128) === 0) {
                break;
            }
            $multiplicador *= 128;
        }

        return [$tipo, $this->leer($longitud)];
    }

    private function leer(int $longitud): string
    {
        $resultado = '';
        while (strlen($resultado) < $longitud) {
            $restante = min((float) $this->esperaLectura, $this->limite - microtime(true));
            if ($restante <= 0) {
                $this->fallo();
            }
            stream_set_timeout($this->socket, (int) $restante, (int) (($restante - (int) $restante) * 1000000));
            $parte = @fread($this->socket, $longitud - strlen($resultado));
            if ($parte === false || $parte === '') {
                $this->fallo();
            }
            $resultado .= $parte;
        }

        return $resultado;
    }

    private function fallo(): never
    {
        throw new ErrorConsulta('MQTT_TRANSPORTE_INVALIDO', 'La recepción MQTT se interrumpió o no cumple el contrato.', 503);
    }
}
