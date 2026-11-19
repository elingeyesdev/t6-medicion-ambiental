<?php

namespace App\Http\Controllers;

use App\Services\Api\ErrorConsulta;
use App\Services\Seguimiento\EvaluarZona;
use App\Services\Territorios\CatalogoTerritorial;
use App\Services\Territorios\GeometriaTerritorial;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class SeguimientoController extends Controller
{
    private const RESULTADOS = [
        'incendio_confirmado' => 'Incendio confirmado en campo',
        'sin_incendio' => 'Sin incendio en la revisión',
        'sin_verificar' => 'No se pudo verificar',
    ];

    public function index(Request $request, ?int $id = null): View
    {
        $zonas = $this->zonas($request)->orderByDesc('id')->paginate(12);
        $zona = $id === null ? null : $this->zona($request, $id);

        return view('seguimiento.index', [
            'modulos' => array_filter(config('geocruz.modulos'), fn ($modulo) => $modulo['habilitado']),
            'zonas' => $zonas,
            'zona' => $zona,
            'evaluacion' => $zona?->evaluacion === null ? null : json_decode($zona->evaluacion, true),
            'tareas' => $zona === null ? null : DB::table('tareas_seguimiento')->where('zona_id', $zona->id)
                ->orderByDesc('id')->paginate(10, ['*'], 'tareas'),
            'resultados' => self::RESULTADOS,
            'hoy' => CarbonImmutable::now('America/La_Paz')->toDateString(),
        ]);
    }

    public function guardar(Request $request, CatalogoTerritorial $catalogo, GeometriaTerritorial $geografia): RedirectResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'latitud' => ['required', 'numeric', 'between:-90,90'],
            'longitud' => ['required', 'numeric', 'between:-180,180'],
            'radio_km' => ['required', 'integer', 'between:1,50'],
        ]);
        $datos['latitud'] = round((float) $datos['latitud'], 5);
        $datos['longitud'] = round((float) $datos['longitud'], 5);
        if (! $geografia->contiene($catalogo->limite('BO07')['geometry'], $datos['longitud'], $datos['latitud'])) {
            throw ValidationException::withMessages(['latitud' => 'El centro debe estar dentro de Santa Cruz, Bolivia.']);
        }

        $id = DB::table('zonas_seguimiento')->insertGetId($datos + [
            'user_id' => $request->user()->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return to_route('seguimiento.zona', $id)->with('estado', 'Zona guardada.');
    }

    public function evaluar(Request $request, int $id, EvaluarZona $servicio): RedirectResponse
    {
        $zona = $this->zona($request, $id);
        $hoy = CarbonImmutable::now('America/La_Paz')->toDateString();
        $datos = $request->validate(['fecha' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.$hoy]]);
        try {
            $evaluacion = $servicio->consultar($zona, $datos['fecha'] ?? null);
        } catch (ErrorConsulta) {
            return to_route('seguimiento.zona', $id)->withInput()->withErrors(['fecha' => 'No pudimos consultar NASA FIRMS. Reintenta en unos minutos. La evaluación anterior sigue disponible.']);
        }

        $this->zonas($request)->where('id', $id)->update([
            'evaluacion' => json_encode($evaluacion, JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);

        return to_route('seguimiento.zona', $id)->with('estado', 'Evaluación registrada.');
    }

    public function crearTarea(Request $request, int $id): RedirectResponse
    {
        $zona = $this->zona($request, $id);
        $datos = $request->validate(['titulo' => ['required', 'string', 'max:140']]);
        abort_if($zona->evaluacion === null, 409, 'Evalúa la zona antes de crear una tarea.');

        DB::table('tareas_seguimiento')->insert($datos + [
            'zona_id' => $id,
            'evaluacion' => $zona->evaluacion,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return to_route('seguimiento.zona', $id)->with('estado', 'Tarea de campo creada.');
    }

    public function registrarResultado(Request $request, int $id, int $tarea): RedirectResponse
    {
        $this->zona($request, $id);
        $consulta = DB::table('tareas_seguimiento')->where('zona_id', $id)->where('id', $tarea);
        abort_unless((clone $consulta)->exists(), 404);

        $datos = $request->validate([
            'resultado_tipo' => ['required', Rule::in(array_keys(self::RESULTADOS))],
            'resultado_nota' => ['required', 'string', 'max:1000'],
        ]);

        $cambios = $consulta->whereNull('completada_en')->update($datos + [
            'completada_en' => now(),
            'updated_at' => now(),
        ]);
        abort_unless($cambios === 1, 409, 'Esta tarea ya tiene un resultado registrado.');

        return to_route('seguimiento.zona', $id)->with('estado', 'Resultado registrado.');
    }

    private function zonas(Request $request): Builder
    {
        return DB::table('zonas_seguimiento')->where('user_id', $request->user()->getKey());
    }

    private function zona(Request $request, int $id): object
    {
        return $this->zonas($request)->where('id', $id)->first() ?? abort(404);
    }
}
