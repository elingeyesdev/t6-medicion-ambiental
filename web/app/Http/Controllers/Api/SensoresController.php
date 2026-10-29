<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Datos\PuntoRequest;
use App\Models\PuntoAmbiental;
use App\Services\Api\ParametrosConsulta;
use App\Services\Datos\CatalogoAmbiental;
use App\Services\Datos\CercaniaPuntos;
use App\Services\Datos\ConsultaHistorial;
use App\Services\Datos\RegistrarLecturas;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SensoresController
{
    public function catalogo(CatalogoAmbiental $catalogo)
    {
        return response()->json(['metricas' => $catalogo->metricas(), 'origenes' => CatalogoAmbiental::ORIGENES])->header('Cache-Control', 'no-store');
    }

    public function index(Request $request, CatalogoAmbiental $catalogo)
    {
        $datos = validator(ParametrosConsulta::leer($request, ['pagina', 'pais', 'buscar', 'activos']), [
            'pagina' => ['sometimes', 'integer', 'min:1', 'max:100000'], 'pais' => ['sometimes', Rule::in(array_column($catalogo->paises(), 'codigo'))],
            'buscar' => ['sometimes', 'string', 'max:100'], 'activos' => ['sometimes', 'boolean'],
        ])->validate();
        $consulta = PuntoAmbiental::query()->where('activo', (bool) ($datos['activos'] ?? true));
        if (isset($datos['pais'])) {
            $consulta->where('pais', $datos['pais']);
        }
        if (isset($datos['buscar'])) {
            $consulta->where('nombre', 'like', '%'.$datos['buscar'].'%');
        }
        $pagina = $consulta->orderBy('codigo')->paginate(50, ['codigo', 'nombre', 'pais', 'latitud', 'longitud', 'tipo', 'activo'], 'pagina', $datos['pagina'] ?? 1);

        return response()->json(['datos' => $pagina->items(), 'total' => $pagina->total(), 'pagina' => $pagina->currentPage(), 'ultimaPagina' => $pagina->lastPage()])->header('Cache-Control', 'no-store');
    }

    public function cercanos(Request $request, CercaniaPuntos $cercania)
    {
        $datos = validator(ParametrosConsulta::leer($request, ['latitud', 'longitud', 'radioKm']), ['latitud' => ['required', 'numeric', 'between:-90,90'], 'longitud' => ['required', 'numeric', 'between:-180,180'], 'radioKm' => ['sometimes', 'numeric', 'between:0.1,100']])->validate();

        return response()->json(['datos' => $cercania->buscar((float) $datos['latitud'], (float) $datos['longitud'], (float) ($datos['radioKm'] ?? 25)),
            'aviso' => 'Las lecturas pertenecen al punto registrado, no a cualquier coordenada cercana.'])->header('Cache-Control', 'no-store');
    }

    public function historial(Request $request, PuntoAmbiental $punto, ConsultaHistorial $historial)
    {
        return response()->json($historial->ejecutar($punto, ParametrosConsulta::leer($request, ['desde', 'hasta', 'metrica', 'origen', 'fuente', 'pagina'])))->header('Cache-Control', 'no-store');
    }

    public function guardar(PuntoRequest $request)
    {
        $punto = PuntoAmbiental::create($request->validated() + ['codigo' => 'p-'.Str::lower((string) Str::ulid()), 'tipo' => 'manual']);

        return response()->json(['punto' => $punto->only(['codigo', 'nombre', 'pais', 'latitud', 'longitud', 'tipo', 'activo'])], 201)->header('Cache-Control', 'no-store');
    }

    public function estado(Request $request, PuntoAmbiental $punto)
    {
        $datos = $request->validate(['activo' => ['required', 'boolean']]);
        $punto->forceFill(['activo' => (bool) $datos['activo']])->save();

        return response()->json(['punto' => $punto->only(['codigo', 'activo'])])->header('Cache-Control', 'no-store');
    }

    public function importar(Request $request, RegistrarLecturas $registrar)
    {
        $datos = $request->validate(['lecturas' => ['required', 'array', 'list', 'min:1', 'max:1000']]);

        return response()->json($registrar->ejecutar($datos['lecturas'], $request->user()->id))->header('Cache-Control', 'no-store');
    }
}
