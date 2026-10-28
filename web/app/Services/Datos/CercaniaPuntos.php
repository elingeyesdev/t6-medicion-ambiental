<?php

namespace App\Services\Datos;

use App\Models\PuntoAmbiental;

final class CercaniaPuntos
{
    public static function distancia(float $lat, float $lon, float $otraLat, float $otraLon): float
    {
        $a = sin(deg2rad($otraLat - $lat) / 2) ** 2 + cos(deg2rad($lat)) * cos(deg2rad($otraLat)) * sin(deg2rad($otraLon - $lon) / 2) ** 2;

        return 6371 * 2 * asin(sqrt(max(0, min(1, $a))));
    }

    public function buscar(float $lat, float $lon, float $radio): array
    {
        $delta = $radio / 110;
        $datos = [];
        foreach (PuntoAmbiental::where('activo', true)->whereBetween('latitud', [$lat - $delta, $lat + $delta])->cursor() as $punto) {
            $distancia = self::distancia($lat, $lon, $punto->latitud, $punto->longitud);
            if ($distancia <= $radio) {
                $datos[] = $punto->only(['codigo', 'nombre', 'pais', 'latitud', 'longitud', 'tipo']) + ['distanciaKm' => round($distancia, 3)];
            }
        }
        usort($datos, fn ($a, $b) => [$a['distanciaKm'], $a['codigo']] <=> [$b['distanciaKm'], $b['codigo']]);

        return array_slice($datos, 0, 20);
    }
}
