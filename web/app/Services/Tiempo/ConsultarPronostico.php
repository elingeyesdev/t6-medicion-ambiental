<?php

namespace App\Services\Tiempo;

use App\Services\Api\ErrorConsulta;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class ConsultarPronostico
{
    public const VARIABLES = [
        'temperatura' => ['temperature_2m', 'Temperatura', '°C', -100, 80],
        'sensacion' => ['apparent_temperature', 'Sensación térmica', '°C', -150, 100],
        'humedad' => ['relative_humidity_2m', 'Humedad', '%', 0, 100],
        'lluvia' => ['rain', 'Lluvia', 'mm', 0, 1000],
        'probabilidad' => ['precipitation_probability', 'Probabilidad de lluvia', '%', 0, 100],
        'viento' => ['wind_speed_10m', 'Viento', 'km/h', 0, 600],
        'direccion' => ['wind_direction_10m', 'Dirección del viento', '°', 0, 360],
        'visibilidad' => ['visibility', 'Visibilidad', 'm', 0, 2000000],
        'nieve' => ['snowfall', 'Nieve', 'cm', 0, 1000],
        'nubosidad' => ['cloud_cover', 'Nubosidad', '%', 0, 100],
        'presion' => ['pressure_msl', 'Presión', 'hPa', 100, 1200],
        'uv' => ['uv_index', 'Índice UV', '', 0, 40],
    ];

    private const DIARIAS = ['maxima' => ['temperature_2m_max', '°C', -100, 80], 'minima' => ['temperature_2m_min', '°C', -100, 80],
        'lluvia' => ['precipitation_sum', 'mm', 0, 2000], 'probabilidad' => ['precipitation_probability_max', '%', 0, 100],
        'viento' => ['wind_speed_10m_max', 'km/h', 0, 600], 'uv' => ['uv_index_max', '', 0, 40]];

    public function ejecutar(float $latitud, float $longitud): array
    {
        if (! is_finite($latitud) || ! is_finite($longitud) || abs($latitud) > 90 || abs($longitud) > 180) {
            throw new ErrorConsulta('FILTROS_INVALIDOS', 'Elige un lugar válido en el mapa.');
        }
        $latitud = round($latitud, 4);
        $longitud = round($longitud, 4);
        $clave = 'tiempo-envirosense-v2:'.hash('sha256', json_encode([$latitud, $longitud]));

        return Cache::remember($clave, now()->addMinutes(15), fn () => $this->consultar($latitud, $longitud));
    }

    private function consultar(float $latitud, float $longitud): array
    {
        try {
            $variables = implode(',', array_column(self::VARIABLES, 0));
            $r = Http::acceptJson()->connectTimeout(3)->timeout(12)->withOptions(['allow_redirects' => false])
                ->get('https://api.open-meteo.com/v1/forecast', ['latitude' => $latitud, 'longitude' => $longitud, 'current' => $variables,
                    'hourly' => $variables, 'daily' => implode(',', [...array_column(self::DIARIAS, 0), 'sunrise', 'sunset']),
                    'past_days' => 1, 'forecast_days' => 7, 'timezone' => 'auto', 'timeformat' => 'unixtime', 'temperature_unit' => 'celsius', 'wind_speed_unit' => 'kmh', 'precipitation_unit' => 'mm']);
            if (! $r->successful() || strlen($r->body()) > 1500000) {
                throw new \UnexpectedValueException('Respuesta no disponible.');
            }
            $datos = $r->json();
            $zona = new DateTimeZone(is_string($datos['timezone'] ?? null) ? $datos['timezone'] : '');
            if (! isset($datos['hourly']['time'], $datos['daily']['time'], $datos['current']['time'])) {
                throw new \UnexpectedValueException('Faltan series de tiempo.');
            }
            $horas = $this->instantes($datos['hourly']['time'], 200);
            $dias = $this->instantes($datos['daily']['time'], 8);
            if (count($horas) < 2 || count($dias) < 1 || (new DateTimeImmutable($horas[0]))->getTimestamp() < now()->subDays(2)->timestamp ||
                ! array_intersect(array_keys($datos['hourly']), array_column(self::VARIABLES, 0))) {
                throw new \UnexpectedValueException('Series vacías.');
            }
            $ahora = $this->instante($datos['current']['time']);
            if ($ahora->getTimestamp() > now()->timestamp || $ahora->getTimestamp() < now()->subHours(6)->timestamp) {
                throw new \UnexpectedValueException('Datos actuales fuera de fecha.');
            }
            $offsetEsperado = $zona->getOffset(new DateTimeImmutable('@'.now()->timestamp));
            $offset = $datos['utc_offset_seconds'] ?? $offsetEsperado;
            if (! is_int($offset) || $offset !== $offsetEsperado) {
                throw new \UnexpectedValueException('Desfase horario inválido.');
            }
            // El proveedor fija los días al offset de la consulta. Las horas se conservan en UTC.
            $hoy = gmdate('Y-m-d', now()->timestamp + $offset);
            $actual = ['instante' => $ahora->format('Y-m-d\TH:i:s\Z')];
            $serie = array_map(fn ($f) => ['instante' => $f], $horas);
            foreach (self::VARIABLES as $clave => [$campo, $nombre, $unidad, $minimo, $maximo]) {
                $this->unidad($datos['current_units'][$campo] ?? null, $unidad);
                $this->unidad($datos['hourly_units'][$campo] ?? null, $unidad);
                $actual[$clave] = $this->numero($datos['current'][$campo] ?? null, $minimo, $maximo);
                $valores = $datos['hourly'][$campo] ?? array_fill(0, count($horas), null);
                if (! is_array($valores) || ! array_is_list($valores) || count($valores) !== count($horas)) {
                    throw new \UnexpectedValueException('La serie no corresponde a sus horas.');
                }
                foreach ($valores as $i => $valor) {
                    $serie[$i][$clave] = $this->numero($valor, $minimo, $maximo);
                }
            }
            $diario = array_map(fn ($f) => ['fecha' => gmdate('Y-m-d', (new DateTimeImmutable($f))->getTimestamp() + $offset)], $dias);
            foreach (self::DIARIAS as $clave => [$campo, $unidad, $minimo, $maximo]) {
                $this->unidad($datos['daily_units'][$campo] ?? null, $unidad);
                $valores = $datos['daily'][$campo] ?? array_fill(0, count($dias), null);
                if (! is_array($valores) || ! array_is_list($valores) || count($valores) !== count($dias)) {
                    throw new \UnexpectedValueException('La serie no corresponde a sus días.');
                }
                foreach ($valores as $i => $valor) {
                    $diario[$i][$clave] = $this->numero($valor, $minimo, $maximo);
                }
            }
            foreach (['amanecer' => 'sunrise', 'atardecer' => 'sunset'] as $clave => $campo) {
                foreach ($diario as $i => $dia) {
                    $valor = $datos['daily'][$campo][$i] ?? null;
                    $diario[$i][$clave] = $valor === null || $valor === 0 ? null : $this->instante($valor)->format('Y-m-d\TH:i:s\Z');
                }
            }
            $historial = array_values(array_filter($serie, function ($fila) use ($ahora) {
                $instante = (new DateTimeImmutable($fila['instante']))->getTimestamp();

                return $instante > $ahora->getTimestamp() - 24 * 3600 && $instante <= $ahora->getTimestamp();
            }));
            $pronostico = array_values(array_filter($serie, fn ($fila) => gmdate('Y-m-d', (new DateTimeImmutable($fila['instante']))->getTimestamp() + $offset) >= $hoy));
            $diario = array_values(array_filter($diario, fn ($fila) => $fila['fecha'] >= $hoy));
            if (count($historial) > 24 || count($pronostico) < 2 || count($pronostico) > 168 || count($diario) < 1 || count($diario) > 7) {
                throw new \UnexpectedValueException('Períodos del tiempo incoherentes.');
            }

            return ['fuente' => 'Open-Meteo', 'origen' => 'estimado', 'zonaHoraria' => $zona->getName(), 'consultadoEl' => now('UTC')->toIso8601ZuluString(),
                'ubicacion' => ['latitud' => $latitud, 'longitud' => $longitud], 'actual' => $actual, 'horas' => $pronostico, 'historial' => $historial, 'dias' => $diario,
                'variables' => array_map(fn ($v) => ['nombre' => $v[1], 'unidad' => $v[2]], self::VARIABLES)];
        } catch (\Throwable $e) {
            throw new ErrorConsulta('PRONOSTICO_NO_DISPONIBLE', 'No se pudo consultar el tiempo. Inténtalo de nuevo más tarde.', 503);
        }
    }

    private function numero(mixed $valor, float $minimo, float $maximo): ?float
    {
        if ($valor === null) {
            return null;
        }
        if ((! is_float($valor) && ! is_int($valor)) || ! is_finite((float) $valor) || $valor < $minimo || $valor > $maximo) {
            throw new \UnexpectedValueException('Valor fuera de la unidad esperada.');
        }

        return (float) $valor;
    }

    private function unidad(mixed $valor, string $esperada): void
    {
        if ($valor !== null && $valor !== $esperada) {
            throw new \UnexpectedValueException('Unidad inesperada.');
        }
    }

    private function instantes(mixed $valores, int $maximo): array
    {
        if (! is_array($valores) || ! array_is_list($valores) || count($valores) > $maximo) {
            throw new \UnexpectedValueException('Tiempo inválido.');
        }
        $resultado = [];
        $anterior = null;
        foreach ($valores as $v) {
            $fecha = $this->instante($v);
            if ($anterior !== null && $fecha->getTimestamp() <= $anterior) {
                throw new \UnexpectedValueException('Horas repetidas o desordenadas.');
            }
            $anterior = $fecha->getTimestamp();
            $resultado[] = $fecha->format('Y-m-d\TH:i:s\Z');
        }

        return $resultado;
    }

    private function instante(mixed $valor): DateTimeImmutable
    {
        if (! is_int($valor) || $valor < 0 || $valor > now()->addDays(9)->timestamp) {
            throw new \UnexpectedValueException('Fecha inválida.');
        }

        return new DateTimeImmutable('@'.$valor);
    }
}
