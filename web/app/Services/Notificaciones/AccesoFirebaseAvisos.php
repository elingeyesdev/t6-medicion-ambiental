<?php

namespace App\Services\Notificaciones;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class AccesoFirebaseAvisos
{
    public function token(): ?string
    {
        $clave = str_replace('\\n', "\n", (string) config('avisos.push.clave_privada'));
        $correo = config('avisos.push.cliente_email');
        $cache = 'avisos-fcm-'.hash('sha256', $correo.$clave);
        if (is_string($token = Cache::get($cache))) {
            return $token;
        }
        $base64 = fn ($dato) => rtrim(strtr(base64_encode($dato), '+/', '-_'), '=');
        $jwt = $base64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])).'.'.$base64(json_encode([
            'iss' => $correo, 'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token', 'iat' => now()->timestamp, 'exp' => now()->addHour()->timestamp,
        ]));
        $privada = @openssl_pkey_get_private($clave);
        if (! $privada || ! openssl_sign($jwt, $firma, $privada, OPENSSL_ALGO_SHA256)) {
            return null;
        }
        try {
            $r = Http::asForm()->acceptJson()->connectTimeout(3)->timeout(10)->withoutRedirecting()
                ->post('https://oauth2.googleapis.com/token', ['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt.'.'.$base64($firma)]);
            $token = $r->json('access_token');
            $vida = filter_var($r->json('expires_in'), FILTER_VALIDATE_INT);
            if (! $r->successful() || ! is_string($token) || $token === '' || ! $vida || $vida <= 60) {
                return null;
            }
            Cache::put($cache, $token, min(3300, $vida - 60));

            return $token;
        } catch (\Throwable) {
            return null;
        }
    }
}
