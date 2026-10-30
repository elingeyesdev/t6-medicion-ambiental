<?php

namespace App\Services\Mapas\Radar;

use UnexpectedValueException;

final class ArchivoPublicacionRaster
{
    public static function directorio(string $raiz, string $relativa): string
    {
        $base = realpath($raiz);
        if ($base === false) {
            throw new UnexpectedValueException('No se puede preparar el archivo de capas.');
        }
        if ($relativa === '.') {
            return $base;
        }
        if (! preg_match('~\A[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_-]+)*\z~D', $relativa)) {
            throw new UnexpectedValueException('Archivo fuera de la publicación.');
        }
        $ruta = $base;
        foreach (explode('/', $relativa) as $segmento) {
            $ruta .= '/'.$segmento;
            if (is_link($ruta) || (! is_dir($ruta) && ! mkdir($ruta, 0700))) {
                throw new UnexpectedValueException('No se puede preparar el archivo de capas.');
            }
        }

        return $ruta;
    }

    public static function guardar(string $raiz, array $cuadro): void
    {
        $directorio = self::directorio($raiz, 'archivo/cuadros');
        $ruta = $directorio.'/'.$cuadro['id'].'.json';
        if (is_link($ruta)) {
            throw new UnexpectedValueException('Referencia de archivo inválida.');
        }
        if (file_exists($ruta)) {
            if (self::leer($raiz, $cuadro['id']) !== $cuadro) {
                throw new UnexpectedValueException('La referencia de una imagen guardada cambió.');
            }

            return;
        }
        $temporal = tempnam($directorio, 'cuadro-');
        if ($temporal === false) {
            throw new UnexpectedValueException('No se puede guardar la referencia de la imagen.');
        }
        try {
            $bytes = json_encode($cuadro, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            if (strlen($bytes) > 8192 || file_put_contents($temporal, $bytes) !== strlen($bytes) || ! rename($temporal, $ruta)) {
                throw new UnexpectedValueException('No se pudo guardar la referencia de la imagen.');
            }
        } finally {
            if (file_exists($temporal)) {
                unlink($temporal);
            }
        }
    }

    public static function leer(string $raiz, string $id): ?array
    {
        if (! preg_match('/\A[a-z0-9][a-z0-9_-]{0,79}\z/D', $id)) {
            return null;
        }
        $ruta = $raiz.'/archivo/cuadros/'.$id.'.json';
        if (! file_exists($ruta)) {
            return null;
        }
        $base = realpath($raiz);
        $real = realpath($ruta);
        if (! $base || ! $real || is_link($ruta) || ! is_file($real) || filesize($real) > 8192 ||
            ! str_starts_with(str_replace('\\', '/', $real).'/', str_replace('\\', '/', $base).'/')) {
            throw new UnexpectedValueException('Referencia archivada inválida.');
        }
        $cuadro = json_decode(file_get_contents($real), true, 32, JSON_THROW_ON_ERROR);
        $validado = (new CatalogoRaster)->validar(['version' => 1, 'cuadros' => [$cuadro]])[0];
        if ($validado['id'] !== $id) {
            throw new UnexpectedValueException('Referencia archivada distinta.');
        }

        return $validado;
    }
}
