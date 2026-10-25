<?php

namespace Tests\Feature\Sequias;

use App\Services\Sequias\Experimental\NumericaGammaNormal;
use App\Services\Sequias\Experimental\Spi3Propuesto;
use App\Services\Sequias\SeriePrecipitacionInvalida;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class Spi3NumericoTest extends TestCase
{
    private static function oraculo(): array
    {
        return json_decode(file_get_contents(__DIR__.'/../../../../docs/ejemplos/sequias/spi3-oraculo.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    public static function referencias(): iterable
    {
        foreach (self::oraculo()['casos'] as $caso) {
            yield $caso['id'] => [$caso];
        }
    }

    #[DataProvider('referencias')]
    public function test_coincide_con_oraculo_scipy_independiente(array $caso): void
    {
        $r = app(Spi3Propuesto::class)->calcular($caso['base'], $caso['objetivo']);
        $e = $caso['esperado'];
        $this->assertNull($r['motivo']);
        $this->assertEqualsWithDelta($e['valor'], $r['valor'], 1e-6);
        $this->assertEqualsWithDelta($e['probabilidadAcumulada'], $r['probabilidadAcumulada'], 1e-8);
        foreach (['forma', 'escalaMm', 'probabilidadCero'] as $campo) {
            $this->assertEqualsWithDelta($e[$campo], $r['parametrosAjuste'][$campo], 1e-7 * max(1, abs($e[$campo])));
        }
    }

    public function test_gamma_y_normal_se_contrastan_con_funciones_scipy_en_centro_y_colas(): void
    {
        $n = new NumericaGammaNormal;
        foreach (self::oraculo()['gamma'] as $e) {
            [$p, $q] = $n->gamma($e['forma'], $e['x']);
            $this->assertEqualsWithDelta($e['p'], $p, 1e-8);
            $this->assertEqualsWithDelta($e['q'], $q, 1e-8);
        }
        foreach (self::oraculo()['normal'] as $e) {
            $this->assertEqualsWithDelta($e['z'], $n->normalInversa($e['p']), 1e-10);
        }
        // Gamma(1,x): distribución exponencial con CDF analítica.
        $this->assertEqualsWithDelta(0.5, $n->gamma(1, log(2))[0], 1e-13);
        $this->assertEqualsWithDelta(0.0, $n->normalInversa(0.5), 1e-14);
    }

    public function test_reutilizar_ajuste_preparado_conserva_los_nueve_casos_de_referencia(): void
    {
        $spi = app(Spi3Propuesto::class);
        foreach (self::oraculo()['casos'] as $caso) {
            $primero = $spi->calcular($caso['base'], $caso['objetivo']);
            $reusado = $spi->calcular($caso['base'], $caso['objetivo'], $primero['parametrosAjuste']);
            $this->assertSame($primero, $reusado);
            $this->assertEqualsWithDelta($caso['esperado']['valor'], $reusado['valor'], 1e-6);
        }
    }

    public function test_ajuste_preparado_incompatible_o_no_finito_se_rechaza(): void
    {
        $spi = app(Spi3Propuesto::class);
        $parametros = $spi->calcular(range(1, 30), 20)['parametrosAjuste'];
        foreach ([['forma' => INF], ['escalaMm' => -1], ['positivos' => 29], ['probabilidadCero' => 0.5]] as $cambio) {
            try {
                $spi->calcular(range(1, 30), 20, [...$parametros, ...$cambio]);
                $this->fail('Aceptó un ajuste incompatible.');
            } catch (SeriePrecipitacionInvalida $e) {
                $this->assertSame('AJUSTE_PREPARADO_INVALIDO', $e->codigo);
            }
        }
    }

    public function test_null_y_extremos_no_se_convierten_en_indice_normal_ni_en_valor_recortado(): void
    {
        $spi = app(Spi3Propuesto::class);
        foreach ([['BASE_INCOMPLETA', array_fill(0, 29, 1), 1],
            ['BASE_INCOMPLETA', [...range(1, 29), null], 1],
            ['PERIODO_INCOMPLETO', range(1, 30), null],
            ['BASE_NO_AJUSTABLE', array_fill(0, 30, 0), 1],
            ['BASE_NO_AJUSTABLE', array_fill(0, 30, 1), 1],
            ['BASE_NO_AJUSTABLE', [...array_fill(0, 21, 0), ...range(1, 9)], 1],
            ['PROBABILIDAD_EXTREMA', range(1, 30), 0],
            ['PROBABILIDAD_EXTREMA', range(1, 30), 1e100],
            ['AJUSTE_FALLIDO', array_map(fn ($n) => 100 + $n * 1e-5, range(1, 30)), 100]] as [$motivo, $base, $objetivo]) {
            $r = $spi->calcular($base, $objetivo);
            $this->assertSame($motivo, $r['motivo']);
            $this->assertNull($r['valor']);
            $this->assertNull($r['categoria']);
        }
    }

    public function test_entradas_no_finitas_negativas_o_textos_se_rechazan_en_ambos_lados(): void
    {
        foreach ([-1, NAN, INF, true, '2', []] as $invalido) {
            foreach ([[...range(1, 29), $invalido], range(1, 30)] as $i => $base) {
                try {
                    app(Spi3Propuesto::class)->calcular($base, $i === 0 ? 5 : $invalido);
                    $this->fail('Acumulación inválida aceptada.');
                } catch (SeriePrecipitacionInvalida $e) {
                    $this->assertSame('ACUMULACION_INVALIDA', $e->codigo);
                }
            }
        }
    }

    public function test_fronteras_se_clasifican_sin_redondear(): void
    {
        foreach ([[-2.00001, 'extremadamente_seco'], [-2, 'extremadamente_seco'],
            [-1.99999, 'muy_seco'], [-1.5, 'muy_seco'], [-1.49999, 'moderadamente_seco'],
            [-1, 'moderadamente_seco'], [-0.99999, 'normal'], [1, 'normal'],
            [1.00001, 'moderadamente_humedo'], [1.5, 'moderadamente_humedo'],
            [1.50001, 'muy_humedo'], [2, 'muy_humedo'], [2.00001, 'extremadamente_humedo']] as [$valor, $categoria]) {
            $this->assertSame($categoria, app(Spi3Propuesto::class)->clasificar($valor));
        }
    }
}
