<?php

namespace App\Services\Cuentas;

use App\Models\PreferenciasCuenta;
use App\Models\User;

final class DatosCuenta
{
    public static function usuario(User $usuario): array
    {
        return ['id' => $usuario->id, 'nombre' => $usuario->name, 'email' => $usuario->email,
            'rol' => $usuario->rol->value, 'rolNombre' => $usuario->rol->nombre(), 'activo' => $usuario->activo,
            'permisos' => $usuario->activo ? $usuario->rol->permisos() : []];
    }

    public static function preferencias(User $usuario): array
    {
        return $usuario->preferencias?->only(array_keys(PreferenciasCuenta::vacias())) ?? PreferenciasCuenta::vacias();
    }
}
