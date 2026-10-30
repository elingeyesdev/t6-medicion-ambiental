<?php

namespace App\Services\Reportes;

use App\Services\Datos\CatalogoAmbiental;
use App\Services\Tiempo\ConsultarPronostico;

final class VariablesReporte
{
    public function atmosfericas(): array
    {
        $variables = ConsultarPronostico::VARIABLES;
        $variables['nieve_acumulada'] = ['snow_depth', 'Nieve acumulada', 'm', 0, 100];
        $variables['aqi'] = ['us_aqi', 'Aire · escala de Estados Unidos', 'USAQI', 0, 1000];
        $variables['aqi_europeo'] = ['european_aqi', 'Aire · escala europea', 'EAQI', 0, 10000];

        return $variables;
    }

    public function metricas(): array
    {
        $metricas = app(CatalogoAmbiental::class)->metricas();
        foreach ($this->atmosfericas() as $k => [, $nombre, $unidad, $min, $max]) {
            $metricas[$k] ??= ['nombre' => $nombre, 'unidad' => $unidad, 'minimo' => $min, 'maximo' => $max];
        }

        return $metricas;
    }
}
