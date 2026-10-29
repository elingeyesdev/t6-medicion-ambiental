<?php

namespace App\Services\Simulaciones;

use App\Models\PuntoAmbiental;
use App\Models\Simulacion;
use App\Services\Api\ErrorConsulta;
use App\Services\Api\ParametrosConsulta;
use App\Services\Datos\CatalogoAmbiental;
use App\Services\Datos\RegistrarLecturas;
use App\Services\Territorios\GeometriaTerritorial;
use App\Services\Territorios\RegionesEnvirosense;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class SimularEscenario
{
    public function __construct(private CatalogoAmbiental $catalogo, private RegionesEnvirosense $regiones, private GeometriaTerritorial $geometria) {}

    public function escenarios(): array
    {
        return json_decode(file_get_contents(base_path('../compartido/simulaciones/escenarios-envirosense.json')), true, 512, JSON_THROW_ON_ERROR)['metricas'];
    }

    public function crear(array $entrada, int $usuario): Simulacion
    {
        Validator::make(['entrada' => $entrada], ['entrada' => ['array:nombre,metrica,inicio,dias,intervalo,semilla,zonas']])->validate();
        $datos = Validator::make($entrada, [
            'nombre' => ['required', 'string', 'max:100'], 'metrica' => ['required', Rule::in(array_keys($this->escenarios()))],
            'inicio' => ['required', 'string'], 'dias' => ['required', 'integer', 'between:1,30'],
            'intervalo' => ['required', 'integer', 'between:1,1440'], 'semilla' => ['required', 'string', 'max:40', 'regex:/^[a-zA-Z0-9_-]+$/D'],
            'zonas' => ['required', 'array', 'list', 'min:1', 'max:10'], 'zonas.*' => ['array:punto,region,escenario,nivel,inicio,fin,curva'],
            'zonas.*.punto' => ['sometimes', 'string', 'max:60'], 'zonas.*.region' => ['sometimes', 'string', 'max:80'],
            'zonas.*.escenario' => ['required', 'string', 'max:40'], 'zonas.*.nivel' => ['required', Rule::in(['bajo', 'medio', 'alto'])],
            'zonas.*.inicio' => ['sometimes', 'numeric'], 'zonas.*.fin' => ['sometimes', 'numeric'],
            'zonas.*.curva' => ['sometimes', Rule::in(['lineal', 'pico', 'exponencial'])],
        ])->validate();
        $inicio = ParametrosConsulta::instante($datos['inicio']);
        $cantidad = intdiv($datos['dias'] * 1440, $datos['intervalo']) + 1;
        if ($cantidad > 2000 || $cantidad * count($datos['zonas']) > 10000) {
            throw new ErrorConsulta('FILTROS_INVALIDOS', 'Aumenta el intervalo o reduce los días y las zonas.');
        }
        $limites = $this->catalogo->metricas()[$datos['metrica']];
        $escenarios = collect($this->escenarios()[$datos['metrica']])->keyBy('id');
        $instantes = [];
        for ($i = 0; $i < $cantidad; $i++) {
            $instantes[] = $inicio->modify('+'.($i * $datos['intervalo']).' minutes')->format('Y-m-d\TH:i:s\Z');
        }
        $series = [];
        foreach ($datos['zonas'] as $zona) {
            if (isset($zona['punto']) === isset($zona['region'])) {
                throw new ErrorConsulta('FILTROS_INVALIDOS', 'Elige un punto o una región por zona.');
            }
            $escenario = $escenarios->get($zona['escenario']);
            if (! $escenario && $zona['escenario'] === 'personalizado' && isset($zona['inicio'],$zona['fin'],$zona['curva'])) {
                $escenario = ['nombre' => 'Personalizado', 'curva' => $zona['curva'], 'niveles' => [$zona['nivel'] => ['inicio' => $zona['inicio'], 'fin' => $zona['fin']]]];
            }
            if (! $escenario) {
                throw new ErrorConsulta('FILTROS_INVALIDOS', 'El escenario no corresponde a esta variable.');
            }
            if (isset($zona['region'])) {
                $feature = $this->regiones->limite($zona['region']);
                $prop = $feature['properties'];
                $g = $feature['geometry'];
                [$o,$s,$e,$n] = $prop['caja'];
                $lon = ($o + $e) / 2;
                $lat = ($s + $n) / 2;
                if (! $this->geometria->contiene($g, $lon, $lat)) {
                    [$lon,$lat] = $g['type'] === 'Polygon' ? $g['coordinates'][0][0] : $g['coordinates'][0][0][0];
                }
                $punto = ['codigo' => 'zona-'.$zona['region'], 'nombre' => $prop['nombre'], 'pais' => $prop['pais'], 'latitud' => $lat, 'longitud' => $lon, 'region' => $zona['region']];
            } else {
                $modelo = PuntoAmbiental::where('codigo', $zona['punto'])->where('activo', true)->first();
                $punto = $modelo?->only(['codigo', 'nombre', 'pais', 'latitud', 'longitud']);
                if (! $punto) {
                    $punto = collect($this->catalogo->leer()['puntos'])->firstWhere('codigo', $zona['punto']);
                }
                if (! $punto || ($modelo === null && PuntoAmbiental::where('codigo', $zona['punto'])->exists())) {
                    throw new ErrorConsulta('FILTROS_INVALIDOS', 'Elige un punto activo del catálogo.');
                }
                $punto = array_intersect_key($punto, array_flip(['codigo', 'nombre', 'pais', 'latitud', 'longitud']));
            }
            if (collect($series)->contains('codigo', $punto['codigo'])) {
                throw new ErrorConsulta('FILTROS_INVALIDOS', 'No repitas una zona en la misma comparación.');
            }
            $rango = $escenario['niveles'][$zona['nivel']];
            $valores = [];
            foreach (['inicio', 'fin'] as $campo) {
                if (isset($zona[$campo])) {
                    $rango[$campo] = (float) $zona[$campo];
                }
                if ($rango[$campo] < $limites['minimo'] || $rango[$campo] > $limites['maximo']) {
                    throw new ErrorConsulta('FILTROS_INVALIDOS', 'Los valores deben respetar los límites de la variable.');
                }
            }
            if (isset($zona['curva'])) {
                $escenario['curva'] = $zona['curva'];
            }
            for ($i = 0; $i < $cantidad; $i++) {
                $t = $i / max(1, $cantidad - 1);
                $progreso = match ($escenario['curva']) {
                    'exponencial' => $t * $t,'pico' => $t <= 0.4 ? $t / 0.4 : 1 - ($t - 0.4) / 0.6,default => $t
                };
                $base = $rango['inicio'] + ($rango['fin'] - $rango['inicio']) * $progreso;
                $u1 = max(1e-12, $this->uniforme($datos['semilla'].':'.$punto['codigo'].':'.$i.':a'));
                $u2 = $this->uniforme($datos['semilla'].':'.$punto['codigo'].':'.$i.':b');
                $ruido = sqrt(-2 * log($u1)) * cos(2 * M_PI * $u2) * abs($rango['fin'] - $rango['inicio']) * 0.04;
                $valores[] = round(max($limites['minimo'], min($limites['maximo'], $base + $ruido)), 2);
            }
            $series[] = $punto + ['escenario' => $escenario['nombre'], 'nivel' => $zona['nivel'], 'curva' => $escenario['curva'], 'inicio' => $rango['inicio'], 'fin' => $rango['fin'], 'valores' => $valores];
        }

        return Simulacion::create(['usuario_id' => $usuario, 'nombre' => $datos['nombre'], 'configuracion' => $datos,
            'resultado' => ['origen' => 'simulado', 'unidad' => $limites['unidad'], 'instantes' => $instantes, 'series' => $series,
                'metodo' => 'envirosense-curvas-v1', 'aviso' => 'Escenario hipotético. No es una observación ni una predicción ambiental.'],
            'estado' => 'lista', 'posicion' => 0, 'revision' => 0]);
    }

    private function uniforme(string $clave): float
    {
        return hexdec(substr(hash('sha256', $clave), 0, 13)) / 4503599627370495;
    }

    public function accion(string $id, int $usuario, array $datos): Simulacion
    {
        $datos = Validator::make($datos, ['accion' => ['required', Rule::in(['iniciar', 'pausar', 'detener', 'reiniciar', 'finalizar'])],
            'revision' => ['required', 'integer', 'min:0'], 'posicion' => ['required', 'integer', 'min:0']])->validate();

        return DB::transaction(function () use ($id, $usuario, $datos) {
            $sesion = Simulacion::whereKey($id)->where('usuario_id', $usuario)->lockForUpdate()->firstOrFail();
            if ($sesion->revision !== (int) $datos['revision']) {
                throw new ErrorConsulta('SESION_CAMBIADA', 'La sesión cambió en otra pantalla. Vuelve a abrirla.', 409);
            }
            $ultima = count($sesion->resultado['instantes']) - 1;
            if ($datos['posicion'] > $ultima) {
                throw new ErrorConsulta('FILTROS_INVALIDOS', 'La posición está fuera de la simulación.');
            }
            if (in_array($datos['accion'], ['pausar', 'finalizar']) && $sesion->estado !== 'activa') {
                throw new ErrorConsulta('ESTADO_INVALIDO', 'Primero inicia la reproducción.', 409);
            }
            if ($datos['accion'] === 'finalizar' && (int) $datos['posicion'] !== $ultima) {
                throw new ErrorConsulta('ESTADO_INVALIDO', 'La reproducción todavía no llegó al final.', 409);
            }
            $sesion->estado = match ($datos['accion']) {
                'iniciar' => 'activa','pausar' => 'pausada','detener' => 'detenida','reiniciar' => 'lista','finalizar' => 'finalizada'
            };
            $sesion->posicion = $datos['accion'] === 'reiniciar' ? 0 : $datos['posicion'];
            $sesion->revision++;
            $sesion->save();

            return $sesion;
        });
    }

    public function publicar(Simulacion $sesion, int $usuario, RegistrarLecturas $registrar): array
    {
        abort_unless($sesion->usuario_id === $usuario, 403);

        return DB::transaction(function () use ($sesion, $usuario, $registrar) {
            $filas = [];
            $futuras = 0;
            foreach ($sesion->resultado['series'] as $serie) {
                $codigo = isset($serie['region']) ? 'sim-'.strtolower($sesion->id).'-'.substr(hash('sha256', $serie['codigo']), 0, 8) : $serie['codigo'];
                if (strtotime($sesion->resultado['instantes'][0]) > now()->timestamp) {
                    $futuras += count($serie['valores']);

                    continue;
                }
                $punto = PuntoAmbiental::firstOrCreate(['codigo' => $codigo], array_intersect_key($serie, array_flip(['nombre', 'pais', 'latitud', 'longitud'])) + ['tipo' => 'referencia']);
                $punto = PuntoAmbiental::whereKey($punto->id)->lockForUpdate()->firstOrFail();
                if (! $punto->activo || abs($punto->latitud - $serie['latitud']) > 0.000001 || abs($punto->longitud - $serie['longitud']) > 0.000001) {
                    throw new ErrorConsulta('PUNTO_CAMBIADO', 'El punto cambió de ubicación o fue archivado. La simulación se conserva sin alterar su historial.', 409);
                }
                foreach ($serie['valores'] as $i => $valor) {
                    $instante = $sesion->resultado['instantes'][$i];
                    if (strtotime($instante) > now()->timestamp) {
                        $futuras++;

                        continue;
                    }
                    $filas[] = ['punto' => $codigo, 'metrica' => $sesion->configuracion['metrica'], 'valor' => $valor, 'instante' => $instante, 'origen' => 'simulado', 'fuente' => 'sim-'.strtolower($sesion->id)];
                }
            }
            $nuevas = 0;
            $repetidas = 0;
            foreach (array_chunk($filas, 1000) as $bloque) {
                $r = $registrar->ejecutar($bloque, $usuario);
                $nuevas += $r['nuevas'];
                $repetidas += $r['repetidas'];
            }

            return compact('nuevas', 'repetidas', 'futuras');
        });
    }
}
