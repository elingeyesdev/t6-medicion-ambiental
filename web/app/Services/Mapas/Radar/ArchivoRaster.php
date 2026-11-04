<?php

namespace App\Services\Mapas\Radar;

use UnexpectedValueException;

final class ArchivoRaster
{
    public static function ruta(string $raiz, string $archivo): string
    {
        if (! preg_match('~\A[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_-]+)*\.png\z~D', $archivo)) {
            throw new UnexpectedValueException('Nombre de raster inválido.');
        }
        $base = realpath($raiz);
        $ruta = realpath($raiz.DIRECTORY_SEPARATOR.$archivo);
        $normalizar = fn ($p) => str_replace('\\', '/', $p).'/';
        if ($base === false || $ruta === false || ! is_file($ruta) ||
            ! str_starts_with($normalizar($ruta), $normalizar($base))) {
            throw new UnexpectedValueException('Raster fuera de la publicación o ausente.');
        }

        return $ruta;
    }

    public static function leer(string $ruta): string
    {
        $limite = (int) config('capas.max_bytes');
        $flujo = fopen($ruta, 'rb');
        if ($flujo === false) {
            throw new UnexpectedValueException('Raster ilegible.');
        }
        try {
            $bytes = stream_get_contents($flujo, $limite + 1);
        } finally {
            fclose($flujo);
        }
        if (! is_string($bytes) || strlen($bytes) > $limite) {
            throw new UnexpectedValueException('Raster excedido.');
        }
        self::dimensiones($bytes);

        return $bytes;
    }

    public static function dimensiones(string $bytes): array
    {
        $info = @getimagesizefromstring($bytes);
        if (substr($bytes, 0, 8) !== "\x89PNG\r\n\x1a\n" || ! $info ||
            $info[2] !== IMAGETYPE_PNG || $info[0] < 2 || $info[1] < 2 ||
            (int) config('capas.max_pixeles') < $info[0] * $info[1] ||
            ord($bytes[24]) !== 8 || ! in_array(ord($bytes[25]), [0, 2, 4, 6], true)) {
            throw new UnexpectedValueException('Se requiere PNG de datos de 8 bits.');
        }
        // Verifica todos los chunks: no servir un PNG truncado o con CRC corrupto.
        $posicion = 8;
        $idat = false;
        while ($posicion + 12 <= strlen($bytes)) {
            $largo = unpack('N', substr($bytes, $posicion, 4))[1];
            if ($largo > strlen($bytes) - $posicion - 12) {
                break;
            }
            $tipo = substr($bytes, $posicion + 4, 4);
            if (hash('crc32b', substr($bytes, $posicion + 4, $largo + 4), true) !== substr($bytes, $posicion + 8 + $largo, 4)) {
                break;
            }
            $idat = $idat || $tipo === 'IDAT';
            $posicion += $largo + 12;
            if ($tipo === 'IEND' && $largo === 0 && $idat && $posicion === strlen($bytes)) {
                return [$info[0], $info[1]];
            }
        }
        throw new UnexpectedValueException('PNG incompleto.');
    }
}
