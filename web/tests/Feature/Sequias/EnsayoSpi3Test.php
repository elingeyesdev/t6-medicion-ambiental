<?php

namespace Tests\Feature\Sequias;

use App\Services\Sequias\Experimental\EnsayoSpi3;
use App\Services\Sequias\RepositorioSeriesPrecipitacion;
use App\Services\Sequias\SeriePrecipitacionInvalida;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EnsayoSpi3Test extends TestCase
{
    private string $directorio;

    private int $numero = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directorio = base_path('../tmp/tests-spi-'.bin2hex(random_bytes(12)));
        mkdir($this->directorio, 0700, true);
        config(['sequias.directorio_series' => $this->directorio.'/almacen']);
        app()->forgetInstance(RepositorioSeriesPrecipitacion::class);
    }

    protected function tearDown(): void
    {
        $ruta = realpath($this->directorio);
        $padre = realpath(base_path('../tmp'));
        if ($ruta !== false) {
            if ($padre === false || ! str_starts_with($ruta, $padre.DIRECTORY_SEPARATOR)
                || ! preg_match('/^tests-spi-[a-f0-9]{24}$/D', basename($ruta))) {
                throw new \LogicException('Ruta de limpieza ajena al ensayo.');
            }
            File::deleteDirectory($ruta);
        }
        parent::tearDown();
    }

    private function serie(string $desde, string $hasta, array $p = [], ?string $nulo = null, ?string $ausente = null): array
    {
        $diarios = ['time' => [], 'precipitation_sum' => []];
        for ($d = new DateTimeImmutable($desde); $d <= new DateTimeImmutable($hasta); $d = $d->modify('+1 day')) {
            $fecha = $d->format('Y-m-d');
            if ($fecha === $ausente) {
                continue;
            }
            $diarios['time'][] = $fecha;
            $diarios['precipitation_sum'][] = $fecha === $nulo ? null : ($d->format('d') === $d->format('t') ? 10 + (int) $d->format('Y') % 11 + (int) $d->format('m') : 0);
        }
        $json = json_encode(['utc_offset_seconds' => 0, 'daily_units' => ['precipitation_sum' => 'mm'], 'daily' => $diarios], JSON_THROW_ON_ERROR);
        $ruta = $this->directorio.'/serie-'.(++$this->numero).'.json';
        file_put_contents($ruta, $json);
        $declaracion = json_decode(file_get_contents(base_path('../docs/ejemplos/sequias/procedencia-completa.json')), true, flags: JSON_THROW_ON_ERROR);
        $declaracion['periodo'] = ['desde' => $desde, 'hastaIncluido' => $hasta, 'zona' => 'UTC'];
        $declaracion['sha256'] = hash('sha256', $json);

        return app(RepositorioSeriesPrecipitacion::class)->preparar($ruta, [...$declaracion, ...$p]);
    }

    private function ensayar(array $base, array $objetivo, string $mes = '2025-01', ?DateTimeImmutable $ahora = null): array
    {
        return app(EnsayoSpi3::class)->ejecutar($base['identificador'], $objetivo['identificador'], $mes,
            $ahora ?? new DateTimeImmutable('2026-10-23T00:00:00Z'));
    }

    private function error(string $codigo, callable $accion): void
    {
        try {
            $accion();
            $this->fail('El ensayo aceptó una entrada incompatible.');
        } catch (SeriePrecipitacionInvalida $e) {
            $this->assertSame($codigo, $e->codigo);
        }
        Http::assertNothingSent();
    }

    public function test_ventanas_del_mismo_mes_cruzan_anio_y_preservan_procedencia_sin_red_ni_escritura(): void
    {
        $base = $this->serie('1990-11-01', '2020-12-31');
        $objetivo = $this->serie('2024-11-01', '2025-01-31');
        $antes = hash_file('sha256', $this->directorio.'/almacen/'.$base['identificador'].'/manifiesto.json');
        $r = $this->ensayar($base, $objetivo);
        $this->assertSame('ensayo_pendiente_revision', $r['estado']);
        $this->assertSame('controlada_simulacion', $r['naturaleza']);
        $this->assertSame('2024-11-01', $r['periodo']['desde']);
        $this->assertSame('2025-01-31', $r['periodo']['hastaIncluido']);
        $this->assertCount(30, $r['base']['ventanas']);
        $this->assertSame(1991, $r['base']['ventanas'][0]['anioFin']);
        $this->assertSame((float) ((10 + 1990 % 11 + 11) + (10 + 1990 % 11 + 12) + (10 + 1991 % 11 + 1)), $r['base']['ventanas'][0]['acumuladoMm']);
        $this->assertNotNull($r['resultado']['valor']);
        $this->assertSame($base['procedencia'], $r['versiones'][0]['procedencia']);
        $this->assertSame('verificada', $r['versiones'][1]['integridad']);
        $this->assertSame($antes, hash_file('sha256', $this->directorio.'/almacen/'.$base['identificador'].'/manifiesto.json'));
        Http::assertNothingSent();
    }

    public function test_mes_nulo_o_ausente_no_se_imputa_y_base_no_relacionada_no_invalida_otro_mes(): void
    {
        $objetivo = $this->serie('2024-11-01', '2025-01-31');
        $faltante = $this->serie('1990-11-01', '2020-12-31', nulo: '1998-12-15');
        $r = $this->ensayar($faltante, $objetivo);
        $this->assertSame('BASE_INCOMPLETA', $r['resultado']['motivo']);
        $this->assertSame(29, $r['resultado']['aniosValidos']);
        $this->assertNull($r['base']['ventanas'][8]['acumuladoMm']);
        $fueraDeVentana = $this->serie('1990-11-01', '2020-12-31', nulo: '1998-06-15');
        $this->assertNotNull($this->ensayar($fueraDeVentana, $objetivo)['resultado']['valor']);
        $incompleto = $this->serie('2024-11-01', '2025-01-31', ausente: '2024-12-15');
        $r = $this->ensayar($fueraDeVentana, $incompleto);
        $this->assertSame('PERIODO_INCOMPLETO', $r['resultado']['motivo']);
        $this->assertNull($r['precipitacionAcumuladaMm']);
    }

    public function test_enero_1991_necesita_los_meses_previos_de_1990_y_mes_entero_no_recibido_es_null(): void
    {
        $base = $this->serie('1991-01-01', '2020-12-31');
        $objetivo = $this->serie('2025-01-01', '2025-01-31');
        $r = $this->ensayar($base, $objetivo);
        $this->assertSame(29, $r['resultado']['aniosValidos']);
        $this->assertNull($r['base']['ventanas'][0]['acumuladoMm']);
        $this->assertNull($r['precipitacionAcumuladaMm']);
    }

    public function test_no_mezcla_celdas_muestras_productos_fuentes_o_tipos_de_evidencia(): void
    {
        $base = $this->serie('1990-11-01', '2020-12-31');
        foreach (['muestra' => 'otra', 'proveedor' => 'otro', 'producto' => 'otro',
            'coordenadaCelda' => [-63.5, -17.75], 'coordenadaSolicitada' => [-63.1, -17.75]] as $campo => $valor) {
            $objetivo = $this->serie('2024-11-01', '2025-01-31', [$campo => $valor]);
            $this->error('SERIES_INCOMPATIBLES', fn () => $this->ensayar($base, $objetivo));
        }
        $externo = $this->serie('2024-11-01', '2025-01-31', ['tipoEvidencia' => 'captura_externa',
            'naturaleza' => 'reanalisis', 'urlOrigen' => 'https://example.org/fixture']);
        $this->error('SERIES_INCOMPATIBLES', fn () => $this->ensayar($base, $externo));
        $baseExterna = $this->serie('1990-11-01', '2020-12-31', ['tipoEvidencia' => 'captura_externa',
            'naturaleza' => 'reanalisis', 'urlOrigen' => 'https://example.org/fixture']);
        $this->error('PRODUCTO_NO_PROPUESTO', fn () => $this->ensayar($baseExterna, $externo));
    }

    public function test_rechaza_mes_diario_actual_futuro_o_anterior_a_2021_en_utc(): void
    {
        foreach (['2024-1', '2024-13', '2024-01-01', '2020-12', '2026-10', '9999-01'] as $mes) {
            $this->error('MES_NO_ADMITIDO', fn () => app(EnsayoSpi3::class)->ejecutar('no_leer', 'no_leer', $mes, new DateTimeImmutable('2026-10-01T00:00:00Z')));
        }
        // En Bolivia aún septiembre, pero el corte de la propuesta es UTC.
        $base = $this->serie('1990-11-01', '2020-12-31');
        $objetivo = $this->serie('2026-07-01', '2026-09-30');
        $r = $this->ensayar($base, $objetivo, '2026-09', new DateTimeImmutable('2026-09-30T20:30:00', new DateTimeZone('America/La_Paz')));
        $this->assertNotNull($r['resultado']['valor']);
    }

    public function test_consola_codigos_cero_uno_dos_y_endpoint_conservado_en_503(): void
    {
        $base = $this->serie('1990-11-01', '2020-12-31');
        $objetivo = $this->serie('2024-11-01', '2025-01-31');
        $args = ['base' => $base['identificador'], 'objetivo' => $objetivo['identificador'], 'mes' => '2025-01'];
        $this->assertSame(0, Artisan::call('sequias:ensayar-spi', $args));
        $salida = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('ensayo_pendiente_revision', $salida['estado']);
        $this->assertNotNull($salida['resultado']['valor']);
        $incompleto = $this->serie('2025-01-01', '2025-01-31');
        $this->assertSame(1, Artisan::call('sequias:ensayar-spi', [...$args, 'objetivo' => $incompleto['identificador']]));
        $salida = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('PERIODO_INCOMPLETO', $salida['resultado']['motivo']);
        $this->assertSame(2, Artisan::call('sequias:ensayar-spi', [...$args, 'base' => '../otro']));
        $this->assertArrayHasKey('error', json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR));
        $this->getJson('/api/v1/inundaciones')->assertStatus(503)->assertJsonPath('error.codigo', 'MODULO_NO_DISPONIBLE');
        Http::assertNothingSent();
    }
}
