<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cuentas\CanalesAvisosRequest;
use App\Http\Requests\Cuentas\PushAvisosRequest;
use App\Models\AvisoAmbiental;
use App\Models\EnvioAviso;
use App\Models\PreferenciasAvisos;
use App\Services\Cuentas\DatosCuenta;
use App\Services\Cuentas\GuardarCanalesAvisos;
use App\Services\Datos\CatalogoAmbiental;
use App\Services\Notificaciones\CanalesAvisos;
use App\Services\Notificaciones\GuardarPushAvisos;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AvisosController extends Controller
{
    public function push(PushAvisosRequest $request, GuardarPushAvisos $guardar)
    {
        $guardar->ejecutar($request->user(), $request->boolean('activo'), $request->validated('token'));

        return response()->json(['mensaje' => $request->boolean('activo') ? 'Avisos del navegador activados.' : 'Avisos del navegador desactivados.'])->header('Cache-Control', 'no-store');
    }

    public function servicioPush(CanalesAvisos $canales)
    {
        abort_unless($canales->disponible('push'), 404);
        $publico = json_encode($canales->configuracionPushPublica(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
        $script = "importScripts('https://www.gstatic.com/firebasejs/13.0.0/firebase-app-compat.js');\n".
            "importScripts('https://www.gstatic.com/firebasejs/13.0.0/firebase-messaging-compat.js');\n".
            "firebase.initializeApp($publico);\nfirebase.messaging();\n";

        return response($script)->header('Content-Type', 'application/javascript')->header('Cache-Control', 'no-store')
            ->header('Service-Worker-Allowed', '/avisos');
    }

    public function index(Request $request, CatalogoAmbiental $catalogo)
    {
        $lista = AvisoAmbiental::where('usuario_id', $request->user()->id)->with('alerta')->latest()->orderByDesc('id')->paginate(20);
        $pref = PreferenciasAvisos::where('usuario_id', $request->user()->id)->first()?->only(['activos', 'simulados', 'metricas']) ??
            ['activos' => false, 'simulados' => false, 'metricas' => array_keys($catalogo->metricas())];
        $sinLeer = AvisoAmbiental::where('usuario_id', $request->user()->id)->whereNull('leido_el')->count();
        if ($request->is('api/*')) {
            return response()->json(['datos' => $lista->getCollection()->map(fn ($a) => ['id' => $a->id, 'leido' => $a->leido_el !== null, 'alerta' => $a->alerta->paraCliente()]),
                'pagina' => $lista->currentPage(), 'ultimaPagina' => $lista->lastPage(), 'sinLeer' => $sinLeer, 'preferencias' => $pref])->header('Cache-Control', 'no-store');
        }

        return view('alertas.avisos', ['avisos' => $lista, 'preferencias' => $pref, 'sinLeer' => $sinLeer, 'metricas' => $catalogo->metricas(),
            'canales' => app(CanalesAvisos::class), 'preferenciasCuenta' => DatosCuenta::preferencias($request->user()),
            'envios' => EnvioAviso::whereIn('aviso_id', $lista->getCollection()->pluck('id'))->get()->groupBy('aviso_id'),
            'modulos' => array_filter(config('geocruz.modulos'), fn ($m) => $m['habilitado'])]);
    }

    public function canales(CanalesAvisosRequest $request, GuardarCanalesAvisos $guardar)
    {
        $guardar->ejecutar($request->user(), $request->validated());

        return back()->with('estado', 'Canales de avisos actualizados.');
    }

    /** Respuesta inmediata: sin SSE, bucles, sleeps ni conexiones persistentes. */
    public function novedades(Request $request, CatalogoAmbiental $catalogo)
    {
        $propios = AvisoAmbiental::where('usuario_id', $request->user()->id);
        $lista = (clone $propios)->with('alerta')->latest()->orderByDesc('id')->paginate(20, ['*'], 'page', 1)->withPath(route('avisos'));
        $envios = EnvioAviso::whereIn('aviso_id', $lista->pluck('id'))->get()->groupBy('aviso_id');
        $revision = hash('sha256', $lista->getCollection()->map(fn ($a) => [$a->id, $a->leido_el?->toIso8601String(),
            ($envios[$a->id] ?? collect())->map(fn ($e) => [$e->canal, $e->estado])->all()])->toJson());

        return response()->json(['sinLeer' => (clone $propios)->whereNull('leido_el')->count(), 'revision' => $revision,
            'paginacion' => $lista->links()->render(),
            'html' => view('alertas.lista-avisos', ['avisos' => $lista, 'envios' => $envios, 'metricas' => $catalogo->metricas()])->render()])
            ->header('Cache-Control', 'no-store');
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
        AvisoAmbiental::whereKey($aviso->id)->whereNull('leido_el')->update(['leido_el' => now()]);

        return $request->is('api/*') ? response()->json(['leido' => true])->header('Cache-Control', 'no-store') : back()->with('estado', 'Aviso marcado como leído.');
    }
}
