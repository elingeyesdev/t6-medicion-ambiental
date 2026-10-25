<?php

namespace Tests\Feature\Sequias;

use App\Services\Sequias\Experimental\CalculoSpi3ConCache;
use App\Services\Sequias\RepositorioSeriesPrecipitacion;
use App\Services\Sequias\SeriePrecipitacionInvalida;
use DateTimeImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CacheCalculoSpi3Test extends TestCase
{
    private string $directorio;

    private array $base;

    private array $objetivo;

    private int $numero = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-23T12:00:00Z'));
        $this->directorio = base_path('../tmp/tests-cache-spi-'.bin2hex(random_bytes(12)));
        mkdir($this->directorio, 0700, true);
        config(['sequias.directorio_series' => $this->directorio.'/almacen']);
        app()->forgetInstance(RepositorioSeriesPrecipitacion::class);
        Cache::flush();
        $this->base = $this->serie('1990-11-01', '2020-12-31');
        $this->objetivo = $this->serie('2024-10-01', '2025-01-31');
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        $ruta = realpath($this->directorio);
        $padre = realpath(base_path('../tmp'));
        if ($ruta !== false) {
            if ($padre === false || ! str_starts_with($ruta, $padre.DIRECTORY_SEPARATOR)
                || ! preg_match('/^tests-cache-spi-[a-f0-9]{24}$/D', basename($ruta))) {
                throw new \LogicException('Ruta de limpieza ajena a esta prueba.');
            }
            File::deleteDirectory($ruta);
        }
        Http::assertNothingSent();
        parent::tearDown();
    }

    private function serie(string $desde, string $hasta, ?string $nulo = null, array $p = []): array
    {
        $daily = ['time' => [], 'precipitation_sum' => []];
        for ($d = new DateTimeImmutable($desde); $d <= new DateTimeImmutable($hasta); $d = $d->modify('+1 day')) {
            $fecha = $d->format('Y-m-d');
            $daily['time'][] = $fecha;
            $daily['precipitation_sum'][] = $fecha === $nulo ? null : ($d->format('d') === $d->format('t') ? 10 + (int) $d->format('Y') % 11 + (int) $d->format('m') : 0);
        }
        $json = json_encode(['utc_offset_seconds' => 0, 'daily_units' => ['precipitation_sum' => 'mm'], 'daily' => $daily], JSON_THROW_ON_ERROR);
        $ruta = $this->directorio.'/serie-'.(++$this->numero).'.json';
        file_put_contents($ruta, $json);
        $procedencia = json_decode(file_get_contents(base_path('../docs/ejemplos/sequias/procedencia-completa.json')), true, flags: JSON_THROW_ON_ERROR);
        $procedencia['periodo'] = ['desde' => $desde, 'hastaIncluido' => $hasta, 'zona' => 'UTC'];
        $procedencia['sha256'] = hash('sha256', $json);

        return app(RepositorioSeriesPrecipitacion::class)->preparar($ruta, [...$procedencia, ...$p]);
    }

    private function calcular(bool $recalcular = false, ?array $objetivo = null, string $mes = '2025-01', ?array $base = null): array
    {
        return app(CalculoSpi3ConCache::class)->ejecutar(($base ?? $this->base)['identificador'],
            ($objetivo ?? $this->objetivo)['identificador'], $mes, $recalcular);
    }

    private function clave(array $r): string
    {
        return 'sequias:resultado:v1:'.$r['cacheCalculo']['huella'];
    }

    private function error(string $codigo, callable $accion): void
    {
        try {
            $accion();
            $this->fail('Debió rechazar la entrada o caché.');
        } catch (SeriePrecipitacionInvalida $e) {
            $this->assertSame($codigo, $e->codigo);
        }
    }

    private function guardarAlterado(string $clave, array $r): void
    {
        unset($r['checksum']);
        $r['checksum'] = hash('sha256', json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));
        Cache::put($clave, $r, 86400);
    }

    public function test_reutiliza_sin_renovar_fecha_datos_y_conserva_calculo_y_motor(): void
    {
        $a = $this->calcular();
        $this->travel(60)->seconds();
        $b = $this->calcular();
        $this->assertTrue($a['cacheCalculo']['esCalculoNuevo']);
        $this->assertFalse($b['cacheCalculo']['esCalculoNuevo']);
        $this->assertSame('cache_calculo', $b['cacheCalculo']['tipo']);
        $this->assertSame($a['resultado'], $b['resultado']);
        $this->assertSame($a['versiones'], $b['versiones']);
        $this->assertSame($a['cacheCalculo']['calculadoEn'], $b['cacheCalculo']['calculadoEn']);
        $this->assertSame(60, $b['cacheCalculo']['antiguedadCalculoSegundos']);
        $this->assertSame($this->base['procedencia']['obtenidoEn'], $b['versiones'][0]['procedencia']['obtenidoEn']);
        foreach ($b['cacheCalculo']['motor']['archivosSha256LF'] as $nombre => $hash) {
            $this->assertSame(hash('sha256', str_replace("\r\n", "\n", file_get_contents(app_path('Services/Sequias/Experimental/'.$nombre)))), $hash);
        }
        $this->assertNotNull($b['resultado']['valor']);
    }

    public function test_recalcular_y_caducar_renuevan_solo_resultado_y_liberan_bloqueo(): void
    {
        $a = $this->calcular();
        $this->travel(1)->seconds();
        $b = $this->calcular(true);
        $this->assertSame($a['resultado'], $b['resultado']);
        $this->assertSame($a['versiones'], $b['versiones']);
        $this->assertSame($a['cacheCalculo']['huella'], $b['cacheCalculo']['huella']);
        $this->assertNotSame($a['cacheCalculo']['calculadoEn'], $b['cacheCalculo']['calculadoEn']);
        $this->travel(86400)->seconds();
        $c = $this->calcular();
        $this->assertTrue($c['cacheCalculo']['esCalculoNuevo']);
        $this->assertSame($a['resultado'], $c['resultado']);
        $lock = Cache::lock($this->clave($a).':bloqueo', 120);
        $this->assertTrue($lock->get());
        $lock->release();
    }

    public function test_null_con_motivo_caduca_a_los_quince_minutos_sin_imputar(): void
    {
        $incompleta = $this->serie('2024-11-01', '2025-01-31', '2024-12-15');
        $a = $this->calcular(objetivo: $incompleta);
        $this->assertNull($a['resultado']['valor']);
        $this->assertSame('PERIODO_INCOMPLETO', $a['resultado']['motivo']);
        $this->assertSame(900.0, Carbon::parse($a['cacheCalculo']['calculadoEn'])->diffInSeconds(Carbon::parse($a['cacheCalculo']['expiraEn'])));
        $this->travel(899)->seconds();
        $this->assertFalse($this->calcular(objetivo: $incompleta)['cacheCalculo']['esCalculoNuevo']);
        $this->travel(1)->seconds();
        $this->assertTrue($this->calcular(objetivo: $incompleta)['cacheCalculo']['esCalculoNuevo']);
    }

    public function test_versions_y_mes_tienen_claves_distintas_sin_borrar_resultados_anteriores(): void
    {
        $a = $this->calcular();
        $nuevaObjetivo = $this->serie('2024-10-01', '2025-01-31', p: ['obtenidoEn' => '2025-02-01T00:00:00Z']);
        $nuevaBase = $this->serie('1990-11-01', '2020-12-31', p: ['obtenidoEn' => '2025-02-01T00:00:00Z']);
        foreach ([$this->calcular(objetivo: $nuevaObjetivo), $this->calcular(base: $nuevaBase), $this->calcular(mes: '2024-12')] as $b) {
            $this->assertNotSame($a['cacheCalculo']['huella'], $b['cacheCalculo']['huella']);
            $this->assertTrue($b['cacheCalculo']['esCalculoNuevo']);
        }
        $this->assertFalse($this->calcular()['cacheCalculo']['esCalculoNuevo']);
    }

    public function test_captura_alterada_no_se_oculta_con_resultado_en_cache_ni_recalcular(): void
    {
        $a = $this->calcular();
        file_put_contents($this->directorio.'/almacen/'.$this->objetivo['identificador'].'/captura.json', '{}');
        $this->error('SERIE_CORRUPTA', fn () => $this->calcular());
        $this->error('SERIE_CORRUPTA', fn () => $this->calcular(true));
        $this->assertNotNull(Cache::get($this->clave($a)));
    }

    public function test_resultado_corrupto_no_se_repara_silenciosamente_y_recalcular_lo_rehace(): void
    {
        $a = $this->calcular();
        $clave = $this->clave($a);
        Cache::put($clave, 'invalido', 86400);
        $this->error('CACHE_CALCULO_INVALIDA', fn () => $this->calcular());
        $b = $this->calcular(true);
        $this->assertTrue($b['cacheCalculo']['esCalculoNuevo']);
        $this->assertSame($a['resultado'], $b['resultado']);
        $guardado = Cache::get($clave);
        $guardado['informe']['resultado']['valor'] = 42;
        Cache::put($clave, $guardado, 86400);
        $this->error('CACHE_CALCULO_INVALIDA', fn () => $this->calcular());
    }

    public function test_checksum_no_suple_contexto_motor_fechas_y_estructura_del_resultado(): void
    {
        $a = $this->calcular();
        $clave = $this->clave($a);
        $original = Cache::get($clave);
        foreach (['contexto', 'motor', 'fecha', 'duracion', 'estructura', 'categoria', 'parametros'] as $caso) {
            $r = $original;
            match ($caso) {
                'contexto' => $r['informe']['muestra'] = 'otra',
                'motor' => $r['motor']['version'] = 'otro',
                'fecha' => $r['calculadoEn'] = '2099-01-01T00:00:00Z',
                'duracion' => $r['expiraEn'] = '2026-10-23T12:10:00Z',
                'estructura' => $r['informe']['resultado'] = ['valor' => 0, 'categoria' => 'normal', 'motivo' => null, 'a' => 1, 'b' => 2, 'c' => 3],
                'categoria' => $r['informe']['resultado']['categoria'] = 'extremadamente_seco',
                'parametros' => $r['informe']['resultado']['parametrosAjuste']['forma'] = -1,
            };
            $this->guardarAlterado($clave, $r);
            $this->error('CACHE_CALCULO_INVALIDA', fn () => $this->calcular());
        }
        // Caducidad del contenido validada aunque el almacén retenga el registro.
        Cache::put($clave, $original, 90000);
        $this->travel(86400)->seconds();
        $this->assertTrue($this->calcular()['cacheCalculo']['esCalculoNuevo']);
    }

    public function test_otro_proceso_con_bloqueo_no_produce_calculo_duplicado(): void
    {
        $a = $this->calcular();
        $lock = Cache::lock($this->clave($a).':bloqueo', 120);
        $this->assertTrue($lock->get());
        try {
            $this->error('CALCULO_OCUPADO', fn () => $this->calcular(true));
        } finally {
            $lock->release();
        }
        $this->assertFalse($this->calcular()['cacheCalculo']['esCalculoNuevo']);
    }

    public function test_error_al_guardar_no_declara_exito_y_libera_bloqueo_sin_modificar_series(): void
    {
        $a = $this->calcular();
        $clave = $this->clave($a);
        $cache = \Mockery::mock(Cache::store());
        $cache->shouldReceive('put')->once()->with($clave, \Mockery::type('array'), 86400)->andReturn(false);
        Cache::swap($cache);
        $this->error('CACHE_CALCULO_NO_DISPONIBLE', fn () => $this->calcular(true));
        $lock = Cache::lock($clave.':bloqueo', 120);
        $this->assertTrue($lock->get());
        $lock->release();
        $this->assertSame($a['versiones'][0]['procedencia'], app(RepositorioSeriesPrecipitacion::class)->leer($this->base['identificador'])['procedencia']);
    }

    public function test_original_ausente_no_se_sustituye_por_cache(): void
    {
        $this->calcular();
        unlink($this->directorio.'/almacen/'.$this->base['identificador'].'/captura.json');
        $this->error('SERIE_CORRUPTA', fn () => $this->calcular());
    }

    public function test_consola_con_cache_recalcular_null_error_y_ruta_sin_habilitar(): void
    {
        $args = ['base' => $this->base['identificador'], 'objetivo' => $this->objetivo['identificador'], 'mes' => '2025-01', '--cache' => true];
        $this->assertSame(0, Artisan::call('sequias:ensayar-spi', $args));
        $this->assertTrue(json_decode(Artisan::output(), true)['cacheCalculo']['esCalculoNuevo']);
        $this->assertSame(0, Artisan::call('sequias:ensayar-spi', $args));
        $this->assertFalse(json_decode(Artisan::output(), true)['cacheCalculo']['esCalculoNuevo']);
        unset($args['--cache']);
        $this->assertSame(0, Artisan::call('sequias:ensayar-spi', [...$args, '--recalcular' => true]));
        $this->assertTrue(json_decode(Artisan::output(), true)['cacheCalculo']['esCalculoNuevo']);
        $incompleta = $this->serie('2025-01-01', '2025-01-31');
        $this->assertSame(1, Artisan::call('sequias:ensayar-spi', [...$args, '--cache' => true, 'objetivo' => $incompleta['identificador']]));
        $this->assertSame('PERIODO_INCOMPLETO', json_decode(Artisan::output(), true)['resultado']['motivo']);
        $this->assertSame(2, Artisan::call('sequias:ensayar-spi', [...$args, '--cache' => true, 'mes' => '2026-10']));
        $this->assertSame('MES_NO_ADMITIDO', json_decode(Artisan::output(), true)['error']['codigo']);
        $this->getJson('/api/v1/inundaciones')->assertStatus(503)->assertJsonPath('error.codigo', 'MODULO_NO_DISPONIBLE');
    }
}
