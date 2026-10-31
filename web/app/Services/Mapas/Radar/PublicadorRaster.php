<?php

namespace App\Services\Mapas\Radar;

use UnexpectedValueException;

final class PublicadorRaster
{
    /** La receta enumera archivos locales ya producidos; no descarga ni ejecuta ETL. */
    public function publicar(string $raiz, array $receta): int
    {
        if (! is_dir($raiz) || ($receta['version'] ?? null) !== 1 || ! is_array($receta['cuadros'] ?? null) ||
            count($receta['cuadros']) > config('capas.max_cuadros') || is_link($raiz.'/catalogo.json')) {
            throw new UnexpectedValueException('Publicación inválida.');
        }
        foreach ($receta['cuadros'] as &$c) {
            $bytes = ArchivoRaster::leer(ArchivoRaster::ruta($raiz, $c['archivo'] ?? ''));
            [$c['ancho'], $c['alto']] = ArchivoRaster::dimensiones($bytes);
            $c['sha256'] = hash('sha256', $bytes);
        }
        unset($c);
        $datos = ['version' => 1, 'cuadros' => (new CatalogoRaster)->validar($receta)];
        $temporal = tempnam($raiz, 'catalogo-');
        if ($temporal === false) {
            throw new UnexpectedValueException('No se puede escribir la publicación.');
        }
        try {
            if (file_put_contents($temporal, json_encode($datos, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false ||
                ! rename($temporal, $raiz.'/catalogo.json')) {
                throw new UnexpectedValueException('No se pudo publicar el catálogo.');
            }
        } finally {
            if (file_exists($temporal)) {
                unlink($temporal);
            }
        }

        return count($datos['cuadros']);
    }
}
