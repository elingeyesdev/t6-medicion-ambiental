<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Acceso\EntradaRequest;
use App\Http\Requests\Acceso\RegistroRequest;
use App\Http\Requests\Cuentas\NombreRequest;
use App\Http\Requests\Cuentas\PreferenciasRequest;
use App\Models\User;
use App\Services\Api\ErrorConsulta;
use App\Services\Cuentas\DatosCuenta;
use App\Services\Cuentas\GuardarPreferencias;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class CuentaController
{
    public function entrar(EntradaRequest $request)
    {
        $clave = 'entrada:'.hash('sha256', $request->string('email')->toString().'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($clave, 5)) {
            throw new ErrorConsulta('SOLICITUDES_EXCESIVAS', 'Espera un minuto antes de volver a ingresar.', 429);
        }
        try {
            $respuesta = DB::transaction(function () use ($request) {
                $usuario = User::where('email', $request->string('email')->toString())->lockForUpdate()->first();
                if (! $usuario || ! Hash::check($request->string('password')->toString(), $usuario->password) || ! $usuario->activo) {
                    throw new ErrorConsulta('CREDENCIALES_INVALIDAS', 'El correo o la contraseña no coinciden.', 401);
                }

                return $this->autorizarDispositivo($usuario);
            }, 3);
        } catch (ErrorConsulta $e) {
            RateLimiter::hit($clave, 60);
            throw $e;
        }
        RateLimiter::clear($clave);

        return $respuesta;
    }

    public function registrar(RegistroRequest $request)
    {
        $usuario = User::create($request->safe()->only(['name', 'email', 'password']));
        event(new Registered($usuario));

        return $this->autorizarDispositivo($usuario, 201);
    }

    private function autorizarDispositivo(User $usuario, int $estado = 200)
    {
        // Una autorización móvil queda limitada a 24 horas y se revoca al suspender/cambiar la cuenta.
        $token = $usuario->createToken('GeoSur móvil', ['cuenta'], now()->addDay());

        return response()->json(['usuario' => DatosCuenta::usuario($usuario), 'token' => $token->plainTextToken,
            'tipoToken' => 'Bearer', 'venceEn' => $token->accessToken->expires_at->toIso8601String()], $estado)->header('Cache-Control', 'no-store');
    }

    public function mostrar(Request $request)
    {
        return response()->json(['usuario' => DatosCuenta::usuario($request->user()), 'preferencias' => DatosCuenta::preferencias($request->user())])->header('Cache-Control', 'no-store');
    }

    public function preferencias(PreferenciasRequest $request, GuardarPreferencias $guardar)
    {
        $guardar->ejecutar($request->user(), $request->validated());

        return $this->mostrar($request);
    }

    public function nombre(NombreRequest $request)
    {
        $request->user()->update(['name' => trim($request->validated('name'))]);

        return $this->mostrar($request);
    }

    public function salir(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['mensaje' => 'Sesión cerrada.'])->header('Cache-Control', 'no-store');
    }
}
