<?php

namespace App\Http\Controllers;

use App\Services\Api\ErrorConsulta;
use App\Services\Seguimiento\EvaluarZona;
use App\Services\Territorios\CatalogoTerritorial;
use App\Services\Territorios\GeometriaTerritorial;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class SeguimientoController extends Controller
{
    private const COORDENADAS = [
        'latitud' => ['required', 'numeric', 'between:-90,90'],
        'longitud' => ['required', 'numeric', 'between:-180,180'],
    ];

    private const RESULTADOS = [
        'incendio_confirmado' => 'Incendio confirmado en campo',
        'sin_incendio' => 'Sin incendio en la revisión',
        'sin_verificar' => 'No se pudo verificar',
    ];

    public function index(Request $request, ?int $id = null): View
    {
        $zonas = $this->zonas($request)->orderByDesc('id')->paginate(12);
        $zona = $id === null ? null : $this->zona($request, $id);
        $ubicacion = Validator::make($request->query(), self::COORDENADAS);

        $hoy = CarbonImmutable::now('America/La_Paz')->toDateString();
        $fecha = ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.$hoy];
        $periodo = Validator::make($request->query(), ['fecha' => $fecha]);

        $evaluacion = $zona?->evaluacion === null ? null : json_decode($zona->evaluacion, true);
        $vigilancia = $zona === null ? null : EvaluarZona::ultimaVigilancia($zona->id);
        $reciente = ($vigilancia['hasta'] ?? '') >= ($evaluacion['hasta'] ?? '')
            ? ($vigilancia ?? $evaluacion) : $evaluacion;
        $ultima = isset($vigilancia['consultadoEn']) ? CarbonImmutable::parse($vigilancia['consultadoEn']) : null;
        $esperaAviso = $ultima === null ? 0 : max(0, $ultima->addMinutes(10)->getTimestamp() - now()->getTimestamp()) * 1000;

        return view('seguimiento.index', [
            'esperaAviso' => $esperaAviso,
            'fechaInicial' => $periodo->passes() ? ($periodo->validated()['fecha'] ?? null) : null,
            'modulos' => array_filter(config('geocruz.modulos'), fn ($modulo) => $modulo['habilitado']),
            'zonas' => $zonas,
            'zona' => $zona,
            'coordenadasIniciales' => $zona === null && $ubicacion->passes() ? $ubicacion->validated() : [],
            'evaluacion' => $evaluacion,
            'resumenVigilancia' => $reciente === null ? null : $this->resumenVigilancia($reciente),
            'tareas' => $zona === null ? null : DB::table('tareas_seguimiento')->where('zona_id', $zona->id)
                ->orderByDesc('id')->paginate(10, ['*'], 'tareas'),
            'resultados' => self::RESULTADOS,
            'hoy' => $hoy,
        ]);
    }

    public function guardar(Request $request, CatalogoTerritorial $catalogo, GeometriaTerritorial $geografia, EvaluarZona $servicio): RedirectResponse
    {
        $hoy = CarbonImmutable::now('America/La_Paz')->toDateString();
        $opciones = $request->validate([
            'fecha' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.$hoy],
            'evaluar' => ['sometimes', 'accepted'],
        ]);
        $fecha = $opciones['fecha'] ?? null;
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'radio_km' => ['required', 'integer', 'between:1,50'],
        ] + self::COORDENADAS);
        $datos['latitud'] = round((float) $datos['latitud'], 5);
        $datos['longitud'] = round((float) $datos['longitud'], 5);
        if (! $geografia->contiene($catalogo->limite('BO07')['geometry'], $datos['longitud'], $datos['latitud'])) {
            throw ValidationException::withMessages(['latitud' => 'El centro debe estar dentro de Santa Cruz, Bolivia.']);
        }

        // Un reintento de guardado puede llegar después de que la zona ya se creó.
        $id = $this->zonas($request)->where($datos)->orderBy('id')->value('id')
            ?? DB::table('zonas_seguimiento')->insertGetId($datos + [
                'user_id' => $request->user()->getKey(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $destino = ['id' => $id] + ($fecha ? ['fecha' => $fecha] : []);
        if (! isset($opciones['evaluar'])) {
            return to_route('seguimiento.zona', $destino)->with('estado', 'Zona guardada.');
        }

        try {
            $this->actualizarEvaluacion($request, $id, $this->zona($request, $id), $servicio, $fecha);
        } catch (ErrorConsulta) {
            return to_route('seguimiento.zona', $destino)->with('estado', 'Zona guardada.')
                ->withErrors(['fecha' => 'NASA FIRMS no respondió. Puedes reintentar la evaluación desde esta zona.']);
        }

        return to_route('seguimiento.zona', $id)->with('estado', 'Zona guardada y evaluada.');
    }

    public function evaluar(Request $request, int $id, EvaluarZona $servicio): RedirectResponse|JsonResponse
    {
        $zona = $this->zona($request, $id);
        if ($request->expectsJson() && $request->boolean('en_vivo')) {
            try {
                $actual = $servicio->consultar($zona, null);
                $servicio->guardarVigilancia($zona, $actual);
            } catch (ErrorConsulta) {
                return response()->json(['mensaje' => 'No pudimos consultar NASA FIRMS.'], 503);
            }

            return response()->json($this->resumenVigilancia($actual));
        }

        $hoy = CarbonImmutable::now('America/La_Paz')->toDateString();
        $datos = $request->validate(['fecha' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.$hoy]]);
        try {
            $this->actualizarEvaluacion($request, $id, $zona, $servicio, $datos['fecha'] ?? null);
        } catch (ErrorConsulta) {
            return to_route('seguimiento.zona', $id)->withInput()->withErrors(['fecha' => 'No pudimos consultar NASA FIRMS. Reintenta en unos minutos. La evaluación anterior sigue disponible.']);
        }

        return to_route('seguimiento.zona', $id)->with('estado', 'Evaluación registrada.');
    }

    private function resumenVigilancia(array $resultado): array
    {
        $hora = fn (?string $fecha) => $fecha === null ? null
            : CarbonImmutable::parse($fecha)->setTimezone('America/La_Paz')->format('d/m/Y H:i');
        $foco = $resultado['focoCercano'] ?? null;

        return [
            'detecciones' => $resultado['detecciones'],
            'cobertura' => $resultado['cobertura'],
            'hasta' => $hora($resultado['hasta']),
            'consultadoEn' => $hora($resultado['consultadoEn'] ?? null),
            'fuente' => $resultado['fuente'],
            'producto' => $resultado['producto'],
            'distancia' => isset($resultado['distanciaKm']) ? number_format($resultado['distanciaKm'], 2, ',', '.') : null,
            'foco' => $foco === null ? null : [
                'latitud' => $foco['latitud'],
                'longitud' => $foco['longitud'],
                'hora' => $hora($foco['observadoEn']),
            ],
        ];
    }

    private function actualizarEvaluacion(Request $request, int $id, object $zona, EvaluarZona $servicio, ?string $fecha): void
    {
        $evaluacion = $servicio->consultar($zona, $fecha);
        $this->zonas($request)->where('id', $id)->update([
            'evaluacion' => json_encode($evaluacion, JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);
    }

    public function crearTarea(Request $request, int $id): RedirectResponse
    {
        $zona = $this->zona($request, $id);
        abort_if($zona->evaluacion === null, 409, 'Evalúa la zona antes de crear una tarea.');
        $datos = $request->validate([
            'titulo' => ['required', 'string', 'max:140'],
            'version_evaluacion' => ['required', 'string', 'size:64'],
        ]);

        if (! hash_equals(hash('sha256', $zona->evaluacion), $datos['version_evaluacion'])) {
            return to_route('seguimiento.zona', $id)
                ->withInput()
                ->withErrors(['titulo' => 'La evaluación cambió. Revisa los datos actualizados antes de crear la tarea.']);
        }

        DB::table('tareas_seguimiento')->insert([
            'zona_id' => $id,
            'titulo' => $datos['titulo'],
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
