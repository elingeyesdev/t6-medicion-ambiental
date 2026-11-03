<?php

namespace App\Services\Mapas\Capas;

final class VariablesRaster
{
    public static function todas(): array
    {
        return [
            'temperatura' => ['nombre' => 'Temperatura', 'unidad' => '°C', 'codificaciones' => ['temp-era5', 'temp-textura']],
            'lluvia' => ['nombre' => 'Precipitación acumulada', 'unidad' => 'mm', 'codificaciones' => ['lluvia-escalones']],
            'lluvia_intensidad' => ['nombre' => 'Intensidad de precipitación', 'unidad' => 'mm/h', 'codificaciones' => ['lluvia-escalones']],
            'viento' => ['nombre' => 'Viento a 10 m', 'unidad' => 'km/h', 'codificaciones' => ['viento-uv', 'viento-uv127', 'viento-direccion']],
            'visibilidad' => ['nombre' => 'Visibilidad', 'unidad' => 'km', 'codificaciones' => ['vis-etl', 'vis-textura']],
            'nieve' => ['nombre' => 'Nieve · equivalente de agua', 'unidad' => 'mm', 'codificaciones' => ['nieve-etl', 'nieve-fresca-textura']],
            'aqi' => ['nombre' => 'Calidad del aire (AQI)', 'unidad' => 'AQI', 'codificaciones' => ['aqi-textura']],
            'humedad' => ['nombre' => 'Humedad relativa', 'unidad' => '%', 'codificaciones' => ['humedad-etl']],
            'presion' => ['nombre' => 'Presión a nivel del mar', 'unidad' => 'hPa', 'codificaciones' => ['presion-etl']],
        ];
    }
}
