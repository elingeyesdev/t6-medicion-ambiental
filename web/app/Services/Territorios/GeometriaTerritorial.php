<?php

namespace App\Services\Territorios;

final class GeometriaTerritorial
{
    private array $municipios = [];

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
        $poligonos = $g['type'] === 'Polygon' ? [$g['coordinates']] : $g['coordinates'];
        foreach ($poligonos as $p) {
            $exterior = $this->enAnillo($p[0], $x, $y);
            if ($exterior === 2) {
                return true;
            }
            if ($exterior === 0) {
                continue;
            }
            $enHueco = false;
            foreach (array_slice($p, 1) as $hueco) {
                $interior = $this->enAnillo($hueco, $x, $y);
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

    /** Interior estricto para muestras: excluye bordes exteriores y de huecos. */
    public function interiorEstricto(array $g, float $x, float $y): bool
    {
        $poligonos = $g['type'] === 'Polygon' ? [$g['coordinates']] : $g['coordinates'];
        foreach ($poligonos as $p) {
            if ($this->enAnillo($p[0], $x, $y) !== 1) {
                continue;
            }
            $enHueco = false;
            foreach (array_slice($p, 1) as $hueco) {
                $enHueco = $enHueco || $this->enAnillo($hueco, $x, $y) !== 0;
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
                $this->municipios[] = [$f['id'], $f['geometry'], $this->caja($f['geometry'])];
            }
        }
        $coinciden = [];
        foreach ($this->municipios as [$id, $g, $b]) {
            if ($x >= $b[0] && $y >= $b[1] && $x <= $b[2] && $y <= $b[3] && $this->contiene($g, $x, $y)) {
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

    // 0 fuera, 1 dentro, 2 borde; tolerancia numérica de 1e-10 grados, sin buffer territorial.
    private function enAnillo(array $anillo, float $x, float $y): int
    {
        $dentro = false;
        for ($i = 0, $j = count($anillo) - 1; $i < count($anillo); $j = $i++) {
            [$ax, $ay] = $anillo[$j];
            [$bx, $by] = $anillo[$i];
            $dx = $bx - $ax;
            $dy = $by - $ay;
            $largo = hypot($dx, $dy);
            if ($largo > 0 && abs(($x - $ax) * $dy - ($y - $ay) * $dx) <= 1e-10 * $largo
                && $x >= min($ax, $bx) - 1e-10 && $x <= max($ax, $bx) + 1e-10
                && $y >= min($ay, $by) - 1e-10 && $y <= max($ay, $by) + 1e-10) {
                return 2;
            }
            if (($ay > $y) !== ($by > $y) && $x < ($bx - $ax) * ($y - $ay) / ($by - $ay) + $ax) {
                $dentro = ! $dentro;
            }
        }

        return $dentro ? 1 : 0;
    }
}
