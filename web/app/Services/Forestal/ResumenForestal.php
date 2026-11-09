<?php

declare(strict_types=1);

namespace App\Services\Forestal;

use App\Services\Api\ErrorConsulta;
use InvalidArgumentException;

/** Recorta la serie propuesta; no valida ni publica el producto científico. */
final class ResumenForestal
{
    public function acotar(array $producto, int $desde, int $hasta): array
    {
        if (($producto['contrato'] ?? null) !== 'propuesta-met03-1'
            || ($producto['unidad'] ?? null) !== 'ha'
            || ($producto['decimales'] ?? null) !== 'cadena_decimal'
            || ! is_array($producto['fuente'] ?? null)) {
            $this->invalido();
        }
        try {
            $fuente = $producto['fuente'];
            if (! is_string($fuente['version'] ?? null) || ! is_array($fuente['anios'] ?? null)) {
                $this->invalido();
            }
            $disponibilidad = new DisponibilidadForestal($fuente['version'], $fuente['anios']);
        } catch (InvalidArgumentException) {
            $this->invalido();
        }
        if ($desde > $hasta || $desde < 1000 || $hasta > 9999) {
            throw new ErrorConsulta('FILTROS_INVALIDOS', 'El intervalo de años no es válido.');
        }
        $anios = range($desde, $hasta);
        if (array_diff($anios, $disponibilidad->anios) !== []) {
            throw new ErrorConsulta('FILTROS_INVALIDOS', 'El intervalo incluye años no publicados.');
        }

        $serie = $producto['serie'] ?? null;
        if (! is_array($serie) || ! array_is_list($serie) || count($serie) > count($disponibilidad->anios)) {
            $this->invalido();
        }
        $filas = [];
        foreach ($serie as $fila) {
            if (! is_array($fila) || ! is_int($fila['anio'] ?? null)
                || ! in_array($fila['anio'], $disponibilidad->anios, true)
                || isset($filas[$fila['anio']]) || ! array_key_exists('perdida_ha', $fila)) {
                $this->invalido();
            }
            $estado = $fila['estado'] ?? null;
            $area = $fila['perdida_ha'];
            if ($estado === 'dato') {
                // Límite de entrada, sin redondear ni convertir la cadena a float.
                if (! is_string($area) || ! preg_match('/^(0|[1-9][0-9]{0,39})(?:\.[0-9]{1,30})?$/D', $area)) {
                    $this->invalido();
                }
            } elseif ($estado !== 'no_informado' || $area !== null) {
                $this->invalido();
            }
            $filas[$fila['anio']] = $fila;
        }

        $elegida = [];
        $faltantes = [];
        $subtotal = null;
        $cantidad = 0;
        foreach ($anios as $anio) {
            $fila = $filas[$anio] ?? ['anio' => $anio, 'estado' => 'no_informado', 'perdida_ha' => null];
            $elegida[] = $fila;
            if ($fila['estado'] === 'no_informado') {
                $faltantes[] = $anio;
            } else {
                $subtotal = $subtotal === null ? $fila['perdida_ha'] : $this->sumar($subtotal, $fila['perdida_ha']);
                $cantidad++;
            }
        }
        // Conservar fuente, territorio, criterio, cobertura, capa y estado de revisión.
        $producto['consulta'] = ['desde' => $desde, 'hasta' => $hasta, 'extremos_incluidos' => true];
        $producto['serie'] = $elegida;
        $producto['resumen'] = [
            'anios_con_dato' => $cantidad,
            'anios_sin_dato' => $faltantes,
            'subtotal_informado_ha' => $subtotal,
            'total_periodo_ha' => $faltantes === [] ? $subtotal : null,
        ];

        return $producto;
    }

    private function sumar(string $a, string $b): string
    {
        [$enteroA, $decimalA] = array_pad(explode('.', $a, 2), 2, '');
        [$enteroB, $decimalB] = array_pad(explode('.', $b, 2), 2, '');
        $escala = max(strlen($decimalA), strlen($decimalB));
        $a = strrev($enteroA.str_pad($decimalA, $escala, '0'));
        $b = strrev($enteroB.str_pad($decimalB, $escala, '0'));
        $acarreo = 0;
        $suma = '';
        for ($i = 0, $n = max(strlen($a), strlen($b)); $i < $n; $i++) {
            $digito = (int) ($a[$i] ?? '0') + (int) ($b[$i] ?? '0') + $acarreo;
            $suma .= (string) ($digito % 10);
            $acarreo = intdiv($digito, 10);
        }
        if ($acarreo > 0) {
            $suma .= (string) $acarreo;
        }
        $suma = str_pad(strrev($suma), $escala + 1, '0', STR_PAD_LEFT);
        if ($escala > 0) {
            return substr($suma, 0, -$escala).'.'.substr($suma, -$escala);
        }

        return $suma;
    }

    private function invalido(): never
    {
        throw new ErrorConsulta('PRODUCTO_INVALIDO', 'La serie forestal no es válida.', 503);
    }
}
