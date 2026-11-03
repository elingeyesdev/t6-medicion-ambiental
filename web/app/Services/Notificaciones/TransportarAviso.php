<?php

namespace App\Services\Notificaciones;

use App\Models\EnvioAviso;
use App\Services\Datos\CatalogoAmbiental;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class TransportarAviso
{
    /** Nunca devuelve respuestas, URLs, tokens ni mensajes de excepción del proveedor. */
    public function ejecutar(EnvioAviso $envio, string $destino): array
    {
        $a = $envio->aviso->alerta;
        $metrica = app(CatalogoAmbiental::class)->metricas()[$a->metrica];
        $origen = $a->origen === 'simulado' ? 'Simulación' : 'Observación';
        $detalle = $metrica['nombre'].': '.$a->contenido['valor'].' '.$metrica['unidad'].' · '.$a->contenido['umbral']['nombre'];
        $lugar = $a->contenido['nombre'];
        $hora = $a->instante->setTimezone('America/La_Paz')->format('d/m/Y H:i');
        $texto = "GeoSur · $origen\n$lugar\n$detalle\n$hora · Hora de Bolivia\n".rtrim(config('app.url'), '/').'/avisos';

        try {
            if ($envio->canal === 'email') {
                $mailer = config('avisos.email.mailer');
                $correo = $mailer === 'log' ? CorreoLocal::mailer() : Mail::mailer($mailer);
                $enviado = $correo->raw($texto, fn ($m) => $m->to($destino)->subject('GeoSur · '.$origen.' ambiental'));

                return $enviado ? ['estado' => $mailer === 'log' ? 'registrado_local' : 'aceptado', 'error_codigo' => null] :
                    ['estado' => 'fallido', 'error_codigo' => 'CORREO_OMITIDO'];
            }
            $peticion = Http::acceptJson()->asJson()->connectTimeout(3)->timeout(10)->withoutRedirecting();
            if ($envio->canal === 'telegram') {
                $respuesta = $peticion->post('https://api.telegram.org/bot'.config('avisos.telegram.token').'/sendMessage',
                    ['chat_id' => $destino, 'text' => $texto]);
                $aceptado = $respuesta->successful() && $respuesta->json('ok') === true && is_int($respuesta->json('result.message_id'));
                $limitado = $respuesta->status() === 429 || $respuesta->json('error_code') === 429;
            } elseif ($envio->canal === 'whatsapp') {
                // Plantilla aprobada con cuatro parámetros posicionales: origen, lugar, detalle y fecha.
                $parametros = array_map(fn ($t) => ['type' => 'text', 'text' => preg_replace('/\s+/u', ' ', $t)], [$origen, $lugar, $detalle, $hora]);
                $respuesta = $peticion->withToken(config('avisos.whatsapp.token'))->post(
                    'https://graph.facebook.com/'.config('avisos.whatsapp.version').'/'.config('avisos.whatsapp.telefono_id').'/messages',
                    ['messaging_product' => 'whatsapp', 'to' => $destino, 'type' => 'template', 'template' => [
                        'name' => config('avisos.whatsapp.plantilla'), 'language' => ['code' => config('avisos.whatsapp.idioma')],
                        'components' => [['type' => 'body', 'parameters' => $parametros]],
                    ]]);
                $aceptado = $respuesta->successful() && is_string($respuesta->json('messages.0.id')) && $respuesta->json('messages.0.id') !== '';
                $limitado = $respuesta->status() === 429;
            } elseif ($envio->canal === 'push') {
                $token = app(AccesoFirebaseAvisos::class)->token();
                if (! $token) {
                    return ['estado' => 'fallido', 'error_codigo' => 'PUSH_AUTORIZACION'];
                }
                $respuesta = $peticion->withToken($token)->post('https://fcm.googleapis.com/v1/projects/'.config('avisos.push.proyecto').'/messages:send',
                    ['message' => ['token' => $destino, 'notification' => ['title' => 'GeoSur · '.$origen, 'body' => $lugar.' · '.$detalle],
                        'webpush' => ['headers' => ['TTL' => (string) max(0, 900 - (now()->timestamp - $a->instante->timestamp))], 'notification' => ['tag' => $envio->aviso_id]]]]);
                $aceptado = $respuesta->successful() && is_string($respuesta->json('name')) && str_starts_with($respuesta->json('name'), 'projects/'.config('avisos.push.proyecto').'/messages/');
                $limitado = $respuesta->status() === 429;
            } else {
                return ['estado' => 'fallido', 'error_codigo' => 'CANAL_INVALIDO'];
            }
            if ($aceptado) {
                return ['estado' => 'aceptado', 'error_codigo' => null];
            }
            if ($limitado) {
                $espera = $envio->canal === 'telegram' ? $respuesta->json('parameters.retry_after') : $respuesta->header('Retry-After');
                $segundos = filter_var($espera, FILTER_VALIDATE_INT);

                return ['estado' => 'reintento', 'error_codigo' => 'LIMITE_PROVEEDOR', 'espera' => max(60, min(86400, $segundos ?: 60))];
            }

            return $respuesta->serverError() || $respuesta->successful() ?
                ['estado' => 'incierto', 'error_codigo' => 'RESPUESTA_SIN_CONFIRMACION'] :
                ['estado' => 'fallido', 'error_codigo' => 'PROVEEDOR_RECHAZO'];
        } catch (ConnectionException) {
            // Una respuesta perdida puede corresponder a un mensaje ya aceptado. No repetirlo a ciegas.
            return ['estado' => 'incierto', 'error_codigo' => 'CONEXION_SIN_CONFIRMACION'];
        } catch (Throwable) {
            return ['estado' => 'incierto', 'error_codigo' => 'TRANSPORTE_SIN_CONFIRMACION'];
        }
    }
}
