<?php

namespace App\Http\Controllers;

use App\Models\Simulacion;
use App\Services\Api\ErrorConsulta;
use App\Services\Datos\CatalogoAmbiental;
use App\Services\Datos\RegistrarLecturas;
use App\Services\Simulaciones\SimularEscenario;
use App\Services\Territorios\RegionesEnvirosense;
use App\Support\FechaFormulario;
use Illuminate\Http\Request;

class SimulacionesController extends Controller
{
    public function index(Request $request, SimularEscenario $motor, CatalogoAmbiental $catalogo, RegionesEnvirosense $regiones)
    {
        if (! $request->is('api/*') && ! $request->user()->can('simulaciones.gestionar')) {
            return view('simulaciones.consulta', [
                'modulos' => array_filter(config('geocruz.modulos'), fn ($m) => $m['habilitado']),
                'metricas' => $catalogo->metricas(),
            ]);
        }
        $sesion = $request->filled('sesion') ? $motor->estado($request->query('sesion'), $request->user()->id) : null;
        $lista = Simulacion::where('usuario_id', $request->user()->id)->latest()->paginate(20);
        if ($request->is('api/*')) {
            return response()->json(['datos' => $lista->getCollection()->map(fn ($s) => $s->only(['id', 'nombre', 'estado', 'created_at'])), 'pagina' => $lista->currentPage(), 'ultimaPagina' => $lista->lastPage()])->header('Cache-Control', 'no-store');
        }

        return view('simulaciones.index', ['modulos' => array_filter(config('geocruz.modulos'), fn ($m) => $m['habilitado']),
            'sesiones' => $lista, 'sesion' => $sesion, 'metricas' => $catalogo->metricas(), 'puntos' => $catalogo->leer()['puntos'], 'regiones' => $regiones->listado(),
            'escenarios' => $motor->escenarios(), 'reproduccion' => $sesion ? ['sesion' => $sesion->paraCliente(), 'accion' => route('simulaciones.accion', $sesion->id),
                'estado' => url('/simulaciones/'.$sesion->id.'/estado'),
                'limites' => array_values(array_map(fn ($s) => $regiones->limite($s['region']), array_filter($sesion->resultado['series'], fn ($s) => isset($s['region']))))] : null]);
    }

    public function catalogo(SimularEscenario $motor, CatalogoAmbiental $catalogo, RegionesEnvirosense $regiones)
    {
        return response()->json(['escenarios' => $motor->escenarios(), 'metricas' => $catalogo->metricas(), 'puntos' => $catalogo->leer()['puntos'],
            'regiones' => array_map(fn ($r) => array_intersect_key($r, array_flip(['codigo', 'pais', 'nombre', 'nivel'])), $regiones->listado())])->header('Cache-Control', 'no-store');
    }

    public function guardar(Request $request, SimularEscenario $motor)
    {
        $datos = $request->except('_token');
        try {
            if (! $request->is('api/*')) {
                $datos['inicio'] = FechaFormulario::utc(is_string($datos['inicio'] ?? null) ? $datos['inicio'] : '');
                $zonas = [];
                foreach (is_array($datos['zonas'] ?? null) ? $datos['zonas'] : [] as $zona) {
                    if (! is_array($zona) || ! is_string($zona['punto'] ?? null) || $zona['punto'] === '') {
                        continue;
                    }
                    foreach (['inicio', 'fin', 'curva'] as $campo) {
                        if (! isset($zona[$campo]) || $zona[$campo] === '') {
                            unset($zona[$campo]);
                        }
                    }
                    if (str_starts_with($zona['punto'], 'region:')) {
                        $zona['region'] = substr($zona['punto'], 7);
                        unset($zona['punto']);
                    }
                    $zonas[] = $zona;
                }
                $datos['zonas'] = $zonas;
            }
            $sesion = $motor->crear($datos, $request->user()->id);
        } catch (ErrorConsulta $e) {
            if ($request->is('api/*')) {
                throw $e;
            }

            return back()->withErrors(['simulacion' => $e->getMessage()])->withInput();
        }

        return $request->is('api/*') ? response()->json($sesion->paraCliente(), 201)->header('Cache-Control', 'no-store') :
            redirect()->route('simulaciones', ['sesion' => $sesion->id])->with('estado', 'Simulación preparada. Sus datos están separados de las observaciones.');
    }

    public function mostrar(Request $request, string $id, SimularEscenario $motor)
    {
        return response()->json($motor->estado($id, $request->user()->id)->paraCliente())->header('Cache-Control', 'no-store');
    }

    public function accion(Request $request, string $id, SimularEscenario $motor)
    {
        try {
            $sesion = $motor->accion($id, $request->user()->id, $request->except('_token'));

            return response()->json($sesion->paraCliente())->header('Cache-Control', 'no-store');
        } catch (ErrorConsulta $e) {
            return response()->json(['error' => ['codigo' => $e->codigo, 'mensaje' => $e->getMessage()]], $e->estado)->header('Cache-Control', 'no-store');
        }
    }

    public function publicar(Request $request, string $id, SimularEscenario $motor, RegistrarLecturas $registrar)
    {
        try {
            $r = $motor->publicar($this->propia($request, $id), $request->user()->id, $registrar);
        } catch (ErrorConsulta $e) {
            if ($request->is('api/*')) {
                throw $e;
            }

            return back()->withErrors(['simulacion' => $e->getMessage()]);
        }

        return $request->is('api/*') ? response()->json($r)->header('Cache-Control', 'no-store') :
            back()->with('estado', $r['nuevas'].' lecturas simuladas guardadas; '.$r['repetidas'].' ya estaban guardadas y '.$r['futuras'].' son de fechas futuras y no se incorporaron al historial.');
    }

    private function propia(Request $request, string $id): Simulacion
    {
        return Simulacion::where('usuario_id', $request->user()->id)->findOrFail($id);
    }
}
