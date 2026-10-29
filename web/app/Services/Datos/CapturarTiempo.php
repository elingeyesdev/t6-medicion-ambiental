<?php

namespace App\Services\Datos;

use App\Models\PuntoAmbiental;
use App\Services\Api\ErrorConsulta;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

final class CapturarTiempo
{
    public function __construct(private RegistrarLecturas $registrar) {}

    public function ejecutar(PuntoAmbiental $punto): array
    {
        if (! config('datos.tiempo_habilitado')) {
            throw new ErrorConsulta('PROVEEDOR_DESACTIVADO', 'La captura externa no está habilitada.', 503);
        }
        if (! $punto->activo) {
            throw new ErrorConsulta('PUNTO_INACTIVO', 'El punto no está activo.', 422);
        }
        try {
            $respuesta = Http::connectTimeout(5)->timeout(15)->acceptJson()->get('https://api.open-meteo.com/v1/forecast', [
                'latitude' => $punto->latitud, 'longitude' => $punto->longitud, 'current' => 'temperature_2m,relative_humidity_2m',
                'temperature_unit' => 'celsius', 'timezone' => 'UTC', 'timeformat' => 'unixtime', 'forecast_days' => 1,
            ]);
            if (! $respuesta->successful() || strlen($respuesta->body()) > 1024 * 1024) {
                throw new \RuntimeException('Respuesta no disponible.');
            }
            $json = $respuesta->json();
            $actual = $json['current'] ?? [];
            $unidades = $json['current_units'] ?? [];
            if (! is_int($actual['time'] ?? null) || $actual['time'] < now()->subHours(2)->timestamp || $actual['time'] > now()->timestamp || ($json['utc_offset_seconds'] ?? null) !== 0) {
                throw new \RuntimeException('Fecha no válida.');
            }
            $filas = [];
            foreach (['temperature_2m' => ['temperatura', '°C'], 'relative_humidity_2m' => ['humedad', '%']] as $clave => [$metrica, $unidad]) {
                if (($actual[$clave] ?? null) === null) {
                    continue;
                }
                if (($unidades[$clave] ?? null) !== $unidad || ! is_numeric($actual[$clave])) {
                    throw new \RuntimeException('Unidad no válida.');
                }
                $filas[] = ['punto' => $punto->codigo, 'metrica' => $metrica, 'valor' => $actual[$clave], 'instante' => CarbonImmutable::createFromTimestampUTC($actual['time'])->toIso8601ZuluString(), 'origen' => 'estimado', 'fuente' => 'open-meteo-modelo'];
            }
            if ($filas === []) {
                throw new \RuntimeException('Sin datos.');
            }
        } catch (\Throwable $e) {
            throw new ErrorConsulta('PROVEEDOR_NO_DISPONIBLE', 'No se pudieron recuperar los datos del tiempo. No se generaron valores de reemplazo.', 503);
        }

        return $this->registrar->ejecutar($filas);
    }
}
