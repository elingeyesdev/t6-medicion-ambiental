<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cuentas\NombreRequest;
use App\Http\Requests\Cuentas\PreferenciasRequest;
use App\Services\Cuentas\DatosCuenta;
use App\Services\Cuentas\GuardarPreferencias;
use Illuminate\Http\Request;

class CuentaController extends Controller
{
    public function index(Request $request)
    {
        return view('cuentas.perfil', ['usuario' => $request->user(), 'preferencias' => DatosCuenta::preferencias($request->user()),
            'modulos' => array_filter(config('geocruz.modulos'), fn ($m) => $m['habilitado'])]);
    }

    public function nombre(NombreRequest $request)
    {
        $datos = $request->validated();
        $request->user()->update(['name' => trim($datos['name'])]);

        return back()->with('estado', 'Nombre actualizado.');
    }

    public function preferencias(PreferenciasRequest $request, GuardarPreferencias $guardar)
    {
        $guardar->ejecutar($request->user(), $request->validated());

        return back()->with('estado', 'Ubicación actualizada.');
    }
}
