<?php

namespace App\Http\Controllers;

use App\Http\Requests\Datos\ImportacionRequest;
use App\Http\Requests\Datos\PuntoRequest;
use App\Models\PuntoAmbiental;
use App\Services\Api\ErrorConsulta;
use App\Services\Datos\CatalogoAmbiental;
use App\Services\Datos\ConsultaHistorial;
use App\Services\Datos\ImportarCsv;
use App\Services\Datos\ImportarHistorial;
use App\Services\Datos\RegistrarLecturas;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SensoresController extends Controller
{
    public function index(Request $request, CatalogoAmbiental $catalogo, ConsultaHistorial $historial)
    {
        $datos = $request->validate(['punto' => ['nullable', 'string', 'max:60'], 'pais' => ['nullable', Rule::in(array_column($catalogo->paises(), 'codigo'))],
            'metrica' => ['nullable', Rule::in(array_keys($catalogo->metricas()))], 'origen' => ['nullable', Rule::in(array_keys(CatalogoAmbiental::ORIGENES))],
            'desde' => ['nullable', 'string'], 'hasta' => ['nullable', 'string'], 'pagina' => ['sometimes', 'integer', 'min:1', 'max:100000']]);
        $puntos = PuntoAmbiental::where('activo', true)->when($datos['pais'] ?? null, fn ($q, $pais) => $q->where('pais', $pais))->orderBy('nombre')->get();
        $punto = isset($datos['punto']) ? PuntoAmbiental::where('codigo', $datos['punto'])->firstOrFail() : ($puntos->firstWhere('codigo', 'santacruz') ?? $puntos->first());
        if ($punto && filled($datos['pais'] ?? null) && $punto->pais !== $datos['pais']) {
            $punto = $puntos->first();
        }
        $resultado = null;
        try {
            $filtros = ['metrica' => $datos['metrica'] ?? 'temperatura', 'pagina' => $datos['pagina'] ?? 1];
            if (filled($datos['origen'] ?? null)) {
                $filtros['origen'] = $datos['origen'];
            }
            foreach (['desde', 'hasta'] as $campo) {
                if (filled($datos[$campo] ?? null)) {
                    $filtros[$campo] = $this->fechaLocal($datos[$campo]);
                }
            }
            if ($punto) {
                $resultado = $historial->ejecutar($punto, $filtros);
            }
        } catch (ErrorConsulta $e) {
            return redirect()->route('sensores')->withErrors(['periodo' => $e->getMessage()]);
        }

        return view('datos.index', ['modulos' => array_filter(config('geocruz.modulos'), fn ($m) => $m['habilitado']),
            'puntos' => $puntos, 'punto' => $punto, 'resultado' => $resultado, 'metricas' => $catalogo->metricas(), 'paises' => $catalogo->paises(), 'origenes' => CatalogoAmbiental::ORIGENES,
            'datosMapa' => ['puntos' => $puntos->map->only(['codigo', 'nombre', 'latitud', 'longitud'])->values(), 'seleccionado' => $punto?->only(['codigo', 'nombre', 'latitud', 'longitud']), 'horas' => $resultado['horas'] ?? [], 'metrica' => $datos['metrica'] ?? 'temperatura', 'unidad' => $catalogo->metricas()[$datos['metrica'] ?? 'temperatura']['unidad'], 'origenes' => CatalogoAmbiental::ORIGENES],
            'metrica' => $datos['metrica'] ?? 'temperatura', 'desde' => CarbonImmutable::parse($resultado['desde'] ?? now()->subDays(7))->setTimezone('America/La_Paz')->format('Y-m-d\TH:i'),
            'hasta' => CarbonImmutable::parse($resultado['hasta'] ?? now())->setTimezone('America/La_Paz')->format('Y-m-d\TH:i')]);
    }

    public function guardar(PuntoRequest $request)
    {
        $punto = PuntoAmbiental::create($request->validated() + ['codigo' => 'p-'.Str::lower((string) Str::ulid()), 'tipo' => 'manual']);

        return redirect()->route('sensores', ['punto' => $punto->codigo])->with('estado', 'Punto guardado.');
    }

    public function registrar(Request $request, PuntoAmbiental $punto, RegistrarLecturas $registrar)
    {
        $datos = $request->validate(['metrica' => ['required', 'string'], 'valor' => ['required', 'numeric'], 'instante' => ['required', 'string'], 'origen' => ['required', 'string']]);
        try {
            $registrar->ejecutar([['punto' => $punto->codigo, 'metrica' => $datos['metrica'], 'valor' => $datos['valor'], 'instante' => $this->fechaLocal($datos['instante']), 'origen' => $datos['origen'], 'fuente' => 'manual']], $request->user()->id);
        } catch (ErrorConsulta $e) {
            return back()->withErrors(['lectura' => $e->getMessage()])->withInput();
        }

        return redirect()->route('sensores', ['punto' => $punto->codigo, 'metrica' => $datos['metrica']])->with('estado', 'Lectura guardada.');
    }

    public function importar(ImportacionRequest $request, ImportarCsv $importar, ImportarHistorial $historial)
    {
        try {
            $resultado = $request->input('formato', 'csv') === 'historial'
                ? $historial->ejecutar($request->file('archivo')->getRealPath(), $request->user()->id)
                : $importar->ejecutar($request->file('archivo')->getRealPath(), $request->user()->id);
        } catch (ErrorConsulta $e) {
            return back()->withErrors(['archivo' => $e->getMessage()]);
        } catch (ValidationException $e) {
            return back()->withErrors(['archivo' => 'No se importó el archivo. Revisa puntos, variables, unidades, valores y procedencia.']);
        }

        return back()->with('estado', 'Importación completa: '.$resultado['nuevas'].' nuevas y '.$resultado['repetidas'].' repetidas.');
    }

    public function estado(Request $request, PuntoAmbiental $punto)
    {
        $datos = $request->validate(['activo' => ['required', 'boolean']]);
        $punto->forceFill(['activo' => (bool) $datos['activo']])->save();

        return redirect()->route('sensores', ['punto' => $punto->codigo])->with('estado', $punto->activo ? 'Punto reactivado.' : 'Punto archivado. Su historial se conserva.');
    }

    private function fechaLocal(string $texto): string
    {
        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $texto, new DateTimeZone('America/La_Paz'));
        if (! $fecha || $fecha->format('Y-m-d\TH:i') !== $texto) {
            throw new ErrorConsulta('FILTROS_INVALIDOS', 'Revisa las fechas elegidas.');
        }

        return $fecha->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }
}
