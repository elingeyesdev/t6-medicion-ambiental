<?php

namespace App\Services\Alertas;

use App\Models\AlertaAmbiental;
use App\Models\AvisoAmbiental;
use App\Models\PreferenciasAvisos;
use App\Models\User;

final class PrepararAvisos
{
    public function ejecutar(AlertaAmbiental $alerta): void
    {
        // Una carga histórica conserva sus alertas sin presentarlas como avisos actuales.
        if ($alerta->instante->lt(now()->subMinutes(15))) {
            return;
        }
        foreach (PreferenciasAvisos::where('activos', true)->orderBy('usuario_id')->cursor() as $pref) {
            if (! in_array($alerta->metrica, $pref->metricas, true) || ($alerta->origen === 'simulado' && ! $pref->simulados)) {
                continue;
            }
            $usuario = User::whereKey($pref->usuario_id)->where('activo', true)->lockForUpdate()->first();
            if (! $usuario) {
                continue;
            }
            $ubicacion = $usuario->preferencias;
            if ($ubicacion?->latitud !== null && $ubicacion?->longitud !== null &&
                self::distancia($ubicacion->latitud, $ubicacion->longitud, $alerta->contenido['latitud'], $alerta->contenido['longitud']) > 50) {
                continue;
            }
            $reciente = AvisoAmbiental::where('usuario_id', $usuario->id)->where('created_at', '>=', now()->subMinutes(2))
                ->whereHas('alerta', fn ($q) => $q->where('metrica', $alerta->metrica)->where('origen', $alerta->origen))->exists();
            if (! $reciente) {
                AvisoAmbiental::firstOrCreate(['usuario_id' => $usuario->id, 'alerta_id' => $alerta->id]);
            }
        }
    }

    public static function distancia(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $a = sin(deg2rad($lat2 - $lat1) / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin(deg2rad($lon2 - $lon1) / 2) ** 2;

        return 6371 * 2 * asin(min(1, sqrt($a)));
    }
}
