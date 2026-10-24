<?php

declare(strict_types=1);

namespace App\Services\Sequias\Experimental;

use App\Services\Sequias\RepositorioSeriesPrecipitacion;
use App\Services\Sequias\SeriePrecipitacionInvalida;
use DateTimeImmutable;
use DateTimeZone;

/** Lee versiones verificadas; no acepta informes mensuales arbitrarios del cliente. */
final class EnsayoSpi3
{
    public function __construct(private readonly RepositorioSeriesPrecipitacion $series, private readonly Spi3Propuesto $spi) {}

    public function ejecutar(string $baseId, string $objetivoId, string $mesFinal, DateTimeImmutable $ahora): array
    {
        return $this->calcularPreparado($this->preparar($baseId, $objetivoId, $mesFinal, $ahora));
    }

    /** Verifica calendario y originales antes de buscar cualquier resultado en caché. */
    public function preparar(string $baseId, string $objetivoId, string $mesFinal, DateTimeImmutable $ahora): PreparacionSpi3
    {
        if (! preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])$/D', $mesFinal) || $mesFinal < '2021-01'
            || $mesFinal >= $ahora->setTimezone(new DateTimeZone('UTC'))->format('Y-m')) {
            throw new SeriePrecipitacionInvalida('MES_NO_ADMITIDO', 'Indica un mes completo desde 2021-01 y anterior al mes UTC actual.');
        }
        $base = $this->series->leer($baseId);
        $objetivo = $this->series->leer($objetivoId);
        $p = $base['procedencia'];
        $o = $objetivo['procedencia'];
        foreach (['tipoEvidencia', 'proveedor', 'producto', 'naturaleza', 'muestra'] as $campo) {
            if ($p[$campo] !== $o[$campo]) {
                throw new SeriePrecipitacionInvalida('SERIES_INCOMPATIBLES', 'Base y objetivo deben conservar fuente, producto, naturaleza y muestra.');
            }
        }
        foreach (['coordenadaSolicitada', 'coordenadaCelda'] as $campo) {
            for ($i = 0; $i < 2; $i++) {
                if (abs($p[$campo][$i] - $o[$campo][$i]) > 1e-9) {
                    throw new SeriePrecipitacionInvalida('SERIES_INCOMPATIBLES', 'Las coordenadas solicitadas y las celdas deben coincidir entre períodos.');
                }
            }
        }
        if ($p['tipoEvidencia'] === 'captura_externa' && ($p['proveedor'] !== 'Open-Meteo' || $p['producto'] !== 'ERA5' || $p['naturaleza'] !== 'reanalisis')) {
            throw new SeriePrecipitacionInvalida('PRODUCTO_NO_PROPUESTO', 'Este ensayo externo solo contrasta la propuesta Open-Meteo/ERA5.');
        }
        $mensuales = array_column($base['informe']['meses'], 'precipitacionAcumuladaMm', 'mes');
        $objetivos = array_column($objetivo['informe']['meses'], 'precipitacionAcumuladaMm', 'mes');
        $fin = new DateTimeImmutable($mesFinal.'-01', new DateTimeZone('UTC'));
        $ventanas = [];
        for ($anio = 1991; $anio <= 2020; $anio++) {
            $ventanas[] = ['anioFin' => $anio, 'acumuladoMm' => $this->acumular($mensuales, $anio.substr($mesFinal, 4))];
        }
        $acumulado = $this->acumular($objetivos, $mesFinal);

        return new PreparacionSpi3(['estado' => 'ensayo_pendiente_revision', 'metodo' => Spi3Propuesto::VERSION,
            'tipoEvidencia' => $p['tipoEvidencia'],
            'naturaleza' => $p['naturaleza'] === 'simulacion' ? 'controlada_simulacion' : 'derivado_de_reanalisis',
            'muestra' => $p['muestra'], 'coordenadaSolicitada' => $p['coordenadaSolicitada'], 'coordenadaCelda' => $p['coordenadaCelda'],
            'periodo' => ['mesFinal' => $mesFinal, 'desde' => $fin->modify('-2 months')->format('Y-m-d'),
                'hastaIncluido' => $fin->modify('last day of this month')->format('Y-m-d'), 'zona' => 'UTC'],
            'base' => ['primerAnioFin' => 1991, 'ultimoAnioFin' => 2020, 'mesFin' => (int) $fin->format('m'), 'ventanas' => $ventanas],
            'precipitacionAcumuladaMm' => $acumulado,
            'versiones' => array_map(fn ($v) => ['identificador' => $v['identificador'], 'integridad' => $v['integridad'], 'procedencia' => $v['procedencia']], [$base, $objetivo]),
            'advertencias' => ['Método propuesto sin adoptar; ensayo local sin respuesta HTTP operativa.',
                'Resultado de una celda, no superficie afectada ni sequía de todo un municipio.',
                'La coordenada solicitada y el centro de celda pueden pertenecer a municipios distintos.',
                'La procedencia es declarada y su integridad se verifica; no certifica autenticidad del proveedor.']]);
    }

    /** @internal Solo recibe la preparación verificada; no es una entrada HTTP. */
    public function calcularPreparado(PreparacionSpi3 $preparacion): array
    {
        $r = [];
        foreach ($preparacion->informe as $campo => $valor) {
            $r[$campo] = $valor;
            if ($campo === 'precipitacionAcumuladaMm') {
                $r['resultado'] = $this->spi->calcular(
                    array_column($preparacion->informe['base']['ventanas'], 'acumuladoMm'), $valor);
            }
        }

        return $r;
    }

    private function acumular(array $mensuales, string $mesFinal): ?float
    {
        $fin = new DateTimeImmutable($mesFinal.'-01', new DateTimeZone('UTC'));
        $suma = 0.0;
        $error = 0.0;
        for ($n = 2; $n >= 0; $n--) {
            $mes = $fin->modify('-'.$n.' months')->format('Y-m');
            if (! isset($mensuales[$mes])) {
                return null;
            }
            $valor = $mensuales[$mes] - $error;
            $nueva = $suma + $valor;
            if (! is_finite($nueva)) {
                throw new SeriePrecipitacionInvalida('ACUMULACION_FUERA_DE_RANGO', 'La suma trimestral excede el rango numérico.');
            }
            $error = ($nueva - $suma) - $valor;
            $suma = $nueva;
        }

        return $suma;
    }
}
