<?php

namespace App\Services\Cuentas;

use App\Enums\RolCuenta;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AdministrarCuenta
{
    public function cambiar(User $actor, User $destino, ?RolCuenta $rol = null, ?bool $activo = null): User
    {
        return DB::transaction(function () use ($actor, $destino, $rol, $activo): User {
            // Serializar cambios de administración evita que dos cuentas se retiren mutuamente el permiso a la vez.
            User::where('rol', RolCuenta::Administrador->value)->orderBy('id')->lockForUpdate()->get(['id']);
            $operador = User::lockForUpdate()->findOrFail($actor->id);
            abort_unless($operador->activo && $operador->rol === RolCuenta::Administrador, 403);
            $usuario = User::lockForUpdate()->findOrFail($destino->id);
            if ($usuario->is($operador)) {
                throw ValidationException::withMessages(['cuenta' => 'Pide a otro administrador que cambie tu cuenta.']);
            }
            $cambios = [];
            if ($rol !== null && $rol !== $usuario->rol) {
                $cambios['rol'] = $rol;
            }
            if ($activo !== null && $activo !== $usuario->activo) {
                $cambios['activo'] = $activo;
            }
            if ($cambios !== []) {
                $usuario->forceFill($cambios + ['sesion_version' => $usuario->sesion_version + 1])->save();
                $usuario->tokens()->delete();
            }

            return $usuario;
        }, 3);
    }
}
