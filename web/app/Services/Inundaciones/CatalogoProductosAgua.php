<?php

declare(strict_types=1);

namespace App\Services\Inundaciones;

use App\Services\Api\ErrorConsulta;
use InvalidArgumentException;
use JsonException;

/** Solo lee publicaciones locales: no calcula, descarga ni usa ejemplos de respaldo. */
final class CatalogoProductosAgua
{
    public const MAX_PRODUCTOS = 20;

    public const MAX_BYTES = 2_000_000;

    public const MAX_TOTAL = 16_000_000;

    public function __construct(
        private readonly string $directorio,
        private readonly ValidadorProductoAgua $validador,
    ) {}

    public function catalogo(): array
    {
        $productos = [];
        foreach ($this->productos() as $producto) {
            $dato = $producto['dato'];
            $productos[] = ['id' => $producto['sha256'], 'comparacion' => $dato['comparacion'],
                'territorio' => $dato['territorio'], 'estado' => $dato['estado']];
        }

        return ['versionContrato' => 'agua-0.1-candidata',
            'estado' => $productos === [] ? 'sin_productos_validados' : 'disponible',
            'productos' => $productos];
    }

    public function consultar(array $filtros): array
    {
        $elegido = null;
        $hayProductos = false;
        $hayTerritorio = false;
        foreach ($this->productos() as $producto) {
            $hayProductos = true;
            $dato = $producto['dato'];
            if ($dato['territorio']['identificador'] === $filtros['territorio']) {
                $hayTerritorio = true;
                if ($dato['comparacion']['referencia'] === $filtros['referencia']
                    && $dato['comparacion']['comparacion'] === $filtros['comparacion']) {
                    $elegido = $producto;
                }
            }
        }
        // Se termina la lectura antes de devolver: un duplicado posterior también invalida.
        if ($elegido !== null) {
            return $elegido;
        }
        if ($hayProductos && ! $hayTerritorio) {
            throw new ErrorConsulta('TERRITORIO_SIN_COBERTURA', 'No hay una comparación disponible para ese lugar.', 404);
        }
        throw new ErrorConsulta('PRODUCTO_NO_DISPONIBLE', 'No hay una comparación disponible para ese lugar y fechas.', 503);
    }

    private function productos(): iterable
    {
        if (! file_exists($this->directorio) && ! is_link($this->directorio)) {
            return;
        }
        if (! is_dir($this->directorio) || is_link($this->directorio)) {
            $this->invalido();
        }
        $raiz = realpath($this->directorio);
        if ($raiz === false) {
            $this->invalido();
        }
        $indice = $this->directorio.'/indice.json';
        if (! file_exists($indice) && ! is_link($indice)) {
            return;
        }
        try {
            $catalogo = json_decode($this->leer($indice, 16_384, $raiz), false, 64, JSON_THROW_ON_ERROR);
            if (! $catalogo instanceof \stdClass || count(get_object_vars($catalogo)) !== 2 || ($catalogo->version ?? null) !== 'agua-publicaciones-1'
                || ! is_array($catalogo->productos ?? null) || ! array_is_list($catalogo->productos)
                || count($catalogo->productos) > self::MAX_PRODUCTOS) {
                $this->invalido();
            }
            $vistos = [];
            $identidades = [];
            $total = 0;
            foreach ($catalogo->productos as $sha) {
                if (! is_string($sha) || ! preg_match('/^[a-f0-9]{64}$/D', $sha) || isset($vistos[$sha])) {
                    $this->invalido();
                }
                $vistos[$sha] = true;
                $raw = $this->leer($this->directorio.'/'.$sha.'.json', self::MAX_BYTES, $raiz);
                $total += strlen($raw);
                if ($total > self::MAX_TOTAL || ! hash_equals($sha, hash('sha256', $raw))) {
                    $this->invalido();
                }
                $dato = json_decode($raw, false, 64, JSON_THROW_ON_ERROR);
                if (! $dato instanceof \stdClass) {
                    $this->invalido();
                }
                $dato = $this->validador->validar($dato);
                $id = $dato['territorio']['identificador'];
                $identidad = json_encode([$id, $dato['comparacion']['referencia'], $dato['comparacion']['comparacion']], JSON_THROW_ON_ERROR);
                if (isset($identidades[$identidad])) {
                    $this->invalido();
                }
                $identidades[$identidad] = true;
                yield ['dato' => $dato, 'sha256' => $sha];
            }
        } catch (JsonException|InvalidArgumentException) {
            $this->invalido();
        }
    }

    private function leer(string $archivo, int $limite, string $raiz): string
    {
        if (is_link($archivo) || realpath($archivo) !== $raiz.DIRECTORY_SEPARATOR.basename($archivo) || ! is_file($archivo)) {
            $this->invalido();
        }
        $raw = @file_get_contents($archivo, false, null, 0, $limite + 1);
        if ($raw === false || strlen($raw) > $limite) {
            $this->invalido();
        }

        return $raw;
    }

    private function invalido(): never
    {
        throw new ErrorConsulta('PRODUCTO_INVALIDO', 'No se pudo cargar la comparación. Inténtalo otra vez.', 503);
    }
}
