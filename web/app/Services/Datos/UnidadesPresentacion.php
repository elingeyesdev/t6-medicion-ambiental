<?php

namespace App\Services\Datos;

use App\Models\User;

final class UnidadesPresentacion
{
    public function preferencias(User $usuario): array
    {
        $guardadas = $usuario->exists ? $usuario->preferencias?->unidades : null;
        $resultado = [];
        foreach (config('unidades') as $clave => $grupo) {
            $valor = is_array($guardadas) ? ($guardadas[$clave] ?? null) : null;
            $resultado[$clave] = is_string($valor) && isset($grupo['opciones'][$valor]) ? $valor : $grupo['defecto'];
        }

        return $resultado;
    }

    public function opcion(string $metrica, string $base, array $preferencias): ?array
    {
        foreach (config('unidades') as $clave => $grupo) {
            if (in_array($metrica, $grupo['variables'], true) && $base === $grupo['base']) {
                $elegida = $preferencias[$clave] ?? $grupo['defecto'];

                return $grupo['opciones'][$elegida] ?? $grupo['opciones'][$grupo['defecto']];
            }
        }

        return null;
    }

    public function valor(string $metrica, mixed $valor, string $base, array $preferencias, bool $diferencia = false): ?float
    {
        if ((! is_int($valor) && ! is_float($valor)) || ! is_finite((float) $valor)) {
            return null;
        }
        $opcion = $this->opcion($metrica, $base, $preferencias);

        return $opcion ? $valor * $opcion['factor'] / $opcion['divisor'] + ($diferencia ? 0 : $opcion['desplazamiento']) : (float) $valor;
    }

    public function unidad(string $metrica, string $base, array $preferencias): string
    {
        return $this->opcion($metrica, $base, $preferencias)['simbolo'] ?? $base;
    }

    public function texto(string $metrica, mixed $valor, string $base, array $preferencias, bool $diferencia = false): string
    {
        $convertido = $this->valor($metrica, $valor, $base, $preferencias, $diferencia);
        if ($convertido === null) {
            return 'No disponible';
        }
        $opcion = $this->opcion($metrica, $base, $preferencias);

        $decimales = $opcion['decimales'] ?? 2;
        $numero = number_format(abs($convertido) < 0.5 * 10 ** -$decimales ? 0 : $convertido, $decimales, ',', '.');

        return trim(rtrim(rtrim($numero, '0'), ',').' '.($opcion['simbolo'] ?? $base));
    }
}
