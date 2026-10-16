<?php

namespace App\Services\FocosCalor;

use DateTimeImmutable;

final class NormalizadorFirms
{
    public function convertir(array $fila): ?array
    {
        $x = $this->numero($fila['longitude'] ?? null);
        $y = $this->numero($fila['latitude'] ?? null);
        if ($x === null || $y === null || abs($x) > 180 || abs($y) > 90) {
            return null;
        }
        $dia = $fila['acq_date'] ?? '';
        $hora = $fila['acq_time'] ?? '';
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $dia) || ! preg_match('/^\d{1,4}$/D', $hora)) {
            return null;
        }
        $texto = $dia.' '.str_pad($hora, 4, '0', STR_PAD_LEFT);
        $instante = DateTimeImmutable::createFromFormat('!Y-m-d Hi T', $texto.' UTC');
        if (! $instante || $instante->format('Y-m-d Hi') !== $texto) {
            return null;
        }
        $faltantes = [];
        $atributos = [];
        foreach (['satelite' => 'satellite', 'instrumento' => 'instrument'] as $destino => $origen) {
            $valor = trim($fila[$origen] ?? '');
            $atributos[$destino] = $valor === '' ? null : $valor;
            if ($valor === '') {
                $faltantes[] = ['campo' => 'atributos.'.$destino, 'motivo' => 'No informado por el proveedor.'];
            }
        }
        $original = trim($fila['confidence'] ?? '');
        $atributos['confianzaOriginal'] = $original === '' ? null : $original;
        $atributos['confianza'] = ['h' => 'alta', 'n' => 'nominal', 'l' => 'baja'][strtolower($original)] ?? 'desconocida';
        if ($atributos['confianza'] === 'desconocida') {
            $faltantes[] = ['campo' => 'atributos.confianza', 'motivo' => 'Valor VIIRS ausente o no reconocido; original conservado.'];
        }
        $frp = $this->numero($fila['frp'] ?? null);
        $atributos['potenciaRadiativa'] = $frp !== null && $frp >= 0 ? $frp : null;
        $atributos['unidadPotenciaRadiativa'] = 'MW';
        if ($atributos['potenciaRadiativa'] === null) {
            $faltantes[] = ['campo' => 'atributos.potenciaRadiativa', 'motivo' => 'FRP ausente o no válido.'];
        }
        $version = trim($fila['version'] ?? '') ?: null;
        $fecha = $instante->format('Y-m-d\TH:i:s\Z');

        return [
            'identificador' => 'firms-'.hash('sha256', json_encode([config('focos.producto'), $atributos['satelite'], $atributos['instrumento'], $fecha, $x, $y], JSON_PRESERVE_ZERO_FRACTION)),
            'geometria' => ['type' => 'Point', 'coordinates' => [$x, $y]],
            'referenciaCapa' => null,
            'fechas' => ['observadoDesde' => $fecha, 'observadoHasta' => null, 'procesadoEn' => null, 'emitidoEn' => null],
            'naturaleza' => 'observacion', 'fuentesRef' => ['nasa-firms-snpp-nrt'], 'metodo' => null,
            'atributos' => $atributos + ['versionOriginal' => $version], 'faltantes' => $faltantes,
        ];
    }

    private function numero(mixed $valor): ?float
    {
        return is_scalar($valor) && is_numeric($valor) && is_finite((float) $valor) ? (float) $valor : null;
    }
}
