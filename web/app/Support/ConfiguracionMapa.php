<?php

namespace App\Support;

final class ConfiguracionMapa
{
    public static function paraWeb(): array
    {
        $token = config('mapas.token_publico');

        return [
            'proveedor' => 'mapbox',
            // Solo los tokens públicos de lectura pueden llegar al navegador.
            'tokenPublico' => is_string($token) && preg_match('/^pk\.[A-Za-z0-9._-]+$/D', $token) ? $token : null,
            'baseInicial' => 'relieve',
        ];
    }
}
