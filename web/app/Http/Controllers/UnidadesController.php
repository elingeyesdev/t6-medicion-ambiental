<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cuentas\UnidadesRequest;
use App\Models\User;
use App\Services\Datos\UnidadesPresentacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UnidadesController extends Controller
{
    public function mostrar(Request $request, UnidadesPresentacion $unidades)
    {
        return response()->json(['unidades' => $unidades->preferencias($request->user()), 'catalogo' => config('unidades')])->header('Cache-Control', 'no-store');
    }

    public function guardar(UnidadesRequest $request, UnidadesPresentacion $unidades)
    {
        DB::transaction(function () use ($request, $unidades): void {
            $usuario = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $usuario->preferencias()->updateOrCreate([], ['unidades' => array_replace($unidades->preferencias($usuario), $request->validated('unidades'))]);
        }, 3);
        $request->user()->unsetRelation('preferencias');

        return $request->expectsJson() || $request->is('api/*')
            ? $this->mostrar($request, $unidades) : back()->with('estado', 'Unidades guardadas.');
    }
}
