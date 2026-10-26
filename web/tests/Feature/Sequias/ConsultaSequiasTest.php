<?php

namespace Tests\Feature\Sequias;

use App\Services\Sequias\AlmacenPublicacionesSequias;
use App\Services\Sequias\Experimental\CatalogoMuestrasSequias;
use App\Services\Sequias\PreparadorConsultaSequias;
use App\Services\Sequias\RepositorioSeriesPrecipitacion;
use App\Services\Sequias\SeriePrecipitacionInvalida;
use DateTimeImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ConsultaSequiasTest extends TestCase
{
    private string $directorio;

    private int $numero = 0;

    private const CONSULTA = '/api/v1/sequias?mesFinal=2024-09&muestra=scz-piloto-01';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-25T14:00:00Z'));
        $this->directorio = base_path('../tmp/tests-api-sequias-'.bin2hex(random_bytes(12)));
        mkdir($this->directorio, 0700, true);
        config(['sequias.directorio_series' => $this->directorio.'/series', 'sequias.directorio_publicaciones' => $this->directorio.'/publicaciones']);
        app()->forgetInstance(RepositorioSeriesPrecipitacion::class);
        app()->forgetInstance(AlmacenPublicacionesSequias::class);
        Cache::flush();
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        $ruta = realpath($this->directorio);
        $padre = realpath(base_path('../tmp'));
        if ($ruta !== false) {
            if ($padre === false || ! str_starts_with($ruta, $padre.DIRECTORY_SEPARATOR) || ! preg_match('/^tests-api-sequias-[a-f0-9]{24}$/D', basename($ruta))) {
                throw new \LogicException('Ruta de limpieza ajena a la prueba.');
            }
            File::deleteDirectory($ruta);
        }
        parent::tearDown();
    }

    /** Datos controlados, aunque simulen el formato del proveedor; nunca evidencia externa. */
    private function serie(string $desde, string $hasta, string $muestra = 'scz-piloto-01', ?string $nulo = null, array $p = []): array
    {
        $s = app(CatalogoMuestrasSequias::class)->consultar(muestra: $muestra)['datos'][0];
        $daily = ['time' => [], 'precipitation_sum' => []];
        for ($d = new DateTimeImmutable($desde); $d <= new DateTimeImmutable($hasta); $d = $d->modify('+1 day')) {
            $fecha = $d->format('Y-m-d');
            $daily['time'][] = $fecha;
            $daily['precipitation_sum'][] = $fecha === $nulo ? null : ($d->format('d') === $d->format('t') ? 10 + (int) $d->format('Y') % 11 + (int) $d->format('m') : 0);
        }
        $json = json_encode(['utc_offset_seconds' => 0, 'daily_units' => ['precipitation_sum' => 'mm'], 'daily' => $daily], JSON_THROW_ON_ERROR);
        $archivo = $this->directorio.'/serie-'.(++$this->numero).'.json';
        file_put_contents($archivo, $json);
        $declaracion = ['version' => 'procedencia-precipitacion-1.0', 'tipoEvidencia' => 'captura_externa',
            'proveedor' => 'Open-Meteo', 'producto' => 'ERA5', 'naturaleza' => 'reanalisis', 'muestra' => $muestra,
            'coordenadaSolicitada' => $s['coordenadaSolicitada'], 'coordenadaCelda' => [-63.25, -17.75],
            'periodo' => ['desde' => $desde, 'hastaIncluido' => $hasta, 'zona' => 'UTC'], 'obtenidoEn' => now()->utc()->format('Y-m-d\TH:i:s.u\Z'),
            'urlOrigen' => 'https://archive-api.open-meteo.com/v1/archive?fixture=controlada',
            'licencia' => 'Fixture de pruebas', 'atribucion' => 'TODOS LOS DATOS SON CONTROLADOS: sin descarga ni observación real.', 'sha256' => hash('sha256', $json)];

        return app(RepositorioSeriesPrecipitacion::class)->preparar($archivo, [...$declaracion, ...$p]);
    }

    private function publicar(?string $nulo = null, ?string $nuloBase = null): array
    {
        $base = $this->serie('1990-11-01', '2020-12-31', nulo: $nuloBase);
        $objetivo = $this->serie('2024-07-01', '2024-09-30', nulo: $nulo);

        return app(PreparadorConsultaSequias::class)->ejecutar('scz-piloto-01', '2024-09', $base['identificador'], $objetivo['identificador']);
    }

    public function test_consulta_detalle_y_resumen_preservan_el_mismo_resultado_sin_red(): void
    {
        $pub = $this->publicar();
        $r = $this->getJson(self::CONSULTA)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('versionContrato', 'sequias-1.0-m3-candidata')->assertJsonPath('consulta.estado', 'metodo_acordado')
            ->assertJsonPath('consulta.provincia', null)->assertJsonPath('datos.0.territorioContexto', 'BO070101')
            ->assertJsonPath('datos.0.municipioDelCentroCelda', 'BO070103')->assertJsonPath('consulta.obtencion.tipo', 'cache')
            ->assertJsonPath('consulta.obtencion.esRespuestaHttp', true)->assertJsonPath('resumen.totalMuestras', 1)
            ->assertJsonPath('resumen.muestrasConIndice', 1)->assertJsonPath('cobertura.superficieEvaluadaKm2', null)
            ->assertJsonPath('representacionMapa.rellenarPoligonos', false)->json();
        $this->assertEquals($pub['datos']['informe']['resultado'], $r['datos'][0]['spi']);
        $detalle = $this->getJson('/api/v1/sequias/scz-piloto-01?mesFinal=2024-09')->assertOk()->json();
        $resumen = $this->getJson('/api/v1/sequias/resumen?mesFinal=2024-09&muestra=scz-piloto-01')->assertOk()->json();
        $this->assertSame($r['datos'], $detalle['datos']);
        $this->assertSame($r['resumen'], $resumen['resumen']);
        $this->assertArrayNotHasKey('datos', $resumen);
        $this->assertSame(2, count($r['fuentes'][0]['capturas']));
        Http::assertNothingSent();
    }

    public function test_cobertura_parcial_no_presenta_una_muestra_como_56_o_superficie_municipal(): void
    {
        $this->publicar();
        $r = $this->getJson('/api/v1/sequias?mesFinal=2024-09')->assertOk()->assertJsonCount(1, 'datos')
            ->assertJsonPath('cobertura.muestrasSeleccionadas', 56)->assertJsonCount(55, 'cobertura.muestrasSinPreparar')
            ->assertJsonPath('cobertura.municipiosEnFiltro', 56)->assertJsonPath('resumen.totalMuestras', 1)->json();
        $this->assertStringContainsString('Cobertura parcial', implode(' ', $r['advertencias']));
        Http::assertNothingSent();
    }

    public function test_null_con_motivo_y_cero_conocido_no_se_confunden_con_normal(): void
    {
        $this->publicar(nulo: '2024-08-15');
        $this->getJson(self::CONSULTA)->assertOk()->assertJsonPath('datos.0.spi.valor', null)
            ->assertJsonPath('datos.0.spi.categoria', null)->assertJsonPath('datos.0.spi.motivo', 'PERIODO_INCOMPLETO')
            ->assertJsonPath('resumen.muestrasSinIndice', 1)->assertJsonPath('resumen.spiMinimo', null);
        $this->travel(900)->seconds();
        $this->getJson(self::CONSULTA)->assertStatus(503)->assertJsonPath('error.codigo', 'PERIODO_NO_DISPONIBLE');
    }

    public function test_ventana_base_incompleta_conserva_29_anios_y_null(): void
    {
        $this->publicar(nuloBase: '1998-08-15');
        $this->getJson(self::CONSULTA)->assertOk()->assertJsonPath('datos.0.spi.motivo', 'BASE_INCOMPLETA')
            ->assertJsonPath('datos.0.spi.aniosValidos', 29)->assertJsonPath('datos.0.spi.valor', null);
    }

    public function test_vencimiento_no_renueva_datos_en_http_ni_republicando(): void
    {
        $r = $this->publicar();
        $this->travel(86400)->seconds();
        $this->getJson(self::CONSULTA)->assertStatus(503)->assertJsonPath('error.codigo', 'PERIODO_NO_DISPONIBLE');
        $this->expectException(SeriePrecipitacionInvalida::class);
        try {
            app(PreparadorConsultaSequias::class)->ejecutar('scz-piloto-01', '2024-09', $r['datos']['base'], $r['datos']['objetivo']);
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_original_alterado_y_publicacion_alterada_no_se_entregan(): void
    {
        $r = $this->publicar();
        $archivo = $this->directorio.'/series/'.$r['datos']['objetivo'].'/captura.json';
        $original = file_get_contents($archivo);
        file_put_contents($archivo, '{}');
        $this->getJson(self::CONSULTA)->assertStatus(503)->assertJsonPath('error.codigo', 'DATOS_INVALIDOS');
        file_put_contents($archivo, $original);
        file_put_contents($this->directorio.'/publicaciones/versiones/'.$r['publicacion'].'.json', '{}');
        $this->getJson(self::CONSULTA)->assertStatus(503)->assertJsonPath('error.codigo', 'DATOS_INVALIDOS');
        Http::assertNothingSent();
    }

    public function test_muestra_sin_preparar_no_descarga_historia_al_visitar_api(): void
    {
        $this->getJson(self::CONSULTA)->assertStatus(503)->assertJsonPath('error.codigo', 'MUESTRAS_NO_DISPONIBLES')
            ->assertJsonStructure(['error' => ['codigo', 'mensaje', 'identificadorConsulta', 'campos']]);
        Http::assertNothingSent();
    }

    #[DataProvider('filtrosInvalidos')]
    public function test_filtros_invalidos_se_rechazan_antes_de_leer_o_descargar(string $query, int $estado, string $codigo): void
    {
        $this->getJson('/api/v1/sequias'.$query)->assertStatus($estado)->assertJsonPath('error.codigo', $codigo);
        Http::assertNothingSent();
    }

    public static function filtrosInvalidos(): array
    {
        return [
            ['', 422, 'FILTROS_INVALIDOS'], ['?mesFinal=2024-9', 422, 'FILTROS_INVALIDOS'],
            ['?mesFinal=2026-10', 422, 'FILTROS_INVALIDOS'], ['?mesFinal=2020-12', 422, 'FILTROS_INVALIDOS'],
            ['?mesFinal=2024-09&mesFinal=2024-08', 422, 'FILTROS_INVALIDOS'], ['?mesFinal[]=2024-09', 422, 'FILTROS_INVALIDOS'],
            ['?mesFinal=2024-09&desde=2024-07-01', 422, 'FILTROS_INVALIDOS'], ['?mesFinal=', 422, 'FILTROS_INVALIDOS'],
            ['?mesFinal=2024-09&provincia=xxx', 422, 'FILTROS_INVALIDOS'], ['?mesFinal=2024-09&municipio=BO079999', 404, 'TERRITORIO_NO_DISPONIBLE'],
            ['?mesFinal=2024-09&muestra=no-existe', 404, 'MUESTRA_NO_DISPONIBLE'],
            ['?mesFinal=2024-09&provincia=BO0702&municipio=BO070101', 422, 'FILTROS_INCOMPATIBLES'],
            ['?mesFinal=2024-09&municipio=BO070103&muestra=scz-piloto-01', 422, 'FILTROS_INCOMPATIBLES'],
        ];
    }

    public function test_detalle_rechaza_muestra_contradictoria_y_conserva_405_y_429(): void
    {
        $this->getJson('/api/v1/sequias/scz-piloto-01?mesFinal=2024-09&muestra=otra')->assertStatus(422)->assertJsonPath('error.codigo', 'FILTROS_INCOMPATIBLES');
        $this->postJson('/api/v1/sequias')->assertStatus(405)->assertJsonPath('error.codigo', 'METODO_NO_ADMITIDO');
        for ($n = 0; $n < 30; $n++) {
            $this->getJson(self::CONSULTA);
        }
        $this->getJson(self::CONSULTA)->assertStatus(429)->assertHeader('Retry-After')->assertJsonPath('error.codigo', 'SOLICITUDES_EXCESIVAS');
    }

    public function test_preparacion_reutiliza_base_por_celda_para_otro_punto_sin_descargarla(): void
    {
        $primera = $this->publicar();
        $s = app(CatalogoMuestrasSequias::class)->consultar(municipio: 'BO070103')['datos'][0];
        $objetivo = $this->serie('2024-07-01', '2024-09-30', $s['identificador']);
        $segunda = app(PreparadorConsultaSequias::class)->ejecutar($s['identificador'], '2024-09', null, $objetivo['identificador']);
        $this->assertSame($primera['datos']['base'], $segunda['datos']['base']);
        $this->assertSame($primera['datos']['ajustes'], $segunda['datos']['ajustes']);
        $this->getJson('/api/v1/sequias?mesFinal=2024-09&provincia=BO0701')->assertOk()
            ->assertJsonPath('resumen.totalMuestras', 2)->assertJsonPath('cobertura.celdasUnicas', 1)->assertJsonCount(3, 'cobertura.muestrasSinPreparar');
        $ajustes = app(AlmacenPublicacionesSequias::class)->leer('ajustes', $primera['datos']['ajustes']);
        $this->assertCount(12, $ajustes['meses']);
        $this->assertSame($primera['datos']['informe']['resultado']['parametrosAjuste'], $ajustes['meses'][9]['parametrosAjuste']);
        Http::assertNothingSent();
    }

    public function test_preparador_no_publica_celdas_incompatibles(): void
    {
        $base = $this->serie('1990-11-01', '2020-12-31');
        $objetivo = $this->serie('2024-07-01', '2024-09-30', p: ['coordenadaCelda' => [-64.0, -17.75]]);
        $this->expectException(SeriePrecipitacionInvalida::class);
        app(PreparadorConsultaSequias::class)->ejecutar('scz-piloto-01', '2024-09', $base['identificador'], $objetivo['identificador']);
    }

    public function test_error_proveedor_no_sustituye_publicacion_vigente_por_fixture_o_vacio(): void
    {
        $primera = $this->publicar();
        Http::fake(['archive-api.open-meteo.com/*' => Http::response('no disponible', 503)]);
        $this->assertSame(2, Artisan::call('sequias:preparar-consulta', ['muestra' => 'scz-piloto-01', 'mes' => '2024-09', '--consultar' => true, '--renovar' => true]));
        $this->assertSame('PROVEEDOR_NO_DISPONIBLE', json_decode(Artisan::output(), true)['error']['codigo']);
        $this->getJson(self::CONSULTA)->assertOk()->assertJsonPath('datos.0.spi.valor', $primera['datos']['informe']['resultado']['valor']);
        Http::assertSentCount(1);
    }

    public function test_renovacion_sustituye_indice_al_final_y_preserva_version_anterior_y_ajustes(): void
    {
        $a = $this->publicar();
        $this->travel(1)->seconds();
        $objetivo = $this->serie('2024-07-01', '2024-09-30');
        $b = app(PreparadorConsultaSequias::class)->ejecutar('scz-piloto-01', '2024-09', null, $objetivo['identificador']);
        $this->assertNotSame($a['publicacion'], $b['publicacion']);
        $this->assertSame($a['datos']['ajustes'], $b['datos']['ajustes']);
        $this->assertFileExists($this->directorio.'/publicaciones/versiones/'.$a['publicacion'].'.json');
        $this->getJson(self::CONSULTA)->assertOk()->assertJsonPath('consulta.obtencion.obtenidoEn', $objetivo['procedencia']['obtenidoEn']);
        Http::assertNothingSent();
    }

    public function test_codigo_o_catalogo_distinto_requiere_repreparar_y_no_oculta_el_problema(): void
    {
        $r = $this->publicar();
        $r['datos']['motor']['version'] = 'otro-motor';
        app(AlmacenPublicacionesSequias::class)->guardar('consultas', PreparadorConsultaSequias::clave('scz-piloto-01', '2024-09'), $r['datos']);
        $this->getJson(self::CONSULTA)->assertStatus(503)->assertJsonPath('error.codigo', 'DATOS_INVALIDOS');
    }

    public function test_simulacion_no_se_publica_como_captura_externa(): void
    {
        $p = ['tipoEvidencia' => 'controlada', 'naturaleza' => 'simulacion', 'urlOrigen' => null];
        $base = $this->serie('1990-11-01', '2020-12-31', p: $p);
        $objetivo = $this->serie('2024-07-01', '2024-09-30', p: $p);
        $this->expectException(SeriePrecipitacionInvalida::class);
        app(PreparadorConsultaSequias::class)->ejecutar('scz-piloto-01', '2024-09', $base['identificador'], $objetivo['identificador']);
    }

    public function test_carga_proveedor_global_ocupada_no_envia_consultas(): void
    {
        $lock = Cache::lock('sequias:carga:proveedor', 120);
        $this->assertTrue($lock->get());
        try {
            $this->assertSame(2, Artisan::call('sequias:preparar-consulta', ['muestra' => 'scz-piloto-01', 'mes' => '2024-09', '--consultar' => true]));
            $this->assertSame('CARGA_OCUPADA', json_decode(Artisan::output(), true)['error']['codigo']);
            Http::assertNothingSent();
        } finally {
            $lock->release();
        }
    }
}
