<?php

namespace Tests\Feature\Sequias;

use App\Services\Sequias\SeriePrecipitacionInvalida;
use App\Services\Sequias\ValidadorSeriePrecipitacion;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ValidadorSeriePrecipitacionTest extends TestCase
{
    public static function serie(string $desde = '2024-02-01', string $hasta = '2024-02-29', float $valor = 1.0): array
    {
        $fechas = [];
        for ($dia = new DateTimeImmutable($desde, new DateTimeZone('UTC')); $dia->format('Y-m-d') <= $hasta; $dia = $dia->modify('+1 day')) {
            $fechas[] = $dia->format('Y-m-d');
        }

        return ['utc_offset_seconds' => 0, 'daily_units' => ['precipitation_sum' => 'mm'],
            'daily' => ['time' => $fechas, 'precipitation_sum' => array_fill(0, count($fechas), $valor)]];
    }

    public function test_bisiesto_y_cero_conocido_generan_un_mes_completo(): void
    {
        $r = (new ValidadorSeriePrecipitacion)->validar(self::serie(valor: 0), '2024-02-01', '2024-02-29');
        $this->assertTrue($r['aptoParaAgregacionMensual']);
        $this->assertSame('completa', $r['estado']);
        $this->assertSame(29, $r['resumen']['diasValidos']);
        $this->assertSame(0.0, $r['meses'][0]['precipitacionAcumuladaMm']);
        $this->assertArrayNotHasKey('spi', $r);
    }

    public function test_febrero_no_bisiesto_y_cambio_de_anio_conservan_meses_independientes(): void
    {
        $v = new ValidadorSeriePrecipitacion;
        $r = $v->validar(self::serie('2023-02-01', '2023-02-28'), '2023-02-01', '2023-02-28');
        $this->assertSame(28.0, $r['meses'][0]['precipitacionAcumuladaMm']);
        $r = $v->validar(self::serie('2023-12-01', '2024-01-31'), '2023-12-01', '2024-01-31');
        $this->assertSame(['2023-12', '2024-01'], array_column($r['meses'], 'mes'));
        $this->assertSame([31.0, 31.0], array_column($r['meses'], 'precipitacionAcumuladaMm'));
    }

    public function test_dias_ausentes_y_null_se_distinguen_e_invalidan_solo_su_mes(): void
    {
        $s = self::serie('2024-01-01', '2024-02-29');
        $s['daily']['precipitation_sum'][5] = null;
        array_shift($s['daily']['time']);
        array_shift($s['daily']['precipitation_sum']);
        $r = (new ValidadorSeriePrecipitacion)->validar($s, '2024-01-01', '2024-02-29');
        $this->assertFalse($r['aptoParaAgregacionMensual']);
        $this->assertSame('incompleta', $r['estado']);
        $this->assertSame(1, $r['resumen']['diasAusentes']);
        $this->assertSame(1, $r['resumen']['diasNulos']);
        $this->assertSame(['2024-01-01'], $r['meses'][0]['fechasAusentes']);
        $this->assertSame(['2024-01-06'], $r['meses'][0]['fechasNulas']);
        $this->assertNull($r['meses'][0]['precipitacionAcumuladaMm']);
        $this->assertSame(29.0, $r['meses'][1]['precipitacionAcumuladaMm']);
    }

    public function test_un_mes_entero_ausente_no_desaparece_del_informe(): void
    {
        $r = (new ValidadorSeriePrecipitacion)->validar(self::serie(), '2024-01-01', '2024-02-29');
        $this->assertSame(31, $r['resumen']['diasAusentes']);
        $this->assertSame(2, $r['resumen']['mesesEsperados']);
        $this->assertSame(1, $r['resumen']['mesesCompletos']);
        $this->assertNull($r['meses'][0]['precipitacionAcumuladaMm']);
    }

    public function test_serie_vacia_con_periodo_declarado_conserva_calendario_y_faltantes(): void
    {
        $s = self::serie();
        $s['daily'] = ['time' => [], 'precipitation_sum' => []];
        $r = (new ValidadorSeriePrecipitacion)->validar($s, '2024-02-01', '2024-02-29');
        $this->assertSame('sin_valores', $r['estado']);
        $this->assertSame(29, $r['resumen']['diasAusentes']);
        $this->assertNull($r['meses'][0]['precipitacionAcumuladaMm']);
    }

    public function test_serie_completamente_null_no_se_declara_sin_lluvia(): void
    {
        $s = self::serie();
        $s['daily']['precipitation_sum'] = array_fill(0, 29, null);
        $r = (new ValidadorSeriePrecipitacion)->validar($s, '2024-02-01', '2024-02-29');
        $this->assertSame('sin_valores', $r['estado']);
        $this->assertSame(29, $r['resumen']['diasNulos']);
        $this->assertSame(0, $r['resumen']['diasAusentes']);
        $this->assertNull($r['meses'][0]['precipitacionAcumuladaMm']);
    }

    public function test_decimales_se_suman_sin_redondear_diariamente(): void
    {
        $r = (new ValidadorSeriePrecipitacion)->validar(self::serie(valor: 0.1234), '2024-02-01', '2024-02-29');
        $this->assertEqualsWithDelta(3.5786, $r['meses'][0]['precipitacionAcumuladaMm'], 1e-12);
    }

    #[DataProvider('documentosInvalidos')]
    public function test_rechaza_serie_ambigua_o_invalida(array $serie, string $codigo): void
    {
        try {
            (new ValidadorSeriePrecipitacion)->validar($serie, '2024-02-01', '2024-02-29');
            $this->fail('La serie inválida fue aceptada.');
        } catch (SeriePrecipitacionInvalida $e) {
            $this->assertSame($codigo, $e->codigo);
        }
    }

    public static function documentosInvalidos(): array
    {
        $casos = [];
        foreach ([-1, true, '2.5', INF, NAN, []] as $i => $valor) {
            $s = self::serie();
            $s['daily']['precipitation_sum'][0] = $valor;
            $casos['valor_'.$i] = [$s, 'PRECIPITACION_INVALIDA'];
        }
        $s = self::serie();
        $s['daily_units']['precipitation_sum'] = 'inch';
        $casos['unidad'] = [$s, 'UNIDAD_INVALIDA'];
        unset($s['daily_units']);
        $casos['sin_unidad'] = [$s, 'UNIDAD_INVALIDA'];
        $s = self::serie();
        $s['utc_offset_seconds'] = -14400;
        $casos['zona'] = [$s, 'ZONA_INVALIDA'];
        $s = self::serie();
        $s['daily']['precipitation_sum'] = [1];
        $casos['longitudes'] = [$s, 'ESTRUCTURA_INVALIDA'];
        $s['daily']['time'] = '2024-02-01';
        $casos['no_lista'] = [$s, 'ESTRUCTURA_INVALIDA'];
        $s = self::serie();
        $s['daily']['time'][1] = '2024-02-01';
        $casos['duplicado'] = [$s, 'FECHA_DUPLICADA'];
        $s['daily']['time'][0] = '2024-02-02';
        $casos['desordenado'] = [$s, 'ORDEN_FECHAS_INVALIDO'];
        foreach (['2024-02-30', '2024-2-01', '2024-02-01T00:00:00Z', 1, '0000-02-01'] as $i => $fecha) {
            $s = self::serie();
            $s['daily']['time'][0] = $fecha;
            $casos['fecha_'.$i] = [$s, 'FECHA_INVALIDA'];
        }
        $s = self::serie();
        $s['daily']['time'][0] = '2024-01-31';
        $casos['fuera_de_rango'] = [$s, 'FECHA_FUERA_DE_RANGO'];
        $s = self::serie(valor: PHP_FLOAT_MAX);
        $casos['desbordamiento'] = [$s, 'ACUMULACION_FUERA_DE_RANGO'];

        return $casos;
    }

    #[DataProvider('rangosInvalidos')]
    public function test_rechaza_rangos_imposibles_parciales_y_excesivos(string $desde, string $hasta, string $codigo): void
    {
        try {
            (new ValidadorSeriePrecipitacion)->validar(self::serie(), $desde, $hasta);
            $this->fail('El rango inválido fue aceptado.');
        } catch (SeriePrecipitacionInvalida $e) {
            $this->assertSame($codigo, $e->codigo);
        }
    }

    public static function rangosInvalidos(): array
    {
        return [
            ['2024-02-30', '2024-03-31', 'FECHA_INVALIDA'],
            ['2024-02-02', '2024-02-29', 'RANGO_NO_MENSUAL'],
            ['2024-02-01', '2024-02-28', 'RANGO_NO_MENSUAL'],
            ['2024-03-01', '2024-02-29', 'RANGO_NO_MENSUAL'],
            ['1900-01-01', '2024-12-31', 'RANGO_DEMASIADO_GRANDE'],
        ];
    }
}
