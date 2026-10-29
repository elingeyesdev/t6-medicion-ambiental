<?php

namespace App\Services\Territorios;

final class GeometriaTerritorial
{
    private const PASO_LATITUD = 0.05;

    private const TOLERANCIA = 1e-10;

    private array $municipios = [];

    private ?string $firmaActiva = null;

    private ?array $indiceActivo = null;

    public function __construct(private readonly CatalogoTerritorial $catalogo) {}

    public function caja(array $geometria): array
    {
        $caja = [180.0, 90.0, -180.0, -90.0];
        $poligonos = $geometria['type'] === 'Polygon' ? [$geometria['coordinates']] : $geometria['coordinates'];
        foreach ($poligonos as $poligono) {
            foreach ($poligono[0] as [$x, $y]) {
                $caja = [min($caja[0], $x), min($caja[1], $y), max($caja[2], $x), max($caja[3], $y)];
            }
        }

        return $caja;
    }

    public function contiene(array $g, float $x, float $y): bool
    {
        return $this->contieneIndice($this->indice($g), $x, $y);
    }

    /** Interior estricto para muestras: excluye bordes exteriores y de huecos. */
    public function interiorEstricto(array $g, float $x, float $y): bool
    {
        foreach ($this->indice($g) as $p) {
            if ($this->clasificar($p['exterior'], $x, $y) !== 1) {
                continue;
            }
            $enHueco = false;
            foreach ($p['huecos'] as $hueco) {
                $enHueco = $enHueco || $this->clasificar($hueco, $x, $y) !== 0;
            }
            if (! $enHueco) {
                return true;
            }
        }

        return false;
    }

    public function asignar(float $x, float $y): array
    {
        if ($this->municipios === []) {
            // Cargar una sola vez la capa compartida y descartar por caja antes del polígono.
            $ruta = config('focos.datos_territoriales').'/limites/municipios.geojson';
            $capa = json_decode(file_get_contents($ruta), true, 512, JSON_THROW_ON_ERROR);
            if (($capa['versionCatalogo'] ?? null) !== $this->catalogo->metadatos()['version']) {
                throw new \RuntimeException('Versiones territoriales incompatibles.');
            }
            foreach ($capa['features'] as $f) {
                $this->municipios[] = [$f['id'], $this->construir($f['geometry']), $this->caja($f['geometry'])];
            }
        }
        $coinciden = [];
        foreach ($this->municipios as [$id, $indice, $b]) {
            if ($x >= $b[0] && $y >= $b[1] && $x <= $b[2] && $y <= $b[3] && $this->contieneIndice($indice, $x, $y)) {
                $coinciden[] = $this->catalogo->buscar($id);
            }
        }
        $padres = array_unique(array_column($coinciden, 'padre'));

        return [
            'provincia' => count($padres) === 1 ? reset($padres) : null,
            'municipio' => count($coinciden) === 1 ? $coinciden[0]['identificador'] : null,
            'motivoAsignacion' => count($coinciden) === 1 ? null : (count($coinciden) > 1 ? 'frontera_compartida' : 'sin_coincidencia_municipal'),
        ];
    }

    private function contieneIndice(array $indice, float $x, float $y): bool
    {
        foreach ($indice as $p) {
            $exterior = $this->clasificar($p['exterior'], $x, $y);
            if ($exterior === 2) {
                return true;
            }
            if ($exterior === 0) {
                continue;
            }
            $enHueco = false;
            foreach ($p['huecos'] as $hueco) {
                $interior = $this->clasificar($hueco, $x, $y);
                if ($interior === 2) {
                    return true;
                }
                $enHueco = $enHueco || $interior === 1;
            }
            if (! $enHueco) {
                return true;
            }
        }

        return false;
    }

    private function indice(array $g): array
    {
        $firma = $this->firma($g);
        if ($this->firmaActiva === $firma && $this->indiceActivo !== null) {
            return $this->indiceActivo;
        }
        $construido = $this->construir($g);
        $this->firmaActiva = $firma;
        $this->indiceActivo = $construido;

        return $construido;
    }

    private function firma(array $g): string
    {
        $poligonos = $g['type'] === 'Polygon' ? [$g['coordinates']] : $g['coordinates'];
        $anillo = $poligonos[0][0] ?? [];
        $n = count($anillo);
        if ($n === 0) {
            return $g['type'].'|0';
        }
        $muestras = '';
        foreach ([0, intdiv($n, 4), intdiv($n, 2), intdiv(3 * $n, 4), $n - 1] as $i) {
            $muestras .= '|'.$anillo[$i][0].'|'.$anillo[$i][1];
        }

        return $g['type'].'|'.count($poligonos).'|'.$n.$muestras;
    }

    private function construir(array $g): array
    {
        $poligonos = $g['type'] === 'Polygon' ? [$g['coordinates']] : $g['coordinates'];
        $salida = [];
        foreach ($poligonos as $p) {
            $huecos = [];
            foreach (array_slice($p, 1) as $hueco) {
                $huecos[] = $this->cubetas($hueco);
            }
            $salida[] = ['exterior' => $this->cubetas($p[0]), 'huecos' => $huecos];
        }

        return $salida;
    }

    private function cubetas(array $anillo): array
    {
        $cubetas = [];
        $n = count($anillo);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $ax = (float) $anillo[$j][0];
            $ay = (float) $anillo[$j][1];
            $bx = (float) $anillo[$i][0];
            $by = (float) $anillo[$i][1];
            $desde = (int) floor((min($ay, $by) - 1e-8) / self::PASO_LATITUD);
            $hasta = (int) floor((max($ay, $by) + 1e-8) / self::PASO_LATITUD);
            for ($k = $desde; $k <= $hasta; $k++) {
                $cubetas[$k][] = [$ax, $ay, $bx, $by];
            }
        }

        return $cubetas;
    }

    // 0 fuera, 1 dentro, 2 borde; tolerancia numérica de 1e-10 grados, sin buffer territorial.
    private function clasificar(array $cubetas, float $x, float $y): int
    {
        $aristas = $cubetas[(int) floor($y / self::PASO_LATITUD)] ?? [];
        $dentro = false;
        foreach ($aristas as [$ax, $ay, $bx, $by]) {
            if ($x >= min($ax, $bx) - self::TOLERANCIA && $x <= max($ax, $bx) + self::TOLERANCIA
                && $y >= min($ay, $by) - self::TOLERANCIA && $y <= max($ay, $by) + self::TOLERANCIA) {
                $dx = $bx - $ax;
                $dy = $by - $ay;
                $largo = hypot($dx, $dy);
                if ($largo > 0 && abs(($x - $ax) * $dy - ($y - $ay) * $dx) <= self::TOLERANCIA * $largo) {
                    return 2;
                }
            }
            if (($ay > $y) !== ($by > $y) && $x < ($bx - $ax) * ($y - $ay) / ($by - $ay) + $ax) {
                $dentro = ! $dentro;
            }
        }

        return $dentro ? 1 : 0;
    }
}
