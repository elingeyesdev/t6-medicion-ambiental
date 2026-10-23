<?php

declare(strict_types=1);

namespace App\Services\Sequias\Experimental;

use App\Services\Sequias\SeriePrecipitacionInvalida;
use RuntimeException;

/** Candidato de #35, aislado de rutas y contrato operativo hasta revisión. */
final class Spi3Propuesto
{
    public const VERSION = 'spi3-era5-gamma-mle-1991-2020-v0.1-propuesta';

    public function __construct(private readonly NumericaGammaNormal $numerica) {}

    public function calcular(array $base, mixed $objetivo): array
    {
        if (! array_is_list($base)) {
            throw new SeriePrecipitacionInvalida('ACUMULACION_INVALIDA', 'La base debe ser una lista de acumulados.');
        }
        foreach ([...$base, $objetivo] as $v) {
            if ($v !== null && ((! is_int($v) && ! is_float($v)) || ! is_finite((float) $v) || $v < 0)) {
                throw new SeriePrecipitacionInvalida('ACUMULACION_INVALIDA', 'Los acumulados deben ser números finitos no negativos o null.');
            }
        }
        $r = ['valor' => null, 'categoria' => null, 'motivo' => null,
            'aniosValidos' => count(array_filter($base, fn ($v) => $v !== null)),
            'parametrosAjuste' => null, 'probabilidadAcumulada' => null];
        if (count($base) !== 30 || $r['aniosValidos'] !== 30) {
            return [...$r, 'motivo' => 'BASE_INCOMPLETA'];
        }
        if ($objetivo === null) {
            return [...$r, 'motivo' => 'PERIODO_INCOMPLETO'];
        }
        $positivos = array_values(array_filter($base, fn ($v) => $v >= 0.01));
        if (count($positivos) < 10 || max($positivos) - min($positivos) <= 1e-10) {
            return [...$r, 'motivo' => 'BASE_NO_AJUSTABLE'];
        }
        try {
            [$forma, $escala] = $this->numerica->ajustar($positivos);
            $q = (30 - count($positivos)) / 30;
            $r['parametrosAjuste'] = ['forma' => $forma, 'escalaMm' => $escala,
                'probabilidadCero' => $q, 'positivos' => count($positivos)];
            [$g] = $this->numerica->gamma($forma, $objetivo < 0.01 ? 0.0 : $objetivo / $escala);
            $p = $q + (1 - $q) * $g;
            $r['probabilidadAcumulada'] = $p;
            if ($p <= 0 || $p >= 1) {
                return [...$r, 'motivo' => 'PROBABILIDAD_EXTREMA'];
            }
            $valor = $this->numerica->normalInversa($p);

            return [...$r, 'valor' => $valor, 'categoria' => $this->clasificar($valor)];
        } catch (RuntimeException) {
            return [...$r, 'motivo' => 'AJUSTE_FALLIDO'];
        }
    }

    public function clasificar(float $spi): string
    {
        if (! is_finite($spi)) {
            throw new SeriePrecipitacionInvalida('INDICE_INVALIDO', 'Solo se clasifican índices finitos.');
        }

        return match (true) {
            $spi <= -2 => 'extremadamente_seco',
            $spi <= -1.5 => 'muy_seco',
            $spi <= -1 => 'moderadamente_seco',
            $spi <= 1 => 'normal',
            $spi <= 1.5 => 'moderadamente_humedo',
            $spi <= 2 => 'muy_humedo',
            default => 'extremadamente_humedo',
        };
    }
}
