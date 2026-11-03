<?php

namespace App\Services\Notificaciones;

use App\Models\DestinoPushAviso;
use App\Models\SuscripcionCanalAviso;
use App\Models\User;

final class CanalesAvisos
{
    public const CUENTA = ['email', 'telegram', 'whatsapp'];

    public const NOMBRES = ['email' => 'Correo', 'telegram' => 'Telegram', 'whatsapp' => 'WhatsApp', 'push' => 'Navegador'];

    public function disponible(string $canal): bool
    {
        if (! config('avisos.'.$canal.'.habilitado')) {
            return false;
        }

        return match ($canal) {
            'push' => preg_match('/^[a-z0-9-]+$/D', (string) config('avisos.push.proyecto')) === 1 &&
                filter_var(config('avisos.push.cliente_email'), FILTER_VALIDATE_EMAIL) && filled(config('avisos.push.clave_privada')) &&
                filled(config('avisos.push.api_key_publica')) && filled(config('avisos.push.app_id')) &&
                filled(config('avisos.push.sender_id')) && filled(config('avisos.push.vapid_publica')),
            // Solo transportes cuyo resultado se puede distinguir sin fallback silencioso a log.
            'email' => in_array(config('avisos.email.mailer'), ['log', 'smtp'], true),
            'telegram' => is_string(config('avisos.telegram.token')) && preg_match('/^\d+:[A-Za-z0-9_-]+$/D', config('avisos.telegram.token')) === 1,
            'whatsapp' => filled(config('avisos.whatsapp.token')) &&
                preg_match('/^\d+$/D', (string) config('avisos.whatsapp.telefono_id')) === 1 &&
                preg_match('/^v\d+\.\d+$/D', (string) config('avisos.whatsapp.version')) === 1 &&
                preg_match('/^[a-z0-9_]+$/D', (string) config('avisos.whatsapp.plantilla')) === 1,
            default => false,
        };
    }

    public function destino(User $usuario, string $canal): ?string
    {
        if ($canal === 'push') {
            return DestinoPushAviso::where('usuario_id', $usuario->id)->first()?->token;
        }
        $pref = $usuario->preferencias;
        if (! $pref || ! $pref->{'notif_'.$canal}) {
            return null;
        }
        $valor = $canal === 'email' ? $usuario->email : $pref->{$canal.'_destino'};
        if (! is_string($valor)) {
            return null;
        }
        $valor = trim($valor);

        return match ($canal) {
            'email' => filter_var($valor, FILTER_VALIDATE_EMAIL) ? $valor : null,
            // Destinos privados. No se permite suscribir grupos ni canales de terceros.
            'telegram' => preg_match('/^[1-9]\d{3,19}$/D', $valor) ? $valor : null,
            'whatsapp' => preg_match('/^\+?[1-9]\d{7,14}$/D', $valor) ? ltrim($valor, '+') : null,
            default => null,
        };
    }

    public function configuracionPushPublica(): array
    {
        return ['apiKey' => config('avisos.push.api_key_publica'), 'projectId' => config('avisos.push.proyecto'),
            'appId' => config('avisos.push.app_id'), 'messagingSenderId' => config('avisos.push.sender_id')];
    }

    public function huella(string $destino): string
    {
        return hash_hmac('sha256', $destino, (string) config('app.key'));
    }

    public function autorizado(User $usuario, string $canal): ?string
    {
        if (! $usuario->activo || ! $this->disponible($canal) || ! ($destino = $this->destino($usuario, $canal))) {
            return null;
        }
        $suscripcion = SuscripcionCanalAviso::where('usuario_id', $usuario->id)->where('canal', $canal)->first();

        return $suscripcion?->activo && $suscripcion->consentido_el && hash_equals($suscripcion->huella, $this->huella($destino)) ? $destino : null;
    }
}
