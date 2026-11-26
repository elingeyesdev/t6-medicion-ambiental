<?php

namespace App\Http\Controllers;

use App\Models\AvisoIncendio;
use App\Models\BrigadistaIncendios;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

final class SolicitudBrigadaController extends Controller
{
    public function formulario(Request $request): Response|RedirectResponse
    {
        $aviso = $this->avisoSolicitado($request);
        if (Auth::guard('brigada')->user()?->activo) {
            return $this->destino($aviso);
        }

        return response()->view('portal-incendios.solicitud', ['aviso' => $aviso])->header('Cache-Control', 'no-store');
    }

    public function registrar(Request $request): RedirectResponse
    {
        $aviso = $this->avisoSolicitado($request);
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'min:3', 'max:120', 'not_regex:/[\p{Cc}\p{Cf}]/u'],
            'email' => ['required', 'string', 'email', 'max:254'],
            'password' => ['required', 'string', 'max:128', 'confirmed', Password::min(12)],
        ], [
            'nombre.required' => 'Escribe tu nombre completo.',
            'password.confirmed' => 'Las contraseñas deben coincidir.',
            'password.min' => 'Usa al menos 12 caracteres.',
        ]);

        $datos['email'] = strtolower(trim($datos['email']));
        $cuenta = new BrigadistaIncendios($datos);
        $cuenta->activo = true;
        try {
            $cuenta->save();
        } catch (UniqueConstraintViolationException) {
            return redirect()->route('portal-incendios.brigada', $aviso ? ['aviso' => $aviso] : [])
                ->with('solicitud_enviada', 'Si ya tienes una cuenta, puedes iniciar sesión.');
        }

        Auth::guard('brigada')->login($cuenta);
        $request->session()->regenerate();

        return $this->destino($aviso)->with('estado', 'Tu cuenta está lista.');
    }

    private function avisoSolicitado(Request $request): ?string
    {
        $aviso = $request->query('aviso');

        return is_string($aviso) && Str::isUuid($aviso) && AvisoIncendio::query()->whereKey($aviso)->exists() ? $aviso : null;
    }

    private function destino(?string $aviso): RedirectResponse
    {
        return $aviso
            ? redirect()->route('portal-incendios.ficha', ['aviso' => $aviso])
            : redirect()->route('portal-incendios.index');
    }
}
