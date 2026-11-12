<?php

declare(strict_types=1);

namespace App\Services\Inundaciones;

use App\Services\Api\ErrorConsulta;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use JsonException;

/** Registro local de un producto ya revisado; no cambia su tipo ni calcula resultados. */
final class PublicadorProductosAgua
{
    public function __construct(
        private readonly string $directorio,
        private readonly ValidadorProductoAgua $validador,
        private readonly CatalogoProductosAgua $catalogo,
    ) {}

    public function archivo(string $archivo, bool $reemplazar = false): string
    {
        $ruta = realpath($archivo);
        if ($ruta === false || ! is_file($ruta)) {
            $this->error('ARCHIVO_NO_DISPONIBLE', 'No se puede leer el producto local.');
        }
        $raw = @file_get_contents($ruta, false, null, 0, CatalogoProductosAgua::MAX_BYTES + 1);
        if ($raw === false || strlen($raw) > CatalogoProductosAgua::MAX_BYTES) {
            $this->error('PRODUCTO_INVALIDO', 'El producto no se puede leer o supera el tamaño permitido.');
        }
        try {
            $dato = json_decode($raw, false, 64, JSON_THROW_ON_ERROR);
            if (! $dato instanceof \stdClass) {
                throw new InvalidArgumentException;
            }

            return $this->registrar($this->validador->validar($dato), $reemplazar);
        } catch (JsonException|InvalidArgumentException) {
            $this->error('PRODUCTO_INVALIDO', 'El producto no coincide con el contrato de agua.');
        }
    }

    public function registrar(array $dato, bool $reemplazar = false): string
    {
        try {
            $dato = $this->validador->validar($dato);
            $raw = json_encode($dato, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
        } catch (JsonException|InvalidArgumentException) {
            $this->error('PRODUCTO_INVALIDO', 'El producto no coincide con el contrato de agua.');
        }
        if (strlen($raw) > CatalogoProductosAgua::MAX_BYTES) {
            $this->error('PRODUCTO_INVALIDO', 'El producto supera el tamaño permitido.');
        }
        $sha = hash('sha256', $raw);
        $lock = Cache::lock('agua:registrar:'.hash('sha256', $this->directorio), 120);
        if (! $lock->get()) {
            $this->error('PUBLICACION_OCUPADA', 'Otra publicación está usando el almacén.');
        }
        try {
            $actuales = $this->catalogo->catalogo()['productos'];
            $ids = array_column($actuales, 'id');
            $posicion = null;
            foreach ($actuales as $i => $actual) {
                if ($actual['territorio']['identificador'] === $dato['territorio']['identificador']
                    && $actual['comparacion']['referencia'] === $dato['comparacion']['referencia']
                    && $actual['comparacion']['comparacion'] === $dato['comparacion']['comparacion']) {
                    if ($actual['id'] === $sha) {
                        return $sha;
                    }
                    if (! $reemplazar) {
                        $this->error('PRODUCTO_EXISTENTE', 'Ese lugar y fechas ya tienen un producto. Revisa el cambio antes de usar --reemplazar.');
                    }
                    $posicion = $i;
                }
            }
            if ($posicion === null) {
                $ids[] = $sha;
            } else {
                $ids[$posicion] = $sha;
            }
            if (count($ids) > CatalogoProductosAgua::MAX_PRODUCTOS) {
                $this->error('CATALOGO_COMPLETO', 'El catálogo alcanzó su límite de productos.');
            }
            $bytes = strlen($raw);
            foreach ($ids as $id) {
                if ($id !== $sha) {
                    $tamano = @filesize($this->directorio.'/'.$id.'.json');
                    if ($tamano === false) {
                        $this->error('PRODUCTO_INVALIDO', 'El catálogo cambió durante la publicación.');
                    }
                    $bytes += $tamano;
                }
            }
            if ($bytes > CatalogoProductosAgua::MAX_TOTAL) {
                $this->error('CATALOGO_COMPLETO', 'El catálogo supera el tamaño total permitido.');
            }
            if (! is_dir($this->directorio) && ! @mkdir($this->directorio, 0700, true) && ! is_dir($this->directorio)) {
                $this->error('ALMACEN_NO_DISPONIBLE', 'No se puede crear el almacén de productos.');
            }
            $destino = $this->directorio.'/'.$sha.'.json';
            if (file_exists($destino) || is_link($destino)) {
                $existente = is_link($destino) ? false : @file_get_contents($destino, false, null, 0, CatalogoProductosAgua::MAX_BYTES + 1);
                if ($existente === false || ! hash_equals($sha, hash('sha256', $existente))) {
                    $this->error('PRODUCTO_INVALIDO', 'La versión guardada está alterada. No se sustituyó.');
                }
            } else {
                $this->sustituir($destino, $raw);
            }
            $indice = json_encode(['version' => 'agua-publicaciones-1', 'productos' => $ids], JSON_THROW_ON_ERROR);
            $this->sustituir($this->directorio.'/indice.json', $indice);

            return $sha;
        } finally {
            $lock->release();
        }
    }

    private function sustituir(string $destino, string $contenido): void
    {
        $temporal = $this->directorio.'/.preparando-'.bin2hex(random_bytes(12));
        try {
            if (@file_put_contents($temporal, $contenido, LOCK_EX) !== strlen($contenido)) {
                $this->error('ALMACEN_NO_DISPONIBLE', 'No se pudo guardar la publicación; el índice anterior se conserva.');
            }
            @chmod($temporal, 0600);
            if (! @rename($temporal, $destino)) {
                $this->error('ALMACEN_NO_DISPONIBLE', 'No se pudo actualizar la publicación; el índice anterior se conserva.');
            }
        } finally {
            if (is_file($temporal)) {
                @unlink($temporal);
            }
        }
    }

    private function error(string $codigo, string $mensaje): never
    {
        throw new ErrorConsulta($codigo, $mensaje, 503);
    }
}
