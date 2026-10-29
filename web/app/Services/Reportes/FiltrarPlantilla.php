<?php

namespace App\Services\Reportes;

final class FiltrarPlantilla
{
    public static function aplicar(array $filas, array $condiciones): array
    {
        if ($condiciones === []) {
            return $filas;
        }

        // Las variables deben coincidir en punto, instante y procedencia. Un faltante no equivale a cero.
        return collect($filas)->groupBy(fn ($f) => json_encode([$f['puntoCodigo'], $f['instante'], $f['origen'], $f['fuente']]))
            ->filter(function ($grupo) use ($condiciones) {
                $valores = $grupo->pluck('valor', 'metrica');
                foreach ($condiciones as $condicion) {
                    if (! $valores->has($condicion['variable'])) {
                        return false;
                    }
                    $valor = (float) $valores[$condicion['variable']];
                    $umbral = (float) $condicion['valor'];
                    if (! match ($condicion['operador']) {
                        '>' => $valor > $umbral, '<' => $valor < $umbral, '=' => $valor === $umbral,
                    }) {
                        return false;
                    }
                }

                return true;
            })->flatten(1)->values()->all();
    }
}
