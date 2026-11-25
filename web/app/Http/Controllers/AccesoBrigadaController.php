<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

final class AccesoBrigadaController extends Controller
{
    public function formulario(Request $request): Response|RedirectResponse
    {
        if (Auth::guard('brigada')->user()?->activo) {
            return $this->destino($request->query('aviso'));
        }

        $aviso = $request->query('aviso');

        return response()->view('portal-incendios.ingreso', [
            'aviso' => is_string($aviso) && Str::isUuid($aviso) ? $aviso : null,
        ])->header('Cache-Control', 'no-store');
    }

    public function entrar(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'email' => ['required', 'email', 'max:254'],
            'password' => ['required', 'string', 'max:255'],
        ]);
        $correo = strtolower(trim($datos['email']));
        if (! Auth::guard('brigada')->attempt(['email' => $correo, 'password' => $datos['password'], 'activo' => true])) {
            return back()->withInput(['email' => $correo])->withErrors(['email' => 'No pudimos iniciar sesión con esos datos.']);
        }
        $request->session()->regenerate();

        return $this->destino($request->input('aviso'));
    }

    public function salir(Request $request): RedirectResponse
    {
        Auth::guard('brigada')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal-incendios.index');
    }

    private function destino(mixed $aviso): RedirectResponse
    {
        return is_string($aviso) && Str::isUuid($aviso)
            ? redirect()->route('portal-incendios.ficha', ['aviso' => $aviso])
            : redirect()->route('portal-incendios.index');
    }
}
