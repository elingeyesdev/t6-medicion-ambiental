<?php

namespace App\Services\Cuentas;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CambiarClave
{
    public function ejecutar(User $usuario, string $anterior, string $nueva): User
    {
        return DB::transaction(function () use ($usuario, $anterior, $nueva): User {
            $cuenta = User::whereKey($usuario->id)->lockForUpdate()->firstOrFail();
            abort_unless($cuenta->activo, 403);
            if (! Hash::check($anterior, $cuenta->password)) {
                throw ValidationException::withMessages(['current_password' => 'Revisa tu contraseña actual.']);
            }
            $cuenta->forceFill(['password' => $nueva, 'remember_token' => Str::random(60), 'sesion_version' => $cuenta->sesion_version + 1])->save();
            $cuenta->tokens()->delete();
            DB::table('password_reset_tokens')->where('email', $cuenta->email)->delete();

            return $cuenta;
        }, 3);
    }
}
