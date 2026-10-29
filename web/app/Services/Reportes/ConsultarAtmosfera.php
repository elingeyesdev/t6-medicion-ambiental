<?php

namespace App\Services\Reportes;

use App\Services\Api\ErrorConsulta;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/** Lectura explícita de modelos numéricos; no escribe lecturas ambientales ni usa IA. */
final class ConsultarAtmosfera
{
    public function ejecutar(array $puntos, array $variables, int $horas): array
    {
        if (! config('reportes.atmosfera.habilitado')) {
            throw new ErrorConsulta('ATMOSFERA_NO_DISPONIBLE', 'La consulta atmosférica no está disponible en este momento.', 503);
        }
        $catalogo = app(VariablesReporte::class)->atmosfericas();
        $grupos = [];
        foreach ($variables as $k) {
            $grupos[$k === 'aqi' ? 'aire' : 'tiempo'][] = $k;
        }
        $desde = now('UTC')->startOfHour();
        $comunes = ['latitude' => implode(',', array_column($puntos, 'latitud')), 'longitude' => implode(',', array_column($puntos, 'longitud')),
            'timezone' => 'UTC', 'timeformat' => 'unixtime', 'forecast_days' => 4];
        if (config('reportes.atmosfera.clave')) {
            $comunes['apikey'] = config('reportes.atmosfera.clave');
        }
        try {
            $respuestas = Http::pool(function (Pool $pool) use ($grupos, $catalogo, $comunes) {
                $consultas = [];
                foreach ($grupos as $grupo => $claves) {
                    $url = config($grupo === 'aire' ? 'reportes.atmosfera.aire_url' : 'reportes.atmosfera.url');
                    if (! is_string($url) || parse_url($url, PHP_URL_SCHEME) !== 'https' || parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_QUERY) !== null) {
                        throw new \UnexpectedValueException('Proveedor inválido.');
                    }
                    $query = $comunes + ['hourly' => implode(',', array_map(fn ($k) => $catalogo[$k][0], $claves))];
                    if ($grupo === 'tiempo') {
                        $query += ['temperature_unit' => 'celsius', 'wind_speed_unit' => 'kmh', 'precipitation_unit' => 'mm'];
                    }
                    $consultas[] = $pool->as($grupo)->acceptJson()->connectTimeout(3)->timeout(8)->withOptions(['allow_redirects' => false])->get($url, $query);
                }

                return $consultas;
            });
            $filas = [];
            $faltantes = [];
            foreach ($grupos as $grupo => $claves) {
                $r = $respuestas[$grupo] ?? null;
                if (! $r instanceof Response || ! $r->successful() || strlen($r->body()) > 1500000) {
                    throw new \UnexpectedValueException('Respuesta no disponible.');
                }
                $json = $r->json();
                $lugares = count($puntos) === 1 && isset($json['hourly']) ? [$json] : $json;
                if (! is_array($lugares) || ! array_is_list($lugares) || count($lugares) !== count($puntos)) {
                    throw new \UnexpectedValueException('Faltan localidades.');
                }
                foreach ($puntos as $i => $p) {
                    $lugar = $lugares[$i];
                    if (count($puntos) > 1 && ($lugar['location_id'] ?? null) !== $i) {
                        throw new \UnexpectedValueException('Localidades desordenadas.');
                    }
                    $tiempos = $lugar['hourly']['time'] ?? null;
                    if (! is_array($tiempos) || ! array_is_list($tiempos) || count($tiempos) < 1 || count($tiempos) > 200) {
                        throw new \UnexpectedValueException('Horas inválidas.');
                    }
                    $anterior = null;
                    foreach ($tiempos as $t) {
                        if (! is_int($t) || ($anterior !== null && $t - $anterior !== 3600) || $t < now()->subDays(2)->timestamp || $t > now()->addDays(6)->timestamp) {
                            throw new \UnexpectedValueException('Horas desordenadas o fuera de fecha.');
                        }
                        $anterior = $t;
                    }
                    $indices = [];
                    for ($h = 0; $h < $horas; $h++) {
                        $t = $desde->timestamp + $h * 3600;
                        $indices[] = [array_search($t, $tiempos, true), gmdate('Y-m-d\TH:i:s\Z', $t)];
                    }
                    foreach ($claves as $k) {
                        [$campo, $nombre, $unidad, $min, $max] = $catalogo[$k];
                        $serie = $lugar['hourly'][$campo] ?? null;
                        if ($serie !== null && (! is_array($serie) || ! array_is_list($serie) || count($serie) !== count($tiempos))) {
                            throw new \UnexpectedValueException('Serie incompleta.');
                        }
                        if ($serie !== null && ($lugar['hourly_units'][$campo] ?? null) !== $unidad) {
                            throw new \UnexpectedValueException('Unidad inesperada.');
                        }
                        foreach ($indices as [$indice, $instante]) {
                            $valor = $indice === false ? null : ($serie[$indice] ?? null);
                            if ($valor !== null && ((! is_int($valor) && ! is_float($valor)) || ! is_finite((float) $valor) || $valor < $min || $valor > $max)) {
                                throw new \UnexpectedValueException('Valor inválido.');
                            }
                            $dato = ['puntoCodigo' => $p['codigo'], 'punto' => $p['nombre'], 'pais' => $p['pais'], 'latitud' => $p['latitud'], 'longitud' => $p['longitud'],
                                'metrica' => $k, 'variable' => $nombre, 'unidad' => $k === 'aqi' ? 'AQI' : $unidad, 'instante' => $instante, 'valor' => $valor,
                                'origen' => 'estimado', 'fuente' => $grupo === 'aire' ? 'open-meteo-cams' : 'open-meteo-modelo'];
                            if ($valor === null) {
                                $faltantes[] = $dato;
                            } else {
                                $filas[] = $dato;
                            }
                        }
                    }
                }
            }

            return ['filas' => $filas, 'faltantes' => $faltantes, 'consultadoEl' => now('UTC')->toIso8601ZuluString(),
                'atribucion' => 'Open-Meteo · Modelos meteorológicos y CAMS (Copernicus Atmosphere Monitoring Service).'];
        } catch (\Throwable $e) {
            throw new ErrorConsulta('ATMOSFERA_NO_DISPONIBLE', 'No se pudo consultar el pronóstico. Inténtalo de nuevo más tarde.', 503);
        }
    }
}
