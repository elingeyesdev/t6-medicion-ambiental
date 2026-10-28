<?php

namespace App\Console\Commands;

use App\Enums\RolCuenta;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PrimerAdministrador extends Command
{
    protected $signature = 'geosur:primer-admin {email} {--confirmar : Confirmar el alta inicial de administración local}';

    protected $description = 'Da acceso de administrador a una cuenta local existente, solo si todavía no hay otro.';

    public function handle(): int
    {
        if (! app()->environment('local', 'testing') || ! $this->option('confirmar')) {
            $this->error('Disponible solo en local, con --confirmar.');

            return self::FAILURE;
        }

        return DB::transaction(function (): int {
            $usuarios = User::orderBy('id')->lockForUpdate()->get();
            if ($usuarios->contains(fn (User $u) => $u->activo && $u->rol === RolCuenta::Administrador)) {
                $this->error('Ya hay una cuenta de administrador activa. Pídele que gestione los permisos.');

                return self::FAILURE;
            }
            $usuario = $usuarios->firstWhere('email', Str::lower(trim($this->argument('email'))));
            if (! $usuario || ! $usuario->activo) {
                $this->error('Hace falta una cuenta local activa con ese correo.');

                return self::FAILURE;
            }
            $usuario->forceFill(['rol' => RolCuenta::Administrador, 'sesion_version' => $usuario->sesion_version + 1])->save();
            $usuario->tokens()->delete();
            $this->info('Cuenta de administrador preparada. Vuelve a ingresar.');

            return self::SUCCESS;
        }, 3);
    }
}
