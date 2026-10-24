<?php

declare(strict_types=1);

namespace App\Services\Sequias;

use DateTimeImmutable;
use DateTimeZone;

/** Calidad de series diarias en mm/UTC; sin proveedor de red ni método de sequía. */
final class ValidadorSeriePrecipitacion
{
    // Límite técnico de memoria/trabajo, no duración científica de una referencia.
    public const MAX_DIAS = 14640;

    public function validar(array $documento, string $desde, string $hasta): array
    {
        [$inicio, $fin, $diasEsperados] = $this->validarPeriodo($desde, $hasta);
        if (($documento['daily_units']['precipitation_sum'] ?? null) !== 'mm') {
            throw new SeriePrecipitacionInvalida('UNIDAD_INVALIDA', 'La precipitación diaria debe declarar unidad mm.');
        }
        if (($documento['utc_offset_seconds'] ?? null) !== 0) {
            throw new SeriePrecipitacionInvalida('ZONA_INVALIDA', 'La serie debe declarar utc_offset_seconds igual a 0 (UTC).');
        }
        $fechas = $documento['daily']['time'] ?? null;
        $valores = $documento['daily']['precipitation_sum'] ?? null;
        if (! is_array($fechas) || ! is_array($valores) || ! array_is_list($fechas) || ! array_is_list($valores)
            || count($fechas) !== count($valores)) {
            throw new SeriePrecipitacionInvalida('ESTRUCTURA_INVALIDA', 'daily.time y daily.precipitation_sum deben ser listas de igual longitud.');
        }
        if (count($fechas) > self::MAX_DIAS) {
            throw new SeriePrecipitacionInvalida('SERIE_DEMASIADO_GRANDE', 'La serie supera el límite técnico de registros por archivo.');
        }

        $diarios = [];
        $anterior = null;
        foreach ($fechas as $i => $texto) {
            $dia = $this->dia($texto);
            if ($dia < $inicio || $dia > $fin) {
                throw new SeriePrecipitacionInvalida('FECHA_FUERA_DE_RANGO', 'Hay registros fuera del período declarado.');
            }
            if (array_key_exists($texto, $diarios)) {
                throw new SeriePrecipitacionInvalida('FECHA_DUPLICADA', 'La serie contiene más de un registro para el mismo día.');
            }
            if ($anterior !== null && $dia < $anterior) {
                throw new SeriePrecipitacionInvalida('ORDEN_FECHAS_INVALIDO', 'Los registros deben estar en orden cronológico.');
            }
            $valor = $valores[$i];
            if ($valor !== null && ((! is_int($valor) && ! is_float($valor)) || ! is_finite((float) $valor) || $valor < 0)) {
                throw new SeriePrecipitacionInvalida('PRECIPITACION_INVALIDA', 'La precipitación debe ser un número finito no negativo o null.');
            }
            $diarios[$texto] = $valor;
            $anterior = $dia;
        }

        return $this->agregar($diarios, $inicio, $fin, $desde, $hasta, $diasEsperados, count($fechas));
    }

    /** Calendario y límite comunes a archivos locales y solicitudes al proveedor. */
    public function validarPeriodo(string $desde, string $hasta): array
    {
        $inicio = $this->dia($desde);
        $fin = $this->dia($hasta);
        if ($inicio > $fin || $inicio->format('d') !== '01' || $fin->format('d') !== $fin->format('t')) {
            throw new SeriePrecipitacionInvalida('RANGO_NO_MENSUAL', 'Indica meses completos: primer día inicial y último día final, en orden.');
        }
        $diasEsperados = (int) $inicio->diff($fin)->days + 1;
        if ($diasEsperados > self::MAX_DIAS) {
            throw new SeriePrecipitacionInvalida('RANGO_DEMASIADO_GRANDE', 'El intervalo supera el límite técnico de días por archivo.');
        }

        return [$inicio, $fin, $diasEsperados];
    }

