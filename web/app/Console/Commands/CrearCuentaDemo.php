<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

final class CrearCuentaDemo extends Command
{
    protected $signature = 'geocruz:crear-demo';

    protected $description = 'Crear una cuenta de demostración únicamente en un entorno local';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('La cuenta demo solo se puede crear en local.');

            return self::FAILURE;
        }

        $correo = 'demo@geocruz.test';
        if (User::where('email', $correo)->exists()) {
            $this->warn('La cuenta demo ya existe. Se conservan sus datos y contraseña.');

            return self::FAILURE;
        }

        $clave = getenv('GEOCRUZ_DEMO_CLAVE') ?: $this->secret('Contraseña para la cuenta demo');
        $validador = Validator::make(['password' => $clave], [
            'password' => ['bail', 'required', 'string', Password::min(12), function ($attribute, $value, $fail): void {
                if (strlen($value) > 72) {
                    $fail('La contraseña no puede superar 72 bytes.');
                }
            }],
        ]);
        if ($validador->fails()) {
            $this->error('Usa una contraseña de al menos 12 caracteres y hasta 72 bytes.');

            return self::INVALID;
        }

        User::create(['name' => 'Demo GeoCruz', 'email' => $correo, 'password' => $clave]);
        $this->info('Cuenta demo creada: '.$correo);
        $this->line('Ingresa por /login. Es una cuenta común, sin permisos de administración.');

        return self::SUCCESS;
    }
}
