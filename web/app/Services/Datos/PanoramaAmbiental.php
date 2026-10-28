<?php

namespace App\Services\Datos;

use App\Models\LecturaAmbiental;
use App\Models\PuntoAmbiental;
use App\Services\Api\ErrorConsulta;
use App\Services\Api\ParametrosConsulta;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class PanoramaAmbiental
{
    public function __construct(private CatalogoAmbiental $catalogo) {}

    public function consultar(array $filtros): array
    {
        $datos = Validator::make($filtros, [
            'pais' => ['sometimes', Rule::in(array_column($this->catalogo->paises(), 'codigo'))],
            'metrica' => ['sometimes', Rule::in(array_keys($this->catalogo->metricas()))],
            'origen' => ['sometimes', Rule::in([...array_keys(CatalogoAmbiental::ORIGENES), 'todos'])],
            'desde' => ['sometimes', 'string'], 'hasta' => ['sometimes', 'string'],
            'pagina' => ['sometimes', 'integer', 'min:1', 'max:100000'],
        ])->validate();
        $hasta = isset($datos['hasta']) ? ParametrosConsulta::instante($datos['hasta']) : now('UTC')->toDateTimeImmutable();
        $desde = isset($datos['desde']) ? ParametrosConsulta::instante($datos['desde']) : $hasta->modify('-7 days');
        if ($desde >= $hasta || $hasta->getTimestamp() > now()->timestamp || $hasta->getTimestamp() - $desde->getTimestamp() > 7 * 86400) {
            throw new ErrorConsulta('FILTROS_INVALIDOS', 'Elige hasta siete días, sin fechas futuras.');
        }
        $metrica = $datos['metrica'] ?? 'temperatura';
        $pagina = PuntoAmbiental::where('activo', true)
            ->when(isset($datos['pais']), fn ($consulta) => $consulta->where('pais', $datos['pais']))
            ->orderBy('codigo')->paginate(250, ['*'], 'pagina', $datos['pagina'] ?? 1);
        $lecturas = LecturaAmbiental::whereIn('punto_id', $pagina->getCollection()->pluck('id'))
            ->where('metrica', $metrica)
            ->where('instante', '>=', $desde->format('Y-m-d H:i:s'))
            ->where('instante', '<', $hasta->format('Y-m-d H:i:s'));
        if (! isset($datos['origen'])) {
            $lecturas->whereIn('origen', ['observado', 'estimado']);
        } elseif ($datos['origen'] !== 'todos') {
            $lecturas->where('origen', $datos['origen']);
        }

        // La última lectura se elige dentro del período y de cada fuente/origen.
        // No se reemplaza una observación por una simulación ni se promedian ambas.
        $lecturas->whereNotExists(function ($posteriores) use ($hasta): void {
            $posteriores->selectRaw('1')->from('lecturas_ambientales as posterior')
                ->whereColumn('posterior.punto_id', 'lecturas_ambientales.punto_id')
                ->whereColumn('posterior.metrica', 'lecturas_ambientales.metrica')
                ->whereColumn('posterior.origen', 'lecturas_ambientales.origen')
                ->whereColumn('posterior.fuente', 'lecturas_ambientales.fuente')
                ->where('posterior.instante', '<', $hasta->format('Y-m-d H:i:s'))
                ->where(function ($masReciente): void {
                    $masReciente->whereColumn('posterior.instante', '>', 'lecturas_ambientales.instante')
                        ->orWhere(function ($mismoInstante): void {
                            $mismoInstante->whereColumn('posterior.instante', 'lecturas_ambientales.instante')
                                ->whereColumn('posterior.id', '>', 'lecturas_ambientales.id');
                        });
                });
        });
        $ultimas = $lecturas->orderByDesc('instante')->orderByDesc('id')->limit(5001)->get();
        if ($ultimas->count() > 5000) {
            throw new ErrorConsulta('FILTROS_INVALIDOS', 'Elige un país y un origen para reducir los resultados.');
        }
        $porPunto = $ultimas->groupBy('punto_id');

        return [
            'metrica' => $metrica, 'unidad' => $this->catalogo->metricas()[$metrica]['unidad'],
            'desde' => ParametrosConsulta::utc($desde), 'hasta' => ParametrosConsulta::utc($hasta),
            'origen' => $datos['origen'] ?? 'sin-simulaciones',
            'datos' => $pagina->getCollection()->map(function (PuntoAmbiental $punto) use ($porPunto): array {
                return $punto->only(['codigo', 'nombre', 'pais', 'latitud', 'longitud', 'tipo']) + [
                    'lecturas' => ($porPunto->get($punto->id) ?? collect())->map(fn (LecturaAmbiental $lectura) => [
                        'valor' => $lectura->valor, 'instante' => $lectura->instante->toIso8601ZuluString(),
                        'origen' => $lectura->origen, 'fuente' => $lectura->fuente,
                        'latitud' => $lectura->latitud, 'longitud' => $lectura->longitud,
                    ])->values()->all(),
                ];
            })->values()->all(),
            'pagina' => $pagina->currentPage(), 'ultimaPagina' => $pagina->lastPage(), 'total' => $pagina->total(),
            'aviso' => 'Los puntos de referencia no acreditan estaciones físicas ni cobertura continua.',
        ];
    }
}
