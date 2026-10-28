<?php

namespace App\Http\Controllers;

use App\Models\AlertaAmbiental;
use App\Services\Alertas\UmbralesAmbientales;
use App\Services\Api\ErrorConsulta;
use App\Services\Api\ParametrosConsulta;
use App\Services\Datos\CatalogoAmbiental;
use App\Support\FechaFormulario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AlertasController extends Controller
{
    public function index(Request $request, CatalogoAmbiental $catalogo)
    {
        $f = array_filter($request->query(), fn ($v) => $v !== null && $v !== '');
        $datos = Validator::make($f, ['metrica' => ['sometimes', Rule::in(array_keys($catalogo->metricas()))],
            'origen' => ['sometimes', Rule::in(array_keys(CatalogoAmbiental::ORIGENES))], 'severidad' => ['sometimes', Rule::in(['critica', 'emergencia'])],
            'reconocida' => ['sometimes', Rule::in(['0', '1'])], 'desde' => ['sometimes', 'string'], 'hasta' => ['sometimes', 'string'], 'page' => ['sometimes', 'integer', 'min:1', 'max:100000']])->validate();
        $q = AlertaAmbiental::query();
        foreach (['metrica', 'origen', 'severidad'] as $campo) {
            if (isset($datos[$campo])) {
                $q->where($campo, $datos[$campo]);
            }
        }
        if (! isset($datos['origen'])) {
            $q->where('origen', '!=', 'simulado');
        }
        if (isset($datos['reconocida'])) {
            $datos['reconocida'] === '1' ? $q->whereNotNull('reconocida_el') : $q->whereNull('reconocida_el');
        }
        try {
            $fechas = [];
            foreach (['desde' => '>=', 'hasta' => '<'] as $campo => $operador) {
                if (isset($datos[$campo])) {
                    $fecha = ParametrosConsulta::instante($request->is('api/*') ? $datos[$campo] : FechaFormulario::utc($datos[$campo]));
                    $fechas[$campo] = $fecha;
                    if ($fecha->getTimestamp() > now()->timestamp) {
                        throw new ErrorConsulta('FILTROS_INVALIDOS', 'No se pueden consultar alertas del futuro.');
                    }
                    $q->where('instante', $operador, $fecha->format('Y-m-d H:i:s'));
                }
            }
            if (isset($fechas['desde'], $fechas['hasta']) && $fechas['desde'] >= $fechas['hasta']) {
                throw new ErrorConsulta('FILTROS_INVALIDOS', 'La fecha de inicio debe ser anterior al final.');
            }
        } catch (ErrorConsulta $e) {
            if ($request->is('api/*')) {
                throw $e;
            }

            return back()->withErrors(['filtros' => 'Revisa las fechas elegidas.']);
        }
        $lista = $q->orderByDesc('instante')->orderByDesc('id')->paginate(20)->withQueryString();
        if ($request->is('api/*')) {
            return response()->json(['datos' => $lista->getCollection()->map(fn ($a) => $a->paraCliente()), 'pagina' => $lista->currentPage(), 'total' => $lista->total(), 'ultimaPagina' => $lista->lastPage()])->header('Cache-Control', 'no-store');
        }

        return view('alertas.index', ['alertas' => $lista, 'metricas' => $catalogo->metricas(), 'filtros' => $datos,
            'modulos' => array_filter(config('geocruz.modulos'), fn ($m) => $m['habilitado'])]);
    }

    public function reconocer(Request $request, string $id)
    {
        Validator::make($request->except('_token', '_method'), ['usuarioId' => 'prohibited', 'usuario_id' => 'prohibited', 'reconocida_por' => 'prohibited'])->validate();
        $alerta = DB::transaction(function () use ($id, $request) {
            $a = AlertaAmbiental::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($a->reconocida_el === null) {
                $a->update(['reconocida_por' => $request->user()->id, 'reconocida_el' => now()]);
            }

            return $a;
        });

        return $request->is('api/*') ? response()->json($alerta->paraCliente())->header('Cache-Control', 'no-store') : back()->with('estado', 'Alerta reconocida. Quedó guardada en el historial.');
    }

    public function umbrales(Request $request, UmbralesAmbientales $umbrales, CatalogoAmbiental $catalogo)
    {
        return $request->is('api/*') ? response()->json($umbrales->catalogo())->header('Cache-Control', 'no-store') :
            view('alertas.umbrales', ['catalogo' => $umbrales->catalogo(), 'metricas' => $catalogo->metricas(), 'modulos' => array_filter(config('geocruz.modulos'), fn ($m) => $m['habilitado'])]);
    }
}
