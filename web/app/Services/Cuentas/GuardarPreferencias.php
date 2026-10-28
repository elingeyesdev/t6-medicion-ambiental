<?php

namespace App\Services\Cuentas;

use App\Models\PreferenciasCuenta;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class GuardarPreferencias
{
    public function ejecutar(User $usuario, array $datos): void
    {
        $valores = array_replace(DatosCuenta::preferencias($usuario), $datos);
        if ($valores['latitud'] === null || $valores['longitud'] === null) {
            $valores = array_replace($valores, ['latitud' => null, 'longitud' => null, 'notif_email' => false, 'notif_whatsapp' => false, 'notif_telegram' => false]);
        }
        foreach (['whatsapp', 'telegram'] as $canal) {
            if ($valores['notif_'.$canal] && ! filled($valores[$canal.'_destino'])) {
                throw ValidationException::withMessages([$canal.'_destino' => 'Escribe el destino del aviso.']);
            }
        }
        $usuario->preferencias()->updateOrCreate([], array_intersect_key($valores, PreferenciasCuenta::vacias()));
        $usuario->unsetRelation('preferencias');
    }
}
