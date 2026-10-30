<?php

namespace App\Services\Mapas\Radar;

use Closure;
use UnexpectedValueException;

final class BloqueoRaster
{
    private static array $tomados = [];

    public static function ejecutar(string $raiz, Closure $trabajo): mixed
    {
        $base = realpath($raiz);
        if ($base === false || ! is_dir($base)) {
            throw new UnexpectedValueException('No existe el directorio de publicación.');
        }
        if (isset(self::$tomados[$base])) {
            return $trabajo();
        }
        $ruta = $base.'/publicacion.lock';
        if (is_link($ruta) || (file_exists($ruta) && ! is_file($ruta))) {
            throw new UnexpectedValueException('Bloqueo de publicación inválido.');
        }
        $archivo = fopen($ruta, 'c+b');
        if (! $archivo || ! flock($archivo, LOCK_EX | LOCK_NB)) {
            if ($archivo) {
                fclose($archivo);
            }
            throw new UnexpectedValueException('Ya hay una publicación en marcha.');
        }
        self::$tomados[$base] = true;
        try {
            return $trabajo();
        } finally {
            unset(self::$tomados[$base]);
            flock($archivo, LOCK_UN);
            fclose($archivo);
            // El archivo permanece: borrarlo permitiría dos bloqueos sobre inodos distintos.
        }
    }
}
