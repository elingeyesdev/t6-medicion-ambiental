<?php

namespace App\Http\Controllers;

use App\Models\PlantillaReporte;
use App\Models\PuntoAmbiental;
use App\Models\ReporteAmbiental;
use App\Models\Simulacion;
use App\Services\Api\ErrorConsulta;
use App\Services\Datos\CatalogoAmbiental;
use App\Services\Reportes\ExportarReporte;
use App\Services\Reportes\PrepararReporte;
use App\Services\Reportes\VariablesReporte;
use App\Support\FechaFormulario;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReportesController extends Controller
{
    private function pantalla(array $datos = []): array
    {
        return $datos + ['modulos' => array_filter(config('geocruz.modulos'), fn ($m) => $m['habilitado'])];
    }

    public function index(Request $request, CatalogoAmbiental $catalogo)
    {
        $lista = ReporteAmbiental::where('usuario_id', $request->user()->id)->latest()->paginate(20);
        if ($request->is('api/*')) {
            return response()->json(['datos' => $lista->getCollection()->map(fn ($r) => $r->only(['id', 'titulo', 'created_at'])), 'pagina' => $lista->currentPage(), 'ultimaPagina' => $lista->lastPage()])->header('Cache-Control', 'no-store');
        }

        return view('reportes.index', $this->pantalla(['reportes' => $lista, 'metricas' => app(VariablesReporte::class)->metricas(), 'paises' => $catalogo->paises(),
            'puntosAtmosfera' => $catalogo->leer()['puntos'], 'variablesAtmosfera' => app(VariablesReporte::class)->atmosfericas(),
            'plantillas' => PlantillaReporte::where('usuario_id', $request->user()->id)->orderBy('nombre')->get(),
            'puntos' => PuntoAmbiental::orderBy('nombre')->limit(1000)->get(['codigo', 'nombre', 'pais']),
            'simulaciones' => Simulacion::where('usuario_id', $request->user()->id)->latest()->limit(100)->get(['id', 'nombre'])]));
    }

    public function guardar(Request $request, PrepararReporte $preparar)
    {
        $datos = $request->except('_token');
        try {
            if (! $request->is('api/*')) {
                $datos = array_filter($datos, fn ($v) => $v !== null && $v !== '');
                if (($datos['base'] ?? '') === 'mediciones') {
                    foreach (['desde', 'hasta'] as $campo) {
                        if (isset($datos[$campo])) {
                            $datos[$campo] = FechaFormulario::utc(is_string($datos[$campo]) ? $datos[$campo] : '');
                        }
                    }
                }
            }
            $reporte = $preparar->crear($datos, $request->user()->id);
        } catch (ErrorConsulta $e) {
            if ($request->is('api/*')) {
                throw $e;
            }

            return back()->withErrors(['reporte' => $e->getMessage()])->withInput();
        }

        return $request->is('api/*') ? response()->json($reporte->paraCliente(), 201)->header('Cache-Control', 'no-store') :
            redirect()->route('reportes.mostrar', $reporte->id)->with('estado', 'Reporte guardado. Los datos de este corte se conservan.');
    }

    public function mostrar(Request $request, string $id)
    {
        $reporte = $this->propio($request, $id);

        return $request->is('api/*') ? response()->json($reporte->paraCliente())->header('Cache-Control', 'no-store') :
            view('reportes.mostrar', $this->pantalla(['reporte' => $reporte, 'columnas' => PrepararReporte::COLUMNAS]));
    }

    public function descargar(Request $request, string $id, string $formato, ExportarReporte $exportar)
    {
        $reporte = $this->propio($request, $id);
        $contenido = $formato === 'pdf' ? $exportar->pdf($reporte) : $exportar->excel($reporte);

        return response($contenido)->header('Content-Type', $formato === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->header('Content-Disposition', 'attachment; filename="geosur-'.Str::slug($reporte->titulo).'.'.$formato.'"')->header('Cache-Control', 'no-store');
    }

    public function plantillas(Request $request, CatalogoAmbiental $catalogo)
    {
        $consulta = PlantillaReporte::where('usuario_id', $request->user()->id);
        if ($request->boolean('archivadas')) {
            $consulta->onlyTrashed();
        }
        $lista = $consulta->orderBy('nombre')->paginate(30);
        if ($request->is('api/*')) {
            return response()->json(['datos' => $lista->getCollection()->map(fn ($p) => $p->only(['id', 'nombre', 'configuracion', 'deleted_at'])), 'pagina' => $lista->currentPage(), 'ultimaPagina' => $lista->lastPage()])->header('Cache-Control', 'no-store');
        }

        return view('reportes.plantillas', $this->pantalla(['plantillas' => $lista, 'columnas' => PrepararReporte::COLUMNAS, 'archivadas' => $request->boolean('archivadas'),
            'tipos' => PrepararReporte::TIPOS, 'metricas' => app(VariablesReporte::class)->metricas()]));
    }

    public function guardarPlantilla(Request $request, PrepararReporte $preparar, ?string $id = null)
    {
        $plantilla = $id ? PlantillaReporte::where('usuario_id', $request->user()->id)->findOrFail($id) : null;
        $p = $preparar->plantilla($request->except('_token', '_method'), $request->user()->id, $plantilla);

        return $request->is('api/*') ? response()->json($p->only(['id', 'nombre', 'configuracion']), $id ? 200 : 201)->header('Cache-Control', 'no-store') :
            redirect()->route('plantillas')->with('estado', 'Plantilla guardada.');
    }

    public function archivarPlantilla(Request $request, string $id)
    {
        $p = PlantillaReporte::withTrashed()->where('usuario_id', $request->user()->id)->findOrFail($id);
        if ($request->boolean('restaurar')) {
            $p->restore();
        } else {
            $p->delete();
        }

        return $request->is('api/*') ? response()->json(['archivada' => $p->trashed()])->header('Cache-Control', 'no-store') :
            redirect()->route('plantillas')->with('estado', $p->trashed() ? 'Plantilla archivada.' : 'Plantilla restaurada.');
    }

    private function propio(Request $request, string $id): ReporteAmbiental
    {
        return ReporteAmbiental::where('usuario_id', $request->user()->id)->findOrFail($id);
    }
}
