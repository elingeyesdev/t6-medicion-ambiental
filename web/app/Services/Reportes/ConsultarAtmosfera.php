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
            $grupos[in_array($k, ['aqi', 'aqi_europeo'], true) ? 'aire' : 'tiempo'][] = $k;
        }
        $desde = now('UTC')->startOfHour();
        $comunes = ['latitude' => implode(',', array_column($puntos, 'latitud')), 'longitude' => implode(',', array_column($puntos, 'longitud')),
            'timezone' => 'UTC', 'timeformat' => 'unixtime', 'forecast_hours' => $horas];
        if (config('reportes.atmosfera.clave')) {
            $comunes['apikey'] = config('reportes.atmosfera.clave');
        }
        try {
            $respuestas = Http::pool(function (Pool $pool) use ($grupos, $catalogo, $comunes) {
                $consultas = [];
                foreach ($grupos as $grupo => $claves) {
                    $url = config($grupo === 'aire' ? 'reportes.atmosfera.aire_url' : 'reportes.atmosfera.url');
                    if (! is_string($url) || parse_url($url, PHP_URL_SCHEME) !== 'https' || ! parse_url($url, PHP_URL_HOST) ||
                        parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PASS) !== null ||
                        parse_url($url, PHP_URL_QUERY) !== null || parse_url($url, PHP_URL_FRAGMENT) !== null) {
                        throw new \UnexpectedValueException('Proveedor inválido.');
                    }
                    $query = $comunes + ['hourly' => implode(',', array_map(fn ($k) => $catalogo[$k][0], $claves))];
                    if ($grupo === 'tiempo') {
                        $query += ['temperature_unit' => 'celsius', 'wind_speed_unit' => 'kmh', 'precipitation_unit' => 'mm'];
                    } else {
                        $query['domains'] = 'cams_global';
                    }
                    $consultas[] = $pool->as($grupo)->acceptJson()->connectTimeout(3)->timeout(12)->withOptions([
                        'allow_redirects' => false,
                        'progress' => static function ($total, $descargado): void {
                            if ($descargado > 1500000) {
                                throw new \UnexpectedValueException('Respuesta excesiva.');
                            }
                        },
                    ])->get($url, $query);
                }

                return $consultas;
            });
            $filas = [];
            $faltantes = [];
            $productos = [];
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
                $celdas = [];
                foreach ($puntos as $i => $p) {
                    $lugar = $lugares[$i];
                    if (count($puntos) > 1 && ($lugar['location_id'] ?? null) !== $i) {
                        throw new \UnexpectedValueException('Localidades desordenadas.');
                    }
                    if (($lugar['utc_offset_seconds'] ?? null) !== 0 || ($lugar['hourly_units']['time'] ?? null) !== 'unixtime' ||
                        ! $this->coordenada($lugar['latitude'] ?? null, 90) || ! $this->coordenada($lugar['longitude'] ?? null, 180) ||
                        abs($lugar['latitude'] - $p['latitud']) > 1 ||
                        min(abs($lugar['longitude'] - $p['longitud']), 360 - abs($lugar['longitude'] - $p['longitud'])) > 1) {
                        throw new \UnexpectedValueException('Zona o celda distinta de la consulta.');
                    }
                    $celdas[] = ['punto' => $p['codigo'], 'latitud' => $lugar['latitude'], 'longitud' => $lugar['longitude']];
                    $tiempos = $lugar['hourly']['time'] ?? null;
                    if (! is_array($tiempos) || ! array_is_list($tiempos) || count($tiempos) < 1 || count($tiempos) > 200) {
                        throw new \UnexpectedValueException('Horas inválidas.');
                    }
                    $anterior = null;
                    foreach ($tiempos as $t) {
                        if (! is_int($t) || $t % 3600 !== 0 || ($anterior !== null && $t - $anterior !== 3600) || $t < now()->subDays(2)->timestamp || $t > now()->addDays(6)->timestamp) {
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
                                'metrica' => $k, 'variable' => $nombre, 'unidad' => $unidad, 'instante' => $instante, 'valor' => $valor,
                                'origen' => 'estimado', 'fuente' => $grupo === 'aire' ? ($k === 'aqi' ? 'open-meteo-cams-us' : 'open-meteo-cams-eu') : 'open-meteo-modelo'];
                            if ($grupo === 'aire') {
                                $dato['escala'] = $campo;
                            }
                            if ($valor === null) {
                                $faltantes[] = $dato;
                            } else {
                                $filas[] = $dato;
                            }
                        }
                    }
                }
                $productos[] = ['grupo' => $grupo, 'producto' => $grupo === 'aire' ? 'CAMS global · Open-Meteo' : 'Modelos meteorológicos · Open-Meteo',
                    'sha256Respuesta' => hash('sha256', $r->body()), 'celdas' => $celdas];
            }

            return ['filas' => $filas, 'faltantes' => $faltantes, 'consultadoEl' => now('UTC')->toIso8601ZuluString(),
                'productos' => $productos, 'atribucion' => isset($grupos['aire'])
                    ? 'Open-Meteo y Copernicus Atmosphere Monitoring Service (CAMS global; reconocimiento CAMS ENSEMBLE). CC BY 4.0. Índices europeo y estadounidense separados.'
                    : 'Open-Meteo · Modelos meteorológicos. CC BY 4.0.'];
        } catch (\Throwable $e) {
            throw new ErrorConsulta('ATMOSFERA_NO_DISPONIBLE', 'No se pudo consultar el pronóstico. Inténtalo de nuevo más tarde.', 503);
        }
    }

    private function coordenada(mixed $valor, int $limite): bool
    {
        return (is_int($valor) || is_float($valor)) && is_finite((float) $valor) && abs($valor) <= $limite;
    }
}
