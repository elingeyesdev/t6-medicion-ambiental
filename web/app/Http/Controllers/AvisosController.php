<?php

namespace App\Http\Controllers;

use App\Models\AvisoAmbiental;
use App\Models\PreferenciasAvisos;
use App\Services\Datos\CatalogoAmbiental;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AvisosController extends Controller
{
    public function index(Request $request, CatalogoAmbiental $catalogo)
    {
        $lista = AvisoAmbiental::where('usuario_id', $request->user()->id)->with('alerta')->latest()->paginate(20);
        $pref = PreferenciasAvisos::where('usuario_id', $request->user()->id)->first()?->only(['activos', 'simulados', 'metricas']) ??
            ['activos' => false, 'simulados' => false, 'metricas' => array_keys($catalogo->metricas())];
        $sinLeer = AvisoAmbiental::where('usuario_id', $request->user()->id)->whereNull('leido_el')->count();
        if ($request->is('api/*')) {
            return response()->json(['datos' => $lista->getCollection()->map(fn ($a) => ['id' => $a->id, 'leido' => $a->leido_el !== null, 'alerta' => $a->alerta->paraCliente()]),
                'pagina' => $lista->currentPage(), 'ultimaPagina' => $lista->lastPage(), 'sinLeer' => $sinLeer, 'preferencias' => $pref])->header('Cache-Control', 'no-store');
        }

        return view('alertas.avisos', ['avisos' => $lista, 'preferencias' => $pref, 'sinLeer' => $sinLeer, 'metricas' => $catalogo->metricas(),
            'modulos' => array_filter(config('geocruz.modulos'), fn ($m) => $m['habilitado'])]);
    }

    public function preferencias(Request $request, CatalogoAmbiental $catalogo)
    {
        $datos = $request->validate(['activos' => ['required', 'boolean'], 'simulados' => ['required', 'boolean'],
            'metricas' => ['required', 'array', 'list', 'min:1', 'max:5'], 'metricas.*' => ['required', 'distinct', Rule::in(array_keys($catalogo->metricas()))], 'usuario_id' => ['prohibited']]);
        PreferenciasAvisos::updateOrCreate(['usuario_id' => $request->user()->id], $datos);

        return $request->is('api/*') ? response()->json(['guardado' => true])->header('Cache-Control', 'no-store') : back()->with('estado', 'Preferencias de avisos guardadas.');
    }

    public function leer(Request $request, string $id)
    {
        $aviso = AvisoAmbiental::where('usuario_id', $request->user()->id)->findOrFail($id);
        if ($aviso->leido_el === null) {
            $aviso->update(['leido_el' => now()]);
        }

        return $request->is('api/*') ? response()->json(['leido' => true])->header('Cache-Control', 'no-store') : back()->with('estado', 'Aviso marcado como leído.');
    }
}
