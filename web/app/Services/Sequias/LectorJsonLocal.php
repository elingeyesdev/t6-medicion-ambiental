<?php

declare(strict_types=1);

namespace App\Services\Sequias;

use JsonException;

final class LectorJsonLocal
{
    public const MAX_BYTES = 8 * 1024 * 1024;

    /** @return array{datos: array, crudo: string, sha256: string, bytes: int} */
    public function leer(string $archivo, int $limite = self::MAX_BYTES): array
    {
        $ruta = realpath($archivo);
        if ($ruta === false || ! is_file($ruta) || ! is_readable($ruta)) {
            throw new SeriePrecipitacionInvalida('ARCHIVO_NO_DISPONIBLE', 'No se puede leer el archivo local indicado.');
        }
        $recurso = @fopen($ruta, 'rb');
        if ($recurso === false) {
            throw new SeriePrecipitacionInvalida('ARCHIVO_NO_DISPONIBLE', 'No se puede abrir el archivo local indicado.');
        }
        try {
            $crudo = stream_get_contents($recurso, $limite + 1);
        } finally {
            fclose($recurso);
        }
        if ($crudo === false) {
            throw new SeriePrecipitacionInvalida('ARCHIVO_NO_DISPONIBLE', 'No se puede leer el contenido del archivo.');
        }
        if (strlen($crudo) > $limite) {
            throw new SeriePrecipitacionInvalida('ARCHIVO_DEMASIADO_GRANDE', 'El archivo supera el límite de bytes admitido.');
        }
        try {
            $documento = json_decode($crudo, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new SeriePrecipitacionInvalida('JSON_INVALIDO', 'El archivo no contiene JSON válido o excede la profundidad permitida.');
        }
        if (! is_array($documento) || array_is_list($documento)) {
            throw new SeriePrecipitacionInvalida('ESTRUCTURA_INVALIDA', 'El archivo debe contener un objeto JSON.');
        }

        return ['datos' => $documento, 'crudo' => $crudo, 'sha256' => hash('sha256', $crudo), 'bytes' => strlen($crudo)];
    }
}
