<?php

namespace App\Services\Reportes;

use App\Models\LecturaAmbiental;
use App\Models\PlantillaReporte;
use App\Models\ReporteAmbiental;
use App\Models\Simulacion;
use App\Services\Api\ErrorConsulta;
use App\Services\Api\ParametrosConsulta;
use App\Services\Datos\CatalogoAmbiental;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class PrepararReporte
{
    public const COLUMNAS = ['fecha' => 'Fecha y hora · Bolivia', 'punto' => 'Zona', 'pais' => 'País', 'variable' => 'Variable', 'valor' => 'Valor', 'unidad' => 'Unidad', 'origen' => 'Origen', 'fuente' => 'Fuente', 'latitud' => 'Latitud', 'longitud' => 'Longitud'];

    public const PREDETERMINADAS = ['fecha', 'punto', 'variable', 'valor', 'unidad', 'origen', 'fuente'];

    public const TIPOS = ['PERSONALIZADA' => 'Personalizada', 'ESTANDAR_LOGISTICA' => 'Logística', 'ESTANDAR_SALUD' => 'Salud'];

    public function __construct(private CatalogoAmbiental $catalogo) {}

    public function plantilla(array $entrada, int $usuario, ?PlantillaReporte $plantilla = null): PlantillaReporte
    {
        $datos = Validator::make($entrada, ['nombre' => ['required', 'string', 'max:100'], 'columnas' => ['required', 'array', 'list', 'min:1', 'max:10'],
            'columnas.*' => ['required', 'distinct', Rule::in(array_keys(self::COLUMNAS))], 'grafico' => ['required', 'boolean'],
            'tipo' => ['sometimes', Rule::in(array_keys(self::TIPOS))], 'condiciones' => ['sometimes', 'array', 'list', 'max:10'],
            'condiciones.*' => ['array:variable,operador,valor'],
            'condiciones.*.variable' => ['required', Rule::in(array_keys($this->catalogo->metricas()))],
            'condiciones.*.operador' => ['required', Rule::in(['>', '<', '='])],
            'condiciones.*.valor' => ['required', 'numeric', 'between:-1000000,1000000']])->validate();
        $config = ['columnas' => $datos['columnas'], 'grafico' => (bool) $datos['grafico'], 'tipo' => $datos['tipo'] ?? 'PERSONALIZADA',
            'condiciones' => $datos['condiciones'] ?? []];
        if ($plantilla) {
            abort_unless($plantilla->usuario_id === $usuario, 404);
            $plantilla->update(['nombre' => $datos['nombre'], 'configuracion' => $config]);

            return $plantilla;
        }

        return PlantillaReporte::create(['usuario_id' => $usuario, 'nombre' => $datos['nombre'], 'configuracion' => $config]);
    }

    public function crear(array $entrada, int $usuario): ReporteAmbiental
    {
        Validator::make(['entrada' => $entrada], ['entrada' => ['array:titulo,base,simulacion,pais,punto,metrica,origen,desde,hasta,plantilla']])->validate();
        $datos = Validator::make($entrada, ['titulo' => ['required', 'string', 'max:100'], 'base' => ['required', Rule::in(['mediciones', 'simulacion'])],
            'simulacion' => ['sometimes', 'string', 'max:40'], 'pais' => ['sometimes', Rule::in(array_column($this->catalogo->paises(), 'codigo'))],
            'punto' => ['sometimes', 'string', 'max:60'], 'metrica' => ['sometimes', Rule::in(array_keys($this->catalogo->metricas()))],
            'origen' => ['sometimes', Rule::in([...array_keys(CatalogoAmbiental::ORIGENES), 'todos'])],
            'desde' => ['sometimes', 'string'], 'hasta' => ['sometimes', 'string'], 'plantilla' => ['sometimes', 'string', 'max:40']])->validate();
        $config = isset($datos['plantilla']) ? PlantillaReporte::where('usuario_id', $usuario)->findOrFail($datos['plantilla'])->configuracion :
            ['columnas' => self::PREDETERMINADAS, 'grafico' => true];
        if ($datos['base'] === 'simulacion') {
            if (! isset($datos['simulacion'])) {
                throw new ErrorConsulta('FILTROS_INVALIDOS', 'Elige una simulación propia.');
            }
            $s = Simulacion::where('usuario_id', $usuario)->findOrFail($datos['simulacion']);
            $filas = [];
            foreach ($s->resultado['series'] as $serie) {
                foreach ($serie['valores'] as $i => $valor) {
                    $filas[] = $this->fila(['instante' => $s->resultado['instantes'][$i], 'puntoCodigo' => $serie['codigo'], 'punto' => $serie['nombre'], 'pais' => $serie['pais'], 'metrica' => $s->configuracion['metrica'],
                        'valor' => $valor, 'origen' => 'simulado', 'fuente' => 'Simulación: '.$s->nombre, 'latitud' => $serie['latitud'], 'longitud' => $serie['longitud']]);
                }
            }
            $instantes = $s->resultado['instantes'];
            $datos = ['titulo' => $datos['titulo'], 'base' => 'simulacion', 'simulacion' => $s->id, 'desde' => $instantes[0], 'hasta' => $instantes[array_key_last($instantes)]];
        } else {
            $hasta = isset($datos['hasta']) ? ParametrosConsulta::instante($datos['hasta']) : now('UTC')->toDateTimeImmutable();
            $desde = isset($datos['desde']) ? ParametrosConsulta::instante($datos['desde']) : $hasta->modify('-7 days');
            if ($desde >= $hasta || $hasta->getTimestamp() > now()->timestamp || $hasta->getTimestamp() - $desde->getTimestamp() > 366 * 86400) {
                throw new ErrorConsulta('FILTROS_INVALIDOS', 'Elige hasta un año de registros, sin fechas futuras.');
            }
            $consulta = LecturaAmbiental::query()->join('puntos_ambientales as p', 'p.id', '=', 'lecturas_ambientales.punto_id')
                ->where('instante', '>=', $desde->format('Y-m-d H:i:s'))->where('instante', '<', $hasta->format('Y-m-d H:i:s'));
            foreach (['pais' => 'p.pais', 'punto' => 'p.codigo'] as $campo => $columna) {
                if (isset($datos[$campo])) {
                    $consulta->where($columna, $datos[$campo]);
                }
            }
            if (! isset($datos['origen'])) {
                $consulta->whereIn('origen', ['observado', 'estimado']);
            } elseif ($datos['origen'] !== 'todos') {
                $consulta->where('origen', $datos['origen']);
            }
            if (isset($datos['metrica']) && empty($config['condiciones'])) {
                $consulta->where('metrica', $datos['metrica']);
            }
            $lecturas = $consulta->select('lecturas_ambientales.*', 'p.nombre as nombre_punto', 'p.codigo as codigo_punto', 'p.pais')->orderBy('instante')->orderBy('lecturas_ambientales.id')->limit(1001)->get();
            $filas = $lecturas->map(fn ($l) => $this->fila(['instante' => $l->instante->toIso8601ZuluString(), 'puntoCodigo' => $l->codigo_punto, 'punto' => $l->nombre_punto, 'pais' => $l->pais, 'metrica' => $l->metrica,
                'valor' => $l->valor, 'origen' => $l->origen, 'fuente' => $l->fuente, 'latitud' => $l->latitud, 'longitud' => $l->longitud]))->all();
            $datos['desde'] = ParametrosConsulta::utc($desde);
            $datos['hasta'] = ParametrosConsulta::utc($hasta);
        }
        if (count($filas) > 1000) {
            throw new ErrorConsulta('FILTROS_INVALIDOS', 'El reporte supera las mil lecturas. Reduce las fechas, zonas o variables.');
        }
        $filas = FiltrarPlantilla::aplicar($filas, $config['condiciones'] ?? []);
        if (isset($datos['metrica'])) {
            $filas = array_values(array_filter($filas, fn ($f) => $f['metrica'] === $datos['metrica']));
        }
        $resumen = collect($filas)->groupBy(fn ($f) => json_encode([$f['puntoCodigo'], $f['metrica'], $f['origen'], $f['fuente']]))->map(function ($grupo) {
            $f = $grupo->first();

            return array_intersect_key($f, array_flip(['punto', 'variable', 'metrica', 'unidad', 'origen', 'fuente', 'origenNombre', 'fuenteNombre'])) +
                ['cantidad' => $grupo->count(), 'minimo' => $grupo->min('valor'), 'maximo' => $grupo->max('valor'), 'promedio' => round($grupo->avg('valor'), 4)];
        })->values()->all();

        return ReporteAmbiental::create(['usuario_id' => $usuario, 'titulo' => $datos['titulo'], 'parametros' => $datos,
            'contenido' => ['columnas' => $config['columnas'], 'grafico' => $config['grafico'], 'plantilla' => $config, 'filas' => $filas, 'resumen' => $resumen,
                'nota' => 'Registrados, estimados y simulados conservan su origen. Este reporte no certifica cobertura continua del territorio.']]);
    }

    private function fila(array $dato): array
    {
        $metrica = $this->catalogo->metricas()[$dato['metrica']];

        return $dato + ['fecha' => (new \DateTimeImmutable($dato['instante']))->setTimezone(new \DateTimeZone('America/La_Paz'))->format('d/m/Y H:i'),
            'origenNombre' => CatalogoAmbiental::ORIGENES[$dato['origen']],
            'fuenteNombre' => str_starts_with($dato['fuente'], 'sim-') ? 'Simulación guardada' : (['demo-local' => 'Demostración', 'manual' => 'Carga manual', 'open-meteo-modelo' => 'Open-Meteo'][$dato['fuente']] ?? $dato['fuente']),
            'variable' => $metrica['nombre'], 'unidad' => $metrica['unidad']];
    }

    public static function celda(array $fila, string $columna): mixed
    {
        return match ($columna) {
            'origen' => $fila['origenNombre'],'fuente' => $fila['fuenteNombre'],default => $fila[$columna]
        };
    }
}
