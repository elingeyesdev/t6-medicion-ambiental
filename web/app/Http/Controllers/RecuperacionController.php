<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as ReglaClave;
use Illuminate\Validation\ValidationException;

class RecuperacionController extends Controller
{
    public function solicitar(Request $request)
    {
        $this->normalizarCorreo($request);
        $datos = $request->validate(['email' => ['required', 'string', 'email', 'max:254']]);
        try {
            Password::sendResetLink($datos + ['activo' => true]);
        } catch (\Throwable) {
            // Igual respuesta para cuentas presentes/ausentes; no registrar correo, enlace ni excepción del transporte.
            Log::warning('RECUPERACION_TRANSPORTE_NO_CONFIRMADO');
        }

        $mensaje = 'Si ese correo tiene una cuenta activa, recibirás un enlace para recuperar el acceso.';
        if (app()->environment('local', 'testing') || config('mail.default') === 'log') {
            $mensaje = 'Si ese correo tiene una cuenta activa, el enlace se guardará en el registro local de correo. No llegará a tu buzón.';
        }

        return $request->is('api/*') ? response()->json(['mensaje' => $mensaje])->header('Cache-Control', 'no-store') : back()->with('estado', $mensaje);
    }

    public function formulario(Request $request, string $token)
    {
        $email = $request->query('email');

        return response()->view('acceso.restablecer', ['token' => $token, 'correo' => is_string($email) ? $email : ''])
            ->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function restablecer(Request $request)
    {
        $this->normalizarCorreo($request);
        $datos = $request->validate(['email' => ['required', 'string', 'email', 'max:254'], 'token' => ['required', 'string', 'max:256'],
            'password' => ['bail', 'required', 'string', 'confirmed', ReglaClave::min(12), function ($attribute, $value, $fail): void {
                if (strlen($value) > 72) {
                    $fail('La contraseña no puede superar 72 bytes.');
                }
            }]]);
        $estado = DB::transaction(function () use ($datos): string {
            // El enlace se consume dentro de la misma transacción que cambia la clave.
            DB::table('password_reset_tokens')->where('email', $datos['email'])->lockForUpdate()->first();

            return Password::reset($datos + ['activo' => true], function (User $usuario, string $clave): void {
                $usuario = User::lockForUpdate()->findOrFail($usuario->id);
                if (! $usuario->activo) {
                    throw ValidationException::withMessages(['email' => 'El enlace ya no es válido. Pide uno nuevo e inténtalo otra vez.']);
                }
                $usuario->forceFill(['password' => $clave, 'remember_token' => Str::random(60), 'sesion_version' => $usuario->sesion_version + 1])->save();
                $usuario->tokens()->delete();
                event(new PasswordReset($usuario));
            });
        }, 3);
        if ($estado !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'El enlace ya no es válido. Pide uno nuevo e inténtalo otra vez.']);
        }

        $mensaje = 'Tu contraseña se actualizó. Ya puedes ingresar.';

        return $request->is('api/*') ? response()->json(['mensaje' => $mensaje])->header('Cache-Control', 'no-store') : redirect()->route('login')->with('estado', $mensaje);
    }

    private function normalizarCorreo(Request $request): void
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => Str::lower(trim($request->input('email')))]);
        }
    }
}
