<?php

namespace App\Services\Mapas\Radar;

use App\Services\Api\ErrorConsulta;
use App\Services\Mapas\Capas\VariablesRaster;
use DateTimeImmutable;
use UnexpectedValueException;

final class CatalogoRaster
{
    public function cargar(): array
    {
        $raiz = config('capas.directorio');
        if (! is_string($raiz) || ! is_dir($raiz)) {
            return ['cuadros' => [], 'faltantes' => 0];
        }
        $ruta = $raiz.'/catalogo.json';
        if (! file_exists($ruta)) {
            return ['cuadros' => [], 'faltantes' => 0];
        }
        try {
            if (is_link($ruta) || filesize($ruta) > 2097152) {
                throw new UnexpectedValueException;
            }
            $datos = json_decode(file_get_contents($ruta), true, 32, JSON_THROW_ON_ERROR);
            $cuadros = $this->validar($datos);
            $disponibles = [];
            $faltantes = 0;
            foreach ($cuadros as $cuadro) {
                try {
                    $archivo = ArchivoRaster::ruta($raiz, $cuadro['archivo']);
                    $tamano = filesize($archivo);
                    $info = @getimagesize($archivo);
                    if ($tamano < 45 || $tamano > config('capas.max_bytes') || ! $info ||
                        $info[0] !== $cuadro['ancho'] || $info[1] !== $cuadro['alto'] || $info[2] !== IMAGETYPE_PNG) {
                        throw new UnexpectedValueException;
                    }
                    $disponibles[] = $cuadro;
                } catch (\Throwable) {
                    $faltantes++;
                }
            }

            return ['cuadros' => $disponibles, 'faltantes' => $faltantes];
        } catch (\Throwable) {
            throw new ErrorConsulta('CAPAS_NO_DISPONIBLES', 'No se pueden consultar las capas. Inténtalo de nuevo.', 503);
        }
    }

    public function validar(mixed $datos): array
    {
        if (! is_array($datos) || ($datos['version'] ?? null) !== 1 ||
            ! is_array($datos['cuadros'] ?? null) || ! array_is_list($datos['cuadros']) ||
            count($datos['cuadros']) > config('capas.max_cuadros')) {
            throw new UnexpectedValueException('Catálogo inválido.');
        }
        $ids = [];
        $fechas = [];
        foreach ($datos['cuadros'] as $c) {
            $variable = VariablesRaster::todas()[$c['capa'] ?? ''] ?? null;
            if (! $variable || ! preg_match('/\A[a-z0-9][a-z0-9_-]{0,79}\z/D', $c['id'] ?? '') || isset($ids[$c['id']]) ||
                ! in_array($c['codificacion'] ?? null, $variable['codificaciones'], true) ||
                ! preg_match('/\A[0-9a-f]{64}\z/D', $c['sha256'] ?? '') ||
                ! preg_match('~\A[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_-]+)*\.png\z~D', $c['archivo'] ?? '') ||
                ! is_int($c['ancho'] ?? null) || ! is_int($c['alto'] ?? null) ||
                $c['ancho'] < 2 || $c['alto'] < 2 || config('capas.max_pixeles') < $c['ancho'] * $c['alto']) {
                throw new UnexpectedValueException('Cuadro inválido.');
            }
            $fecha = is_string($c['instante'] ?? null) ? DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $c['instante']) : false;
            if (! $fecha || $fecha->format('Y-m-d\TH:i:s\Z') !== $c['instante'] || isset($fechas[$c['capa'].'/'.$c['instante']])) {
                throw new UnexpectedValueException('Fecha inválida o duplicada.');
            }
            foreach (['fuente', 'producto'] as $campo) {
                if (! is_string($c[$campo] ?? null) || ! trim($c[$campo]) || mb_strlen($c[$campo]) > 160 || preg_match('/[\x00-\x1f]/', $c[$campo])) {
                    throw new UnexpectedValueException('Procedencia obligatoria.');
                }
            }
            $r = $c['rejilla'] ?? [];
            foreach (['oeste', 'este', 'sur', 'norte'] as $campo) {
                if (! is_int($r[$campo] ?? null) && ! is_float($r[$campo] ?? null) || ! is_finite((float) $r[$campo])) {
                    throw new UnexpectedValueException('Extensión inválida.');
                }
            }
            if (($r['proyeccion'] ?? null) !== 'EPSG:4326' || ! in_array($r['filas'] ?? null, ['sur-norte', 'norte-sur'], true) ||
                ! in_array($r['registro'] ?? null, ['centros', 'nodos'], true) ||
                $r['oeste'] < -180 || $r['este'] > 360 || $r['oeste'] >= $r['este'] || $r['este'] - $r['oeste'] > 360 ||
                $r['sur'] < -90 || $r['norte'] > 90 || $r['sur'] >= $r['norte']) {
                throw new UnexpectedValueException('Rejilla inválida.');
            }
            $ids[$c['id']] = true;
            $fechas[$c['capa'].'/'.$c['instante']] = true;
        }
        $cuadros = $datos['cuadros'];
        usort($cuadros, fn ($a, $b) => strcmp($a['instante'], $b['instante']));

        return $cuadros;
    }

    public function imagen(string $id): string
    {
        $c = collect($this->cargar()['cuadros'])->firstWhere('id', $id);
        if (! $c) {
            throw new ErrorConsulta('CUADRO_NO_ENCONTRADO', 'Esta imagen no está disponible. Elige otra fecha.', 404);
        }
        try {
            $bytes = ArchivoRaster::leer(ArchivoRaster::ruta(config('capas.directorio'), $c['archivo']));
            if (! hash_equals($c['sha256'], hash('sha256', $bytes)) || ArchivoRaster::dimensiones($bytes) !== [$c['ancho'], $c['alto']]) {
                throw new UnexpectedValueException;
            }

            return $bytes;
        } catch (\Throwable) {
            throw new ErrorConsulta('CUADRO_NO_DISPONIBLE', 'No se pudo cargar esta imagen. Elige otra fecha.', 503);
        }
    }
}
