<?php

declare(strict_types=1);

namespace App\Services\Sequias;

use Illuminate\Support\Facades\Cache;

/** Publicaciones derivadas inmutables e índices locales sustituidos al finalizar. */
final class AlmacenPublicacionesSequias
{
    public function __construct(private readonly string $directorio, private readonly LectorJsonLocal $lector) {}

    public function leer(string $tipo, string $clave): ?array
    {
        $indice = $this->ruta($tipo, $clave);
        if (! is_file($indice)) {
            return null;
        }
        $id = @file_get_contents($indice, false, null, 0, 65);
        if (! is_string($id) || ! preg_match('/^[a-f0-9]{64}$/D', $id)) {
            $this->invalido();
        }
        try {
            $r = $this->lector->leer($this->directorio.'/versiones/'.$id.'.json');
        } catch (SeriePrecipitacionInvalida) {
            $this->invalido();
        }
        if (! hash_equals($id, $r['sha256'])) {
            $this->invalido();
        }

        return $r['datos'];
    }

    public function guardar(string $tipo, string $clave, array $datos): string
    {
        $indice = $this->ruta($tipo, $clave);
        $lock = Cache::lock('sequias:publicar:'.hash('sha256', $indice), 120);
        if (! $lock->get()) {
            throw new SeriePrecipitacionInvalida('PUBLICACION_OCUPADA', 'Otra preparación está publicando este registro.');
        }
        try {
            foreach ([$this->directorio.'/versiones', dirname($indice)] as $carpeta) {
                if (! is_dir($carpeta) && ! @mkdir($carpeta, 0700, true) && ! is_dir($carpeta)) {
                    $this->noDisponible();
                }
            }
            $json = json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
            $id = hash('sha256', $json);
            $archivo = $this->directorio.'/versiones/'.$id.'.json';
            if (is_file($archivo)) {
                if (! hash_equals($id, (string) @hash_file('sha256', $archivo))) {
                    $this->invalido();
                }
            } else {
                $this->sustituir($archivo, $json);
            }
            $this->sustituir($indice, $id);

            return $id;
        } finally {
            $lock->release();
        }
    }

    private function ruta(string $tipo, string $clave): string
    {
        if (! in_array($tipo, ['consultas', 'bases', 'ajustes'], true) || ! preg_match('/^[a-f0-9]{64}$/D', $clave)) {
            throw new \LogicException('Clave interna de publicación inválida.');
        }

        return $this->directorio.'/'.$tipo.'/'.$clave;
    }

    private function sustituir(string $archivo, string $contenido): void
    {
        $temporal = $archivo.'.preparando-'.bin2hex(random_bytes(12));
        try {
            if (@file_put_contents($temporal, $contenido, LOCK_EX) !== strlen($contenido) || ! @rename($temporal, $archivo)) {
                $this->noDisponible();
            }
        } finally {
            if (is_file($temporal)) {
                @unlink($temporal);
            }
        }
    }

    private function invalido(): never
    {
        throw new SeriePrecipitacionInvalida('PUBLICACION_INVALIDA', 'El índice o resultado preparado está alterado o incompleto; no se entregaron datos.');
    }

    private function noDisponible(): never
    {
        throw new SeriePrecipitacionInvalida('ALMACEN_NO_DISPONIBLE', 'No se pudo publicar el resultado; las versiones originales se conservan.');
    }
}
