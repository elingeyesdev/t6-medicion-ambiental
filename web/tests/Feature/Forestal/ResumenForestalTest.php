<?php

declare(strict_types=1);

namespace Tests\Feature\Forestal;

use App\Services\Api\ErrorConsulta;
use App\Services\Forestal\ResumenForestal;
use PHPUnit\Framework\TestCase;

final class ResumenForestalTest extends TestCase
{
    private function base(): array
    {
        return json_decode(file_get_contents(dirname(__DIR__, 4).'/compartido/cobertura-forestal/ejemplo-propuesto.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_coincide_con_los_dos_resultados_de_python(): void
    {
        $base = $this->base();
        $servicio = new ResumenForestal;
        $completo = $servicio->acotar($base, 2001, 2025);
        $this->assertSame($base['serie'], $completo['serie']);
        $this->assertSame($base['resumen'], $completo['resumen']);
        $python = json_decode(file_get_contents(dirname(__DIR__, 4).'/docs/forestal147/evidencias/2024-2025.json'), true, flags: JSON_THROW_ON_ERROR);
        $actual = $servicio->acotar($base, 2024, 2025);
        foreach (['consulta', 'serie', 'resumen'] as $campo) {
            $this->assertSame($python[$campo], $actual[$campo]);
        }
    }

    public function test_conserva_identidades_y_no_modifica_la_entrada(): void
    {
        $base = $this->base();
        $antes = $base;
        $r = (new ResumenForestal)->acotar($base, 2024, 2025);
        foreach (['fuente', 'territorio', 'criterio', 'cobertura', 'capa', 'limites', 'estado_metodo', 'integrado_en_api', 'unidad', 'decimales'] as $campo) {
            $this->assertSame($base[$campo], $r[$campo]);
        }
        $this->assertFalse($r['criterio']['adoptado']);
        $this->assertSame($antes, $base);
    }

    public function test_cero_y_faltante_tienen_totales_diferentes(): void
    {
        $s = new ResumenForestal;
        $base = $this->base();
        $base['serie'][0]['perdida_ha'] = '0.00';
        $r = $s->acotar($base, 2001, 2001);
        $this->assertSame('0.00', $r['resumen']['total_periodo_ha']);
        $base['serie'][0] = ['anio' => 2001, 'estado' => 'no_informado', 'perdida_ha' => null];
        $r = $s->acotar($base, 2001, 2002);
        $this->assertNull($r['resumen']['total_periodo_ha']);
        $this->assertSame('118225.4508', $r['resumen']['subtotal_informado_ha']);
        $this->assertSame([2001], $r['resumen']['anios_sin_dato']);
        array_shift($base['serie']);
        $r = $s->acotar($base, 2001, 2001);
        $this->assertNull($r['resumen']['subtotal_informado_ha']);
        $this->assertSame([['anio' => 2001, 'estado' => 'no_informado', 'perdida_ha' => null]], $r['serie']);
        $this->assertSame('1720254.57894', $s->acotar($base, 2024, 2025)['resumen']['total_periodo_ha']);
    }

    public function test_suma_exacta_con_acarreo_y_mas_de_28_cifras(): void
    {
        foreach ([
            ['123456789012345678901234567890.123456789123456789', '0.000000000000000001', '123456789012345678901234567890.123456789123456790'],
            ['9.99', '0.01', '10.00'],
            ['0.1', '0.002', '0.102'],
            ['9999999999999999999999999999999999999999', '1', '10000000000000000000000000000000000000000'],
        ] as [$a, $b, $total]) {
            $base = $this->base();
            $base['serie'][0]['perdida_ha'] = $a;
            $base['serie'][1]['perdida_ha'] = $b;
            $this->assertSame($total, (new ResumenForestal)->acotar($base, 2001, 2002)['resumen']['total_periodo_ha']);
        }
    }

    public function test_rechaza_series_corruptas_incluso_fuera_de_la_ventana(): void
    {
        $cambios = [
            fn (&$p) => $p['serie'][] = $p['serie'][0],
            fn (&$p) => $p['serie'][0]['anio'] = 2026,
            fn (&$p) => $p['serie'][0]['anio'] = true,
            fn (&$p) => $p['serie'][0]['estado'] = 'otro',
            fn (&$p) => $p['serie'][0]['perdida_ha'] = '-1',
            fn (&$p) => $p['serie'][0]['perdida_ha'] = 'NaN',
            fn (&$p) => $p['serie'][0]['perdida_ha'] = '1e3',
            fn (&$p) => $p['serie'][0]['perdida_ha'] = true,
            fn (&$p) => $p['serie'][0]['perdida_ha'] = 1.5,
            fn (&$p) => $p['serie'][0]['perdida_ha'] = null,
            fn (&$p) => $p['serie'][0]['perdida_ha'] = '01',
            fn (&$p) => $p['serie'][0]['perdida_ha'] = str_repeat('9', 41),
            fn (&$p) => $p['serie'][0]['perdida_ha'] = '0.'.str_repeat('1', 31),
            fn (&$p) => $p['serie'][0]['estado'] = 'no_informado',
            fn (&$p) => $p['serie'] = array_combine(range(1, 25), $p['serie']),
            fn (&$p) => $p['unidad'] = 'km2',
            fn (&$p) => $p['capa'] = ['type' => 'FeatureCollection', 'features' => []],
            fn (&$p) => $p['fuente']['anios'][] = 2001,
        ];
        foreach ($cambios as $cambio) {
            $p = $this->base();
            $cambio($p);
            try {
                (new ResumenForestal)->acotar($p, 2024, 2025);
                $this->fail('Se aceptó una serie corrupta.');
            } catch (ErrorConsulta $e) {
                $this->assertSame('PRODUCTO_INVALIDO', $e->codigo);
                $this->assertSame(503, $e->estado);
            }
        }
    }

    public function test_no_reutiliza_totales_anteriores_ni_acepta_anios_sin_publicar(): void
    {
        $base = $this->base();
        $base['resumen']['total_periodo_ha'] = '999';
        $s = new ResumenForestal;
        $this->assertSame('1720254.57894', $s->acotar($base, 2024, 2025)['resumen']['total_periodo_ha']);
        foreach ([[2025, 2024], [2000, 2001], [2025, 2026]] as [$desde, $hasta]) {
            try {
                $s->acotar($base, $desde, $hasta);
                $this->fail('Se aceptó una consulta fuera de versión.');
            } catch (ErrorConsulta $e) {
                $this->assertSame('FILTROS_INVALIDOS', $e->codigo);
            }
        }
        $base['fuente']['anios'] = [2001, 2003];
        $this->expectException(ErrorConsulta::class);
        $s->acotar($base, 2001, 2003);
    }
}