    private function agregar(array $diarios, DateTimeImmutable $inicio, DateTimeImmutable $fin, string $desde, string $hasta, int $diasEsperados, int $registros): array
    {
        $meses = [];
        $sumas = [];
        $compensaciones = [];
        for ($dia = $inicio; $dia <= $fin; $dia = $dia->modify('+1 day')) {
            $mes = $dia->format('Y-m');
            $texto = $dia->format('Y-m-d');
            if (! isset($meses[$mes])) {
                $meses[$mes] = ['mes' => $mes, 'diasEsperados' => (int) $dia->format('t'),
                    'diasValidos' => 0, 'diasAusentes' => 0, 'diasNulos' => 0,
                    'fechasAusentes' => [], 'fechasNulas' => [],
                    'estado' => 'incompleto', 'precipitacionAcumuladaMm' => null];
                $sumas[$mes] = 0.0;
                $compensaciones[$mes] = 0.0;
            }
            if (! array_key_exists($texto, $diarios)) {
                $meses[$mes]['diasAusentes']++;
                $meses[$mes]['fechasAusentes'][] = $texto;
            } elseif ($diarios[$texto] === null) {
                $meses[$mes]['diasNulos']++;
                $meses[$mes]['fechasNulas'][] = $texto;
            } else {
                $meses[$mes]['diasValidos']++;
                // Suma compensada para reducir el error acumulativo de punto flotante.
                $corregido = (float) $diarios[$texto] - $compensaciones[$mes];
                $siguiente = $sumas[$mes] + $corregido;
                if (! is_finite($siguiente)) {
                    throw new SeriePrecipitacionInvalida('ACUMULACION_FUERA_DE_RANGO', 'La suma mensual excede el rango numérico admitido.');
                }
                $compensaciones[$mes] = ($siguiente - $sumas[$mes]) - $corregido;
                $sumas[$mes] = $siguiente;
            }
        }
        foreach ($meses as $id => &$mes) {
            if ($mes['diasValidos'] === $mes['diasEsperados']) {
                $mes['estado'] = 'completo';
                $mes['precipitacionAcumuladaMm'] = $sumas[$id];
            }
        }
        unset($mes);
        $validos = array_sum(array_column($meses, 'diasValidos'));
        $completos = count(array_filter($meses, fn (array $m): bool => $m['estado'] === 'completo'));
        $apto = $completos === count($meses);

        return [
            'versionInforme' => 'calidad-precipitacion-1.0',
            'estado' => $apto ? 'completa' : ($validos === 0 ? 'sin_valores' : 'incompleta'),
            'aptoParaAgregacionMensual' => $apto,
            'periodo' => ['desde' => $desde, 'hastaIncluido' => $hasta, 'zona' => 'UTC'],
            'unidad' => 'mm',
            'resumen' => ['diasEsperados' => $diasEsperados, 'registrosRecibidos' => $registros,
                'diasValidos' => $validos, 'diasAusentes' => array_sum(array_column($meses, 'diasAusentes')),
                'diasNulos' => array_sum(array_column($meses, 'diasNulos')), 'mesesEsperados' => count($meses),
                'mesesCompletos' => $completos, 'mesesIncompletos' => count($meses) - $completos],
            'meses' => array_values($meses),
            'advertencias' => $apto
                ? ['Completitud diaria verificada; no acredita exactitud de la fuente, SPI ni sequía.']
                : ['Meses con días ausentes o null conservan acumulado null; no se imputaron valores.'],
        ];
    }

    private function dia(mixed $texto): DateTimeImmutable
    {
        if (! is_string($texto) || ! preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $texto) || substr($texto, 0, 4) === '0000') {
            throw new SeriePrecipitacionInvalida('FECHA_INVALIDA', 'Las fechas deben usar AAAA-MM-DD, con año entre 0001 y 9999.');
        }
        $dia = DateTimeImmutable::createFromFormat('!Y-m-d', $texto, new DateTimeZone('UTC'));
        if ($dia === false || $dia->format('Y-m-d') !== $texto) {
            throw new SeriePrecipitacionInvalida('FECHA_INVALIDA', 'La serie o el período contienen una fecha imposible.');
        }

        return $dia;
    }
}
