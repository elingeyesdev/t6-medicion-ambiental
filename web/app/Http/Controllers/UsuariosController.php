<?php

namespace App\Http\Controllers;

use App\Enums\RolCuenta;
use App\Models\User;
use App\Services\Cuentas\AdministrarCuenta;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UsuariosController extends Controller
{
    public function index(Request $request)
    {
        $datos = $request->validate(['buscar' => ['nullable', 'string', 'max:100'], 'page' => ['sometimes', 'integer', 'min:1']]);
        $consulta = User::query()->when($datos['buscar'] ?? '', function ($query, $buscar): void {
            $query->where(fn ($q) => $q->where('name', 'like', '%'.$buscar.'%')->orWhere('email', 'like', '%'.$buscar.'%'));
        });
        $activas = (clone $consulta)->where('activo', true)->count();
        $usuarios = $consulta->orderBy('id')->paginate(15)->withQueryString();

        return view('cuentas.usuarios', ['usuarios' => $usuarios, 'roles' => RolCuenta::cases(), 'activas' => $activas,
            'modulos' => array_filter(config('geocruz.modulos'), fn ($m) => $m['habilitado'])]);
    }

    public function rol(Request $request, User $usuario, AdministrarCuenta $administrar)
    {
        $datos = $request->validate(['rol' => ['required', Rule::enum(RolCuenta::class)]]);
        $administrar->cambiar($request->user(), $usuario, RolCuenta::from($datos['rol']));

        return back()->with('estado', 'Rol actualizado.');
    }

    public function estado(Request $request, User $usuario, AdministrarCuenta $administrar)
    {
        $datos = $request->validate(['activo' => ['required', 'boolean']]);
        $administrar->cambiar($request->user(), $usuario, activo: (bool) $datos['activo']);

        return back()->with('estado', $datos['activo'] ? 'La cuenta está activa.' : 'La cuenta quedó suspendida.');
    }
}
