<?php

namespace App\Services\Datos;

use App\Services\Api\ErrorConsulta;
use JsonException;

final class ArchivoDatos
{
    public static function leer(string $archivo): string
    {
        if (str_contains($archivo, '://') || is_link($archivo) || ! is_file($archivo) || ! is_readable($archivo)) {
            throw new ErrorConsulta('ARCHIVO_INVALIDO', 'Elige un archivo local legible.');
        }
        $contenido = @file_get_contents($archivo, false, null, 0, 2 * 1024 * 1024 + 1);
        if ($contenido === false || strlen($contenido) > 2 * 1024 * 1024) {
            throw new ErrorConsulta('ARCHIVO_INVALIDO', 'El archivo debe ocupar hasta 2 MB.');
        }

        return $contenido;
    }

    public static function json(string $contenido): array
    {
        if (strlen($contenido) > 2 * 1024 * 1024) {
            throw new ErrorConsulta('ARCHIVO_INVALIDO', 'El archivo debe ocupar hasta 2 MB.');
        }
        try {
            $datos = json_decode($contenido, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ErrorConsulta('ARCHIVO_INVALIDO', 'Revisa el formato JSON del archivo.');
        }
        if (! is_array($datos) || array_is_list($datos)) {
            throw new ErrorConsulta('ARCHIVO_INVALIDO', 'El archivo debe contener un objeto de datos.');
        }

        return $datos;
    }
}
