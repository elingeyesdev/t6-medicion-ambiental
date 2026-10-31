<?php

namespace Tests\Feature;

use App\Services\Territorios\CatalogoTerritorial;
use App\Services\Territorios\GeometriaTerritorial;
use PHPUnit\Framework\TestCase;

class GeometriaTerritorialTest extends TestCase
{
    private function servicio(): GeometriaTerritorial
    {
        return new GeometriaTerritorial(new CatalogoTerritorial(dirname(__DIR__, 3).'/compartido/territorios/datos'));
    }

    private function cuadrado(float $x = 0, float $y = 0): array
    {
        return [[$x, $y], [$x + 10, $y], [$x + 10, $y + 10], [$x, $y + 10], [$x, $y]];
    }

    public function test_cambiar_los_huecos_no_reutiliza_el_limite_anterior(): void
    {
        $g = $this->servicio();
        $sinHueco = ['type' => 'Polygon', 'coordinates' => [$this->cuadrado()]];
        $conHueco = ['type' => 'Polygon', 'coordinates' => [$this->cuadrado(), [[2, 2], [4, 2], [4, 4], [2, 4], [2, 2]]]];
        $this->assertTrue($g->contiene($sinHueco, 3, 3));
        $this->assertFalse($g->contiene($conHueco, 3, 3));
        $this->assertFalse($g->interiorEstricto($conHueco, 3, 3));
        $this->assertTrue($g->contiene($conHueco, 2, 3));
        $this->assertFalse($g->interiorEstricto($conHueco, 2, 3));
        $this->assertTrue($g->contiene($sinHueco, 3, 3));
    }

    public function test_cambiar_otra_parte_del_multipoligono_actualiza_la_seleccion(): void
    {
        $g = $this->servicio();
        $a = ['type' => 'MultiPolygon', 'coordinates' => [[$this->cuadrado()], [$this->cuadrado(20)]]];
        $b = ['type' => 'MultiPolygon', 'coordinates' => [[$this->cuadrado()], [$this->cuadrado(40)]]];
        $this->assertTrue($g->contiene($a, 25, 5));
        $this->assertFalse($g->contiene($b, 25, 5));
        $this->assertTrue($g->contiene($b, 45, 5));
        $this->assertFalse($g->interiorEstricto($b, 40, 5));
    }

    public function test_cambiar_un_vertice_intermedio_tambien_cambia_el_limite(): void
    {
        $g = $this->servicio();
        $a = ['type' => 'Polygon', 'coordinates' => [[[0, 0], [5, 0], [10, 0], [10, 5], [10, 10], [5, 10], [0, 10], [0, 5], [0, 0]]]];
        $b = $a;
        $b['coordinates'][0][1] = [5, 8];
        $this->assertTrue($g->contiene($a, 5, 2));
        $this->assertFalse($g->contiene($b, 5, 2));
        $this->assertTrue($g->contiene($a, 5, 2));
    }

    public function test_las_cubetas_conservan_la_regla_original_en_bordes_y_latitudes_negativas(): void
    {
        $g = $this->servicio();
        $forma = ['type' => 'Polygon', 'coordinates' => [$this->cuadrado(-10, -10), [[-8, -8], [-6, -8], [-6, -6], [-8, -6], [-8, -8]]]];
        foreach ([-10.01, -10, -9.95, -8, -7.999999999, -7, -6, -0.05, 0, 0.01] as $y) {
            foreach ([-10.01, -10, -9, -8, -7, -6, -1, 0, 0.01] as $x) {
                $exterior = $this->anillo($forma['coordinates'][0], $x, $y);
                $hueco = $this->anillo($forma['coordinates'][1], $x, $y);
                $this->assertSame($exterior === 2 || ($exterior === 1 && $hueco !== 1), $g->contiene($forma, $x, $y), "Punto $x,$y");
                $this->assertSame($exterior === 1 && $hueco === 0, $g->interiorEstricto($forma, $x, $y));
            }
        }
    }

    private function anillo(array $anillo, float $x, float $y): int
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
