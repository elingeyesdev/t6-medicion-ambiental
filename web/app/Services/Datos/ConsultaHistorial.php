<?php

namespace App\Services\Datos;

use App\Models\LecturaAmbiental;
use App\Models\PuntoAmbiental;
use App\Services\Api\ErrorConsulta;
use App\Services\Api\ParametrosConsulta;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class ConsultaHistorial
{
    public function __construct(private CatalogoAmbiental $catalogo) {}

    public function ejecutar(PuntoAmbiental $punto, array $filtros): array
    {
        $datos = Validator::make($filtros, ['desde' => ['sometimes', 'string'], 'hasta' => ['sometimes', 'string'],
            'metrica' => ['sometimes', Rule::in(array_keys($this->catalogo->metricas()))], 'origen' => ['sometimes', Rule::in(array_keys(CatalogoAmbiental::ORIGENES))],
            'pagina' => ['sometimes', 'integer', 'min:1', 'max:100000'], 'fuente' => ['sometimes', 'string', 'max:80']])->validate();
        $hasta = isset($datos['hasta']) ? ParametrosConsulta::instante($datos['hasta']) : now('UTC')->toDateTimeImmutable();
        $desde = isset($datos['desde']) ? ParametrosConsulta::instante($datos['desde']) : $hasta->modify('-7 days');
        if ($desde >= $hasta || $hasta->getTimestamp() > now()->timestamp || $hasta->getTimestamp() - $desde->getTimestamp() > 7 * 86400) {
            throw new ErrorConsulta('FILTROS_INVALIDOS', 'Elige un período de hasta siete días, sin fechas futuras.');
        }
        $consulta = $punto->lecturas()->where('instante', '>=', $desde->format('Y-m-d H:i:s'))->where('instante', '<', $hasta->format('Y-m-d H:i:s'));
        foreach (['metrica', 'origen', 'fuente'] as $filtro) {
            if (isset($datos[$filtro])) {
                $consulta->where($filtro, $datos[$filtro]);
            }
        }
        $pagina = (clone $consulta)->orderByDesc('instante')->orderByDesc('id')->paginate(50, ['*'], 'pagina', $datos['pagina'] ?? 1);
        $resumen = (clone $consulta)->selectRaw('metrica, origen, fuente, COUNT(*) AS cantidad, MIN(valor) AS minimo, MAX(valor) AS maximo, AVG(valor) AS media')->groupBy('metrica', 'origen', 'fuente')->orderBy('metrica')->orderBy('origen')->orderBy('fuente')->limit(101)->get();
        $horas = (clone $consulta)->selectRaw('hora_utc, metrica, origen, fuente, AVG(valor) AS media')->groupBy('hora_utc', 'metrica', 'origen', 'fuente')->orderBy('hora_utc')->orderBy('metrica')->orderBy('origen')->orderBy('fuente')->limit(2001)->get();
        if ($resumen->count() > 100 || $horas->count() > 2000) {
            throw new ErrorConsulta('FILTROS_INVALIDOS', 'Acota el período, la variable o la fuente para ver los resultados.');
        }

        return ['punto' => $punto->only(['codigo', 'nombre', 'pais', 'latitud', 'longitud', 'tipo', 'activo']),
            'desde' => ParametrosConsulta::utc($desde), 'hasta' => ParametrosConsulta::utc($hasta),
            'datos' => $pagina->getCollection()->map(fn (LecturaAmbiental $l) => ['id' => $l->id, 'metrica' => $l->metrica, 'unidad' => $this->catalogo->metricas()[$l->metrica]['unidad'],
                'valor' => $l->valor, 'instante' => $l->instante->toIso8601ZuluString(), 'origen' => $l->origen, 'fuente' => $l->fuente, 'latitud' => $l->latitud, 'longitud' => $l->longitud])->all(),
            'resumen' => $resumen->map(fn ($r) => ['metrica' => $r->metrica, 'origen' => $r->origen, 'fuente' => $r->fuente, 'cantidad' => (int) $r->cantidad, 'minimo' => (float) $r->minimo, 'maximo' => (float) $r->maximo, 'media' => round((float) $r->media, 4)])->all(),
            'horas' => $horas->map(fn ($h) => ['instante' => $h->hora_utc->toIso8601ZuluString(), 'metrica' => $h->metrica, 'origen' => $h->origen, 'fuente' => $h->fuente, 'media' => round((float) $h->media, 4)])->all(),
            'pagina' => $pagina->currentPage(), 'ultimaPagina' => $pagina->lastPage(), 'total' => $pagina->total()];
    }
}
