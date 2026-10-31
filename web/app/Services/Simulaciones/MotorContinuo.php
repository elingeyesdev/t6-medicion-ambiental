<?php

namespace App\Services\Simulaciones;

use App\Services\Datos\CatalogoAmbiental;

/** Adaptación determinista de simulacion.service.js: rangos, ciclo diurno y correlaciones originales. */
final class MotorContinuo
{
    public const METRICAS = ['temperatura', 'humedad', 'aqi', 'ica', 'ruido'];

    public function __construct(private CatalogoAmbiental $catalogo) {}

    public function inicial(string $semilla): array
    {
        return array_map(function ($p) use ($semilla) {
            $valores = [];
            foreach ($p['rangosSimulacion'] as $k => [$min, $max]) {
                $valores[$k] = round($min + ($max - $min) * $this->uniforme($semilla.':'.$p['codigo'].':'.$k), 2);
            }

            return array_intersect_key($p, array_flip(['codigo', 'nombre', 'pais', 'latitud', 'longitud', 'rangosSimulacion'])) + ['valores' => $valores, 'inyecciones' => []];
        }, $this->catalogo->leer()['puntos']);
    }

    public function siguiente(array $puntos, string $semilla, int $paso, int $ms): array
    {
        $hora = (int) now()->setTimestamp(intdiv($ms, 1000))->setTimezone('America/La_Paz')->format('G');
        $diurno = sin(($hora - 6) * M_PI / 12);
        $punta = ($hora >= 7 && $hora <= 9) || ($hora >= 17 && $hora <= 20);
        foreach ($puntos as &$p) {
            $v = $p['valores'];
            $r = $p['rangosSimulacion'];
            $n = [];
            $delta = fn ($k, $amplitud) => (2 * $this->uniforme($semilla.':'.$p['codigo'].':'.$paso.':'.$k) - 1) * $amplitud;
            $limitar = fn ($k, $x) => round(max($r[$k][0], min($r[$k][1], $x)), 2);
            [$min, $max] = $r['temperatura'];
            $objetivo = ($min + $max) / 2 + $diurno * ($max - $min) / 2 * .45;
            $n['temperatura'] = $limitar('temperatura', $v['temperatura'] + $delta('temperatura', .8) + ($objetivo - $v['temperatura']) * .10);
            $normal = ($n['temperatura'] - $min) / ($max - $min + .001);
            [$min, $max] = $r['humedad'];
            $n['humedad'] = $limitar('humedad', $v['humedad'] + $delta('humedad', 3) + ($max - $normal * ($max - $min) * .60 - $v['humedad']) * .08);
            [$min, $max] = $r['aqi'];
            $objetivo = ($min + $max) / 2 + $diurno * ($max - $min) / 2 * .20 + ($punta ? ($max - $min) / 2 * .15 : 0);
            $n['aqi'] = $limitar('aqi', $v['aqi'] + $delta('aqi', 8) + ($objetivo - $v['aqi']) * .07);
            $normal = ($n['aqi'] - $min) / ($max - $min + .001);
            [$min, $max] = $r['ica'];
            $n['ica'] = $limitar('ica', $v['ica'] + $delta('ica', 4) + ($max - $normal * ($max - $min) * .50 - $v['ica']) * .06);
            $n['ruido'] = $limitar('ruido', $v['ruido'] + $delta('ruido', 4) + ($punta ? 8 : 0));
            $p['valores'] = $n;
            $p['inyecciones'] = [];
        }

        return $puntos;
    }

    private function uniforme(string $clave): float
    {
        return hexdec(substr(hash('sha256', $clave), 0, 13)) / 4503599627370495;
    }
}
