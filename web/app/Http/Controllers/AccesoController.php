<?php

namespace App\Http\Controllers;

use App\Http\Requests\Acceso\EntradaRequest;
use App\Http\Requests\Acceso\RegistroRequest;
use App\Models\User;
use App\Services\Cuentas\GuardarCanalesAvisos;
use App\Services\Cuentas\GuardarPreferencias;
use App\Services\Notificaciones\GuardarPushAvisos;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AccesoController extends Controller
{
    public function entrar(EntradaRequest $request): RedirectResponse
    {
        $clave = 'entrada:'.hash('sha256', $request->string('email')->toString().'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($clave, 5)) {
            throw ValidationException::withMessages(['email' => 'Espera un minuto y vuelve a intentarlo.']);
        }
        if (! Auth::attempt($request->safe()->only(['email', 'password']) + ['activo' => true])) {
            RateLimiter::hit($clave, 60);
            throw ValidationException::withMessages(['email' => 'El correo o la contraseña no coinciden.']);
        }
        RateLimiter::clear($clave);
        $request->session()->regenerate();
        $request->session()->put('cuenta_version', $request->user()->sesion_version);

        return redirect()->intended(route('mapa', absolute: false));
    }

    public function registrar(RegistroRequest $request, GuardarPreferencias $preferencias, GuardarCanalesAvisos $canales): RedirectResponse
    {
        $usuario = DB::transaction(function () use ($request, $preferencias, $canales): User {
            $usuario = User::create($request->safe()->only(['name', 'email', 'password']));
            if ($request->filled('latitud') || $request->filled('longitud')) {
                $preferencias->ejecutar($usuario, $request->safe()->only(['latitud', 'longitud']));
            }
            if ($request->boolean('notif_email') || $request->boolean('notif_telegram') || $request->boolean('notif_whatsapp')) {
                $canales->ejecutar($usuario, $request->safe()->only(['telegram_destino', 'whatsapp_destino', 'consentimiento']) + [
                    'notif_email' => $request->boolean('notif_email'), 'notif_telegram' => $request->boolean('notif_telegram'), 'notif_whatsapp' => $request->boolean('notif_whatsapp'),
                ]);
            }

            return $usuario;
        });
        event(new Registered($usuario));
        Auth::login($usuario);
        $request->session()->regenerate();
        $request->session()->put('cuenta_version', $usuario->sesion_version);

        return redirect()->intended(route('mapa', absolute: false));
    }

    public function salir(Request $request): RedirectResponse
    {
        app(GuardarPushAvisos::class)->ejecutar($request->user(), false);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('inicio');
    }
}
