<?php

namespace App\Services\Aire;

use App\Services\Api\ErrorConsulta;
use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class ConsultarCalidadAire
{
    public const VARIABLES = [
        'indice' => ['european_aqi', 'Índice europeo', 'EAQI'],
        'pm25' => ['pm2_5', 'Partículas finas (PM2,5)', 'μg/m³'],
        'pm10' => ['pm10', 'Partículas (PM10)', 'μg/m³'],
        'no2' => ['nitrogen_dioxide', 'Dióxido de nitrógeno', 'μg/m³'],
        'ozono' => ['ozone', 'Ozono', 'μg/m³'],
        'so2' => ['sulphur_dioxide', 'Dióxido de azufre', 'μg/m³'],
    ];

    public function ejecutar(float $latitud, float $longitud): array
    {
        if (! is_finite($latitud) || ! is_finite($longitud) || abs($latitud) > 90 || abs($longitud) > 180) {
            throw new ErrorConsulta('FILTROS_INVALIDOS', 'Elige un lugar válido en el mapa.');
        }
        $latitud = round($latitud, 4);
        $longitud = round($longitud, 4);
        $clave = 'aire-cams-europeo-v1:'.hash('sha256', json_encode([$latitud, $longitud]));

        return Cache::remember($clave, now()->addMinutes(15), fn () => $this->consultar($latitud, $longitud));
    }

    private function consultar(float $latitud, float $longitud): array
    {
        try {
            $campos = implode(',', array_column(self::VARIABLES, 0));
            $respuesta = Http::acceptJson()->connectTimeout(3)->timeout(12)->withOptions([
                'allow_redirects' => false,
                'progress' => static function ($total, $descargado): void {
                    if ($descargado > 524288) {
                        throw new \UnexpectedValueException('Respuesta excesiva.');
                    }
                },
            ])->get('https://air-quality-api.open-meteo.com/v1/air-quality', [
                'latitude' => $latitud, 'longitude' => $longitud, 'current' => $campos, 'hourly' => $campos,
                'past_hours' => 24, 'forecast_hours' => 1, 'timezone' => 'UTC', 'timeformat' => 'unixtime', 'domains' => 'cams_global',
            ]);
            if (! $respuesta->successful() || strlen($respuesta->body()) > 524288) {
                throw new \UnexpectedValueException('Respuesta no disponible.');
            }
            $datos = $respuesta->json();
            if (! is_array($datos) || ($datos['utc_offset_seconds'] ?? null) !== 0 ||
                ! $this->coordenada($datos['latitude'] ?? null, 90) || ! $this->coordenada($datos['longitude'] ?? null, 180) ||
                abs($datos['latitude'] - $latitud) > 1 || min(abs($datos['longitude'] - $longitud), 360 - abs($datos['longitude'] - $longitud)) > 1) {
                throw new \UnexpectedValueException('Ubicación o zona inesperada.');
            }
            $ahora = now()->timestamp;
            $instante = $datos['current']['time'] ?? null;
            if (! $this->hora($instante) || $instante > $ahora || $instante < $ahora - 7200 ||
                ($datos['current']['interval'] ?? null) !== 3600) {
                throw new \UnexpectedValueException('Fecha actual no disponible.');
            }
            $horas = $datos['hourly']['time'] ?? null;
            if (! is_array($horas) || ! array_is_list($horas) || count($horas) < 1 || count($horas) > 26) {
                throw new \UnexpectedValueException('Serie no disponible.');
            }
            $anterior = null;
            $serie = [];
            foreach ($horas as $i => $hora) {
                if (! $this->hora($hora) || ($anterior !== null && $hora !== $anterior + 3600) || $hora < $ahora - 26 * 3600 || $hora > $ahora + 3600) {
                    throw new \UnexpectedValueException('Horas incoherentes.');
                }
                $anterior = $hora;
                if ($hora >= $ahora - 86400 && $hora <= $ahora) {
                    $serie[$i] = ['instante' => $this->iso($hora)];
                }
            }
            if ($serie === []) {
                throw new \UnexpectedValueException('No hay horas recientes.');
            }
            $actual = ['instante' => $this->iso($instante)];
            $variables = [];
            foreach (self::VARIABLES as $clave => [$campo, $nombre, $unidad]) {
                if (($datos['current_units']['time'] ?? null) !== 'unixtime' || ($datos['hourly_units']['time'] ?? null) !== 'unixtime' ||
                    ($datos['current_units'][$campo] ?? null) !== $unidad || ($datos['hourly_units'][$campo] ?? null) !== $unidad ||
                    ! array_key_exists($campo, $datos['current']) || ! array_key_exists($campo, $datos['hourly'])) {
                    throw new \UnexpectedValueException('Unidad o variable no disponible.');
                }
                $actual[$clave] = $this->numero($datos['current'][$campo]);
                $valores = $datos['hourly'][$campo];
                if (! is_array($valores) || ! array_is_list($valores) || count($valores) !== count($horas)) {
                    throw new \UnexpectedValueException('La variable no corresponde a las horas.');
                }
                foreach ($valores as $i => $valor) {
                    $valor = $this->numero($valor);
                    if (isset($serie[$i])) {
                        $serie[$i][$clave] = $valor;
                    }
                }
                $variables[$clave] = ['nombre' => $nombre, 'unidad' => $unidad];
            }
            if (count(array_filter(array_intersect_key($actual, self::VARIABLES), fn ($v) => $v !== null)) === 0) {
                throw new \UnexpectedValueException('Sin valores actuales.');
            }

            return ['escala' => 'european_aqi', 'nombreEscala' => 'Índice europeo', 'origen' => 'estimado',
                'fuente' => 'CAMS global · Open-Meteo', 'documentacion' => 'https://open-meteo.com/en/docs/air-quality-api',
                'atribucion' => 'CAMS ENSEMBLE, Copernicus Atmosphere Monitoring Service y Open-Meteo · CC BY 4.0',
                'ubicacion' => ['latitud' => $latitud, 'longitud' => $longitud],
                'celda' => ['latitud' => $datos['latitude'], 'longitud' => $datos['longitude']],
                'consultado' => $this->iso($ahora), 'desde' => $this->iso($ahora - 86400), 'hasta' => $this->iso($ahora),
                'variables' => $variables, 'actual' => $actual, 'horas' => array_values($serie)];
        } catch (\Throwable $e) {
            throw new ErrorConsulta('CALIDAD_AIRE_NO_DISPONIBLE', 'No pudimos cargar la calidad del aire. Inténtalo de nuevo.', 503);
        }
    }

    private function numero(mixed $valor): int|float|null
    {
        if ($valor === null) {
            return null;
        }
        if ((! is_int($valor) && ! is_float($valor)) || ! is_finite((float) $valor) || $valor < 0) {
            throw new \UnexpectedValueException('Valor inválido.');
        }

        return $valor;
    }

    private function coordenada(mixed $valor, int $limite): bool
    {
        return (is_int($valor) || is_float($valor)) && is_finite((float) $valor) && abs($valor) <= $limite;
    }

    private function hora(mixed $valor): bool
    {
        return is_int($valor) && $valor > 0 && $valor % 3600 === 0;
    }

    private function iso(int $valor): string
    {
        return (new DateTimeImmutable('@'.$valor))->format('Y-m-d\TH:i:s\Z');
    }
}
