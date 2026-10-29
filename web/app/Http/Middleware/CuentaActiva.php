<?php

namespace App\Http\Middleware;

use App\Services\Api\ErrorConsulta;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CuentaActiva
{
    public function handle(Request $request, Closure $next): mixed
    {
        $usuario = $request->user();
        $versionInvalida = $request->hasSession() && (int) $request->session()->get('cuenta_version', 1) !== $usuario->sesion_version;
        if (! $usuario->activo || $versionInvalida) {
            if ($request->hasSession()) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
            if ($request->is('api/*') || $request->expectsJson()) {
                throw new ErrorConsulta('NO_AUTENTICADO', 'Vuelve a ingresar para continuar.', 401);
            }

            return redirect()->route('login')->withErrors(['email' => 'Vuelve a ingresar para continuar.']);
        }

        return $next($request)->header('Cache-Control', 'no-store');
    }
}
