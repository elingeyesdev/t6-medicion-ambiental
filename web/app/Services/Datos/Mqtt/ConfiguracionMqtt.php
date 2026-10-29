<?php

namespace App\Services\Datos\Mqtt;

use App\Services\Api\ErrorConsulta;
use App\Services\Datos\ArchivoDatos;
use App\Services\Datos\CatalogoAmbiental;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class ConfiguracionMqtt
{
    public function temas(): array
    {
        $temas = config('datos.mqtt.temas');
        if (is_string($temas)) {
            $temas = ArchivoDatos::json($temas);
        }
        if (! is_array($temas) || count($temas) < 1 || count($temas) > 50) {
            throw new ErrorConsulta('MQTT_CONFIGURACION', 'Configura entre 1 y 50 temas de entrada.');
        }
        $metricas = app(CatalogoAmbiental::class)->metricas();
        foreach ($temas as $tema => $destino) {
            // Temas exactos: no comodines, URLs, conversiones ni destinos elegidos por el mensaje.
            if (! is_string($tema) || ! preg_match('~^[a-zA-Z0-9/_-]{1,200}$~D', $tema)) {
                throw new ErrorConsulta('MQTT_CONFIGURACION', 'Usa temas exactos sin comodines.');
            }
            Validator::make(['destino' => $destino], [
                'destino' => ['required', 'array:punto,metrica,unidad,origen,fuente'],
                'destino.punto' => ['required', 'string', 'max:60'],
                'destino.metrica' => ['required', Rule::in(array_keys($metricas))],
                'destino.unidad' => ['required', 'string'],
                'destino.origen' => ['required', Rule::in(array_keys(CatalogoAmbiental::ORIGENES))],
                'destino.fuente' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9][a-z0-9._-]*$/D'],
            ])->validate();
            if ($destino['unidad'] !== $metricas[$destino['metrica']]['unidad']) {
                throw new ErrorConsulta('MQTT_CONFIGURACION', 'La unidad configurada no corresponde a la variable.');
            }
        }

        return $temas;
    }

    public function conexion(): array
    {
        if (! config('datos.mqtt.habilitado')) {
            throw new ErrorConsulta('MQTT_DESHABILITADO', 'La recepción MQTT está deshabilitada.', 503);
        }
        $c = config('datos.mqtt');
        $url = parse_url($c['url']);
        if (! is_array($url) || array_diff(array_keys($url), ['scheme', 'host', 'port']) ||
            ! isset($url['host'], $url['port'], $url['scheme']) || $url['port'] < 1 || $url['port'] > 65535 ||
            ! in_array($url['scheme'], ['mqtt', 'mqtts'], true) || ! preg_match('/^[a-zA-Z0-9.\[\]:-]+$/D', $url['host'])) {
            throw new ErrorConsulta('MQTT_CONFIGURACION', 'Configura dirección y puerto del broker, sin credenciales en la URL.');
        }
        if ($url['scheme'] === 'mqtt' && (! app()->environment(['local', 'testing']) || ! in_array($url['host'], ['127.0.0.1', '[::1]'], true))) {
            throw new ErrorConsulta('MQTT_CONFIGURACION', 'Las conexiones sin TLS solo se admiten en pruebas locales de loopback.');
        }
        if (! preg_match('/^[a-zA-Z0-9_-]{1,23}$/D', $c['cliente']) ||
            strlen($c['usuario']) > 1024 || strlen($c['clave']) > 1024 ||
            str_contains($c['usuario'], "\0") || ! mb_check_encoding($c['usuario'], 'UTF-8') ||
            ($c['clave'] !== '' && $c['usuario'] === '') || $c['espera'] < 1 || $c['espera'] > 30 ||
            ($c['ca'] !== '' && ! is_readable($c['ca']))) {
            throw new ErrorConsulta('MQTT_CONFIGURACION', 'Revisa cliente, credenciales, certificado y tiempo de espera MQTT.');
        }

        return $c + ['direccion' => ($url['scheme'] === 'mqtts' ? 'tls' : 'tcp').'://'.$url['host'].':'.$url['port']];
    }
}
