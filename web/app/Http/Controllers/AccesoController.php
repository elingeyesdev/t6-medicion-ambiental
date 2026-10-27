<?php

namespace App\Http\Controllers;

use App\Http\Requests\Acceso\EntradaRequest;
use App\Http\Requests\Acceso\RegistroRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        if (! Auth::attempt($request->safe()->only(['email', 'password']))) {
            RateLimiter::hit($clave, 60);
            throw ValidationException::withMessages(['email' => 'El correo o la contraseña no coinciden.']);
        }
        RateLimiter::clear($clave);
        $request->session()->regenerate();

        return redirect()->intended(route('mapa', absolute: false));
    }

    public function registrar(RegistroRequest $request): RedirectResponse
    {
        $usuario = User::create($request->safe()->only(['name', 'email', 'password']));
        event(new Registered($usuario));
        Auth::login($usuario);
        $request->session()->regenerate();

        return redirect()->intended(route('mapa', absolute: false));
    }

    public function salir(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('inicio');
    }
}
