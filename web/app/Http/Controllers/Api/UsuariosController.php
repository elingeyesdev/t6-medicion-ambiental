<?php

namespace App\Http\Controllers\Api;

use App\Enums\RolCuenta;
use App\Models\User;
use App\Services\Cuentas\AdministrarCuenta;
use App\Services\Cuentas\DatosCuenta;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UsuariosController
{
    public function index(Request $request)
    {
        $datos = $request->validate(['pagina' => ['sometimes', 'integer', 'min:1']]);
        $pagina = User::orderBy('id')->paginate(15, ['*'], 'pagina', $datos['pagina'] ?? 1);

        return response()->json(['datos' => $pagina->getCollection()->map(fn ($u) => DatosCuenta::usuario($u)),
            'pagina' => $pagina->currentPage(), 'total' => $pagina->total(), 'ultimaPagina' => $pagina->lastPage(),
            'roles' => array_map(fn ($rol) => ['clave' => $rol->value, 'nombre' => $rol->nombre()], RolCuenta::cases())])->header('Cache-Control', 'no-store');
    }

    public function rol(Request $request, User $usuario, AdministrarCuenta $administrar)
    {
        $datos = $request->validate(['rol' => ['required', Rule::enum(RolCuenta::class)]]);
        $usuario = $administrar->cambiar($request->user(), $usuario, RolCuenta::from($datos['rol']));

        return response()->json(['usuario' => DatosCuenta::usuario($usuario)])->header('Cache-Control', 'no-store');
    }

    public function estado(Request $request, User $usuario, AdministrarCuenta $administrar)
    {
        $datos = $request->validate(['activo' => ['required', 'boolean']]);
        $usuario = $administrar->cambiar($request->user(), $usuario, activo: (bool) $datos['activo']);

        return response()->json(['usuario' => DatosCuenta::usuario($usuario)])->header('Cache-Control', 'no-store');
    }
}
