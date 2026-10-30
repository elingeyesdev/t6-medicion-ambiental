<?php

namespace Tests\Feature;

use App\Models\ReporteAmbiental;
use App\Models\SimulacionContinua;
use App\Models\User;
use App\Services\Datos\CatalogoAmbiental;
use App\Services\Reportes\GraficoReporte;
use App\Services\Reportes\VariablesReporte;
use App\Services\Simulaciones\ContinuarSimulacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportesAtmosferaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        config(['reportes.atmosfera.habilitado' => true]);
    }

    private function cliente(string $rol = 'analista'): User
    {
        $u = User::factory()->create(['rol' => $rol]);
        Sanctum::actingAs($u, ['cuenta']);

        return $u;
    }

    private function entrada(array $extra = []): array
    {
        return $extra + ['titulo' => 'Pronóstico controlado', 'base' => 'atmosfera', 'puntos' => ['santacruz'],
            'variables' => ['temperatura', 'lluvia', 'viento', 'visibilidad', 'nieve', 'nieve_acumulada', 'aqi'], 'horas' => 3];
    }

    private function respuesta(string $codigo = 'santacruz'): array
    {
        $hora = now('UTC')->startOfHour()->timestamp;
        $h = ['time' => [$hora, $hora + 3600, $hora + 7200]];
        $u = ['time' => 'unixtime'];
        foreach (app(VariablesReporte::class)->atmosfericas() as [$campo, , $unidad, $min]) {
            $h[$campo] = [max(0, $min), null, max(1, $min)];
            $u[$campo] = $unidad;
        }

        $punto = collect(app(CatalogoAmbiental::class)->leer()['puntos'])->firstWhere('codigo', $codigo);

        return ['latitude' => $punto['latitud'], 'longitude' => $punto['longitud'], 'utc_offset_seconds' => 0,
            'hourly' => $h, 'hourly_units' => $u];
    }

    public function test_una_accion_explicita_conserva_variables_ceros_faltantes_y_fuentes(): void
    {
        $this->cliente();
        Http::fake(fn () => Http::response($this->respuesta()));
        $r = $this->postJson('/api/v1/reportes', $this->entrada(['variables' => array_keys(app(VariablesReporte::class)->atmosfericas())]))->assertCreated();
        $filas = $r->json('contenido.filas');
        $this->assertCount(count(app(VariablesReporte::class)->atmosfericas()) * 2, $filas);
        $this->assertCount(count(app(VariablesReporte::class)->atmosfericas()), $r->json('contenido.faltantes'));
        $this->assertEquals(0, $filas[0]['valor']);
        $this->assertSame(['estimado'], array_values(array_unique(array_column($filas, 'origen'))));
        $this->assertContains('open-meteo-cams-us', array_column($filas, 'fuente'));
        $this->assertContains('open-meteo-cams-eu', array_column($filas, 'fuente'));
        $nieve = collect($filas)->firstWhere('metrica', 'nieve_acumulada');
        $this->assertSame('m', $nieve['unidad']);
        $this->assertSame(now('UTC')->startOfHour()->toIso8601ZuluString(), $nieve['instante']);
        $this->assertDatabaseCount('lecturas_ambientales', 0);
        $this->assertDatabaseCount('puntos_ambientales', 0);
        Http::assertSentCount(2);
        Http::assertSent(fn ($req) => str_starts_with($req->url(), 'https://api.open-meteo.com/v1/forecast?') && $req['timezone'] === 'UTC' && $req['timeformat'] === 'unixtime' && str_contains($req['hourly'], 'snow_depth'));
        $this->get('/reportes/'.$r->json('id'))->assertOk()->assertSee('valores no están disponibles')->assertSee('Nieve acumulada')->assertSee('Barras');
    }

    public function test_abrir_pantallas_no_consulta_proveedores_y_visualizador_no_genera(): void
    {
        $this->cliente();
        $this->get('/reportes')->assertOk()->assertSee('Pronóstico atmosférico');
        $this->get('/plantillas')->assertOk()->assertSee('Visibilidad');
        Http::assertNothingSent();
        $this->cliente('visualizador');
        $this->postJson('/api/v1/reportes', $this->entrada())->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_filtros_incompatibles_valores_del_cliente_y_limites_no_salen_a_la_red(): void
    {
        $this->cliente();
        foreach ([['puntos' => ['falso']], ['puntos' => ['santacruz', 'santacruz']], ['variables' => ['ica']],
            ['horas' => 0], ['horas' => 73], ['variables' => []], ['variables' => ['viento', 'viento']],
            ['valor' => 100], ['url' => 'https://ajena.test'], ['origen' => 'observado'],
            ['puntos' => ['santacruz', 'lapaz'], 'variables' => array_keys(app(VariablesReporte::class)->atmosfericas()), 'horas' => 72]] as $mal) {
            $this->postJson('/api/v1/reportes', $this->entrada($mal))->assertUnprocessable();
        }
        $this->postJson('/api/v1/reportes', ['titulo' => 'Incompatible', 'base' => 'mediciones', 'horas' => 3])->assertUnprocessable();
        $this->assertDatabaseCount('reportes_ambientales', 0);
        Http::assertNothingSent();
    }

    public function test_condiciones_excluyen_faltantes_y_no_mezclan_modelo_meteorologico_con_aire(): void
    {
        $this->cliente();
        Http::fake(fn () => Http::response($this->respuesta()));
        $id = $this->postJson('/api/v1/plantillas', ['nombre' => 'Sin viento', 'columnas' => ['valor', 'variable'], 'grafico' => true,
            'condiciones' => [['variable' => 'viento', 'operador' => '=', 'valor' => 0]]])->assertCreated()->json('id');
        $r = $this->postJson('/api/v1/reportes', $this->entrada(['plantilla' => $id, 'variables' => ['viento', 'lluvia']]))->assertCreated();
        $this->assertCount(2, $r->json('contenido.filas'));
        $this->patchJson('/api/v1/plantillas/'.$id, ['nombre' => 'Dos fuentes', 'columnas' => ['valor'], 'grafico' => true,
            'condiciones' => [['variable' => 'temperatura', 'operador' => '=', 'valor' => 0], ['variable' => 'aqi', 'operador' => '=', 'valor' => 0]]])->assertOk();
        $this->postJson('/api/v1/reportes', $this->entrada(['plantilla' => $id]))->assertCreated()->assertJsonPath('contenido.filas', []);
    }

    public function test_errores_unidades_fechas_y_vectores_no_guardan_reportes_parciales(): void
    {
        $this->cliente();
        $respuesta = [];
        $status = 200;
        // Conserva el mismo transporte controlado al cambiar el contrato de cada caso.
        Http::fake(function () use (&$respuesta, &$status) {
            return Http::response($respuesta, $status);
        });
        foreach (['http', 'unidad', 'longitud', 'valor', 'orden', 'antiguo', 'json'] as $caso) {
            $respuesta = $this->respuesta();
            $status = $caso === 'http' ? 429 : 200;
            if ($caso === 'unidad') {
                $respuesta['hourly_units']['rain'] = 'cm';
            }
            if ($caso === 'longitud') {
                $respuesta['hourly']['rain'] = [0];
            }
            if ($caso === 'valor') {
                $respuesta['hourly']['rain'][0] = -1;
            }
            if ($caso === 'orden') {
                $respuesta['hourly']['time'][1] = $respuesta['hourly']['time'][0];
            }
            if ($caso === 'antiguo') {
                $respuesta['hourly']['time'][0] = now()->subDays(4)->timestamp;
            }
            if ($caso === 'json') {
                $respuesta = 'respuesta inválida';
            }
            $this->postJson('/api/v1/reportes', $this->entrada(['variables' => ['lluvia']]))->assertStatus(503)->assertJsonPath('error.codigo', 'ATMOSFERA_NO_DISPONIBLE');
        }
        $this->assertDatabaseCount('reportes_ambientales', 0);
    }

    public function test_multiples_localidades_respetan_su_orden_y_no_se_cruzan(): void
    {
        $this->cliente();
        $a = $this->respuesta() + ['location_id' => 0];
        $b = $this->respuesta('lapaz') + ['location_id' => 1];
        $b['hourly']['rain'][0] = 25;
        $respuesta = [$a, $b];
        Http::fake(function () use (&$respuesta) {
            return Http::response($respuesta);
        });
        $r = $this->postJson('/api/v1/reportes', $this->entrada(['puntos' => ['santacruz', 'lapaz'], 'variables' => ['lluvia']]))->assertCreated();
        $this->assertEquals(25, collect($r->json('contenido.filas'))->firstWhere('puntoCodigo', 'lapaz')['valor']);
        Http::assertSent(fn ($req) => count(explode(',', $req['latitude'])) === 2);
        $respuesta = [$b, $a];
        $this->postJson('/api/v1/reportes', $this->entrada(['puntos' => ['santacruz', 'lapaz'], 'variables' => ['lluvia']]))->assertStatus(503);
        $this->assertDatabaseCount('reportes_ambientales', 1);
    }

    public function test_configuracion_por_entorno_se_prueba_con_transporte_controlado(): void
    {
        $this->cliente();
        config(['reportes.atmosfera.url' => 'https://proveedor.test/forecast', 'reportes.atmosfera.clave' => 'clave-ficticia-de-prueba']);
        Http::fake(['proveedor.test/*' => Http::response($this->respuesta())]);
        $this->postJson('/api/v1/reportes', $this->entrada(['variables' => ['lluvia']]))->assertCreated()->assertDontSee('clave-ficticia');
        Http::assertSent(fn ($r) => $r['apikey'] === 'clave-ficticia-de-prueba');
        config(['reportes.atmosfera.habilitado' => false]);
        $this->postJson('/api/v1/reportes', $this->entrada())->assertStatus(503);
        Http::assertSentCount(1);
    }

    public function test_pdf_con_graficos_y_excel_con_faltantes_descargan_el_snapshot_sin_reconsultar(): void
    {
        $this->cliente();
        Http::fake(fn () => Http::response($this->respuesta()));
        $r = $this->postJson('/api/v1/reportes', $this->entrada(['variables' => ['lluvia']]))->assertCreated()->json();
        $pdf = $this->get('/reportes/'.$r['id'].'/descargar/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $xls = $this->get('/reportes/'.$r['id'].'/descargar/xlsx')->assertOk();
        $this->assertStringStartsWith('PK', $xls->getContent());
        $this->getJson('/api/v1/reportes/'.$r['id'])->assertJsonPath('contenido', $r['contenido']);
        Http::assertSentCount(1);
        $grafico = app(GraficoReporte::class);
        $serie = $grafico->series($r['contenido'])[0];
        $svg = $grafico->svg($serie);
        $this->assertStringContainsString('circle', $svg);
        $this->assertStringNotContainsString('L', preg_replace('/<text.*?<\/text>/', '', $svg));
        $this->assertSame('', $grafico->svg([$r['contenido']['faltantes'][0]]));
        if (getenv('GEOSUR_EVIDENCIA_M3')) {
            $directorio = base_path('../tmp/evidencia-m3');
            File::ensureDirectoryExists($directorio);
            file_put_contents($directorio.'/atmosfera.pdf', $pdf->getContent());
            file_put_contents($directorio.'/atmosfera.xlsx', $xls->getContent());
        }
    }

    public function test_timeout_no_filtra_errores_y_todas_las_variables_ausentes_siguen_sin_ser_cero(): void
    {
        $this->cliente();
        $fallar = true;
        $respuesta = $this->respuesta();
        unset($respuesta['hourly']['rain'], $respuesta['hourly_units']['rain']);
        Http::fake(function () use (&$fallar, $respuesta) {
            if ($fallar) {
                throw new ConnectionException('detalle privado del transporte');
            }

            return Http::response($respuesta);
        });
        $this->postJson('/api/v1/reportes', $this->entrada(['variables' => ['lluvia']]))->assertStatus(503)->assertDontSee('detalle privado');
        $fallar = false;
        $r = $this->postJson('/api/v1/reportes', $this->entrada(['variables' => ['lluvia']]))->assertCreated()->assertJsonPath('contenido.filas', []);
        $this->assertCount(3, $r->json('contenido.faltantes'));
        $this->assertSame([], $r->json('contenido.resumen'));
        $this->get('/reportes/'.$r->json('id'))->assertOk()->assertSee('No hay lecturas')->assertSee('3 valores no están disponibles');
    }

    public function test_corte_global_preserva_inyecciones_y_reportes_anteriores_al_reinicio(): void
    {
        $u = $this->cliente();
        $motor = app(ContinuarSimulacion::class);
        $motor->accion(['accion' => 'preparar', 'revision' => 0, 'semilla' => 'corte'], $u->id);
        $motor->accion(['accion' => 'iniciar', 'revision' => 1], $u->id);
        $motor->accion(['accion' => 'inyectar', 'revision' => 2, 'punto' => 'santacruz', 'valores' => ['aqi' => 0]], $u->id);
        $r = $this->postJson('/api/v1/reportes', ['titulo' => 'Corte global', 'base' => 'continua'])->assertCreated()->json();
        $f = collect($r['contenido']['filas'])->first(fn ($f) => $f['puntoCodigo'] === 'santacruz' && $f['metrica'] === 'aqi');
        $this->assertEquals(0, $f['valor']);
        $this->assertSame('manual', $f['intervencion']);
        $this->assertStringEndsWith('-manual', $f['fuente']);
        $this->assertSame(3, $r['parametros']['corte']['revision']);
        $motor->accion(['accion' => 'reiniciar', 'revision' => 3], $u->id);
        $this->getJson('/api/v1/reportes/'.$r['id'])->assertJsonPath('contenido', $r['contenido'])->assertJsonPath('parametros', $r['parametros']);
        $this->assertNotSame($r['parametros']['corte']['version'], SimulacionContinua::find(1)->contenido['version']);
        $this->assertDatabaseCount('lecturas_ambientales', 0);
        Http::assertNothingSent();
    }

    public function test_las_escalas_del_aire_tienen_variables_unidades_fuentes_y_graficos_distintos(): void
    {
        $this->cliente();
        $datos = $this->respuesta();
        $datos['hourly']['us_aqi'] = [91, null, 95];
        $datos['hourly']['european_aqi'] = [40, null, 45];
        Http::fake(fn () => Http::response($datos));
        $r = $this->postJson('/api/v1/reportes', $this->entrada(['variables' => ['aqi', 'aqi_europeo']]))->assertCreated()->json();
        $us = collect($r['contenido']['filas'])->firstWhere('metrica', 'aqi');
        $eu = collect($r['contenido']['filas'])->firstWhere('metrica', 'aqi_europeo');
        $this->assertSame('USAQI', $us['unidad']);
        $this->assertSame('EAQI', $eu['unidad']);
        $this->assertSame('us_aqi', $us['escala']);
        $this->assertSame('european_aqi', $eu['escala']);
        $this->assertNotSame($us['fuente'], $eu['fuente']);
        $this->assertSame(91, $us['valor']);
        $this->assertSame(40, $eu['valor']);
        $this->assertCount(2, $r['contenido']['resumen']);
        $this->assertCount(2, app(GraficoReporte::class)->series($r['contenido']));
        $this->assertSame(hash('sha256', json_encode($datos)), $r['contenido']['productos'][0]['sha256Respuesta']);
        $this->assertSame('santacruz', $r['contenido']['productos'][0]['celdas'][0]['punto']);
        Http::assertSentCount(1);
        Http::assertSent(fn ($q) => $q['domains'] === 'cams_global' && $q['hourly'] === 'us_aqi,european_aqi' && $q['forecast_hours'] === 3);
        $this->get('/reportes/'.$r['id'])->assertOk()->assertSee('escala europea')->assertSee('escala de Estados Unidos')->assertSee('USAQI')->assertSee('EAQI');
        $this->assertDatabaseCount('lecturas_ambientales', 0);
    }

    public function test_celdas_zonas_escalas_y_fechas_incoherentes_no_guardan_ningun_reporte(): void
    {
        $this->cliente();
        $datos = [];
        Http::fake(function () use (&$datos) {
            return Http::response($datos);
        });
        $casos = [
            fn (&$r) => $r['latitude'] = 40,
            fn (&$r) => $r['longitude'] = 0,
            fn (&$r) => $r['latitude'] = '-17.8',
            fn (&$r) => $r['utc_offset_seconds'] = -14400,
            fn (&$r) => $r['hourly_units']['time'] = 'iso8601',
            fn (&$r) => $r['hourly_units']['us_aqi'] = 'EAQI',
            fn (&$r) => $r['hourly_units']['european_aqi'] = 'USAQI',
            fn (&$r) => $r['hourly']['european_aqi'][0] = -1,
            function (&$r) {
                foreach ($r['hourly']['time'] as &$t) {
                    $t += 1800;
                }
            },
        ];
        foreach ($casos as $cambiar) {
            $datos = $this->respuesta();
            $cambiar($datos);
            $this->postJson('/api/v1/reportes', $this->entrada(['variables' => ['aqi', 'aqi_europeo']]))->assertStatus(503);
        }
        $this->assertDatabaseCount('reportes_ambientales', 0);
    }

    public function test_no_se_aplican_condiciones_cruzando_las_dos_escalas(): void
    {
        $this->cliente();
        Http::fake(fn () => Http::response($this->respuesta()));
        $p = $this->postJson('/api/v1/plantillas', ['nombre' => 'Dos escalas', 'columnas' => ['variable', 'valor', 'unidad'], 'grafico' => true,
            'condiciones' => [['variable' => 'aqi', 'operador' => '=', 'valor' => 0], ['variable' => 'aqi_europeo', 'operador' => '=', 'valor' => 0]]])->assertCreated()->json('id');
        $this->postJson('/api/v1/reportes', $this->entrada(['plantilla' => $p, 'variables' => ['aqi', 'aqi_europeo']]))->assertCreated()->assertJsonPath('contenido.filas', []);
    }

    public function test_reportes_anteriores_con_aqi_generico_conservan_su_corte_y_descargan_sin_red(): void
    {
        $u = $this->cliente();
        Http::fake(fn () => Http::response($this->respuesta()));
        $nuevo = $this->postJson('/api/v1/reportes', $this->entrada(['variables' => ['aqi']]))->assertCreated()->json();
        $contenido = $nuevo['contenido'];
        unset($contenido['productos']);
        foreach (['filas', 'faltantes', 'resumen'] as $grupo) {
            foreach ($contenido[$grupo] as &$fila) {
                $fila['fuente'] = 'open-meteo-cams';
                $fila['fuenteNombre'] = 'Open-Meteo · CAMS';
                $fila['variable'] = 'Calidad del aire';
                $fila['unidad'] = 'AQI';
                unset($fila['escala']);
            }
            unset($fila);
        }
        $viejo = ReporteAmbiental::create(['usuario_id' => $u->id, 'titulo' => 'Corte anterior', 'parametros' => $nuevo['parametros'], 'contenido' => $contenido]);
        $this->postJson('/api/v1/reportes', $this->entrada(['variables' => ['aqi_europeo']]))->assertCreated();
        $this->getJson('/api/v1/reportes/'.$viejo->id)->assertOk()->assertJsonPath('contenido', $contenido);
        $this->get('/reportes/'.$viejo->id.'/descargar/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get('/reportes/'.$viejo->id.'/descargar/xlsx')->assertOk();
        Http::assertSentCount(2);
    }

    public function test_pedir_ambas_escalas_no_expone_claves_y_limita_la_descarga(): void
    {
        $this->cliente();
        config(['reportes.atmosfera.clave' => 'secreto-controlado-no-real']);
        $opciones = [];
        Http::fake(function ($r, $configuracion) use (&$opciones) {
            $opciones = $configuracion;

            return Http::response($this->respuesta());
        });
        $r = $this->postJson('/api/v1/reportes', $this->entrada(['variables' => ['aqi_europeo']]))->assertCreated()->assertDontSee('secreto-controlado');
        $this->assertFalse($opciones['allow_redirects']);
        $this->assertSame(12, $opciones['timeout']);
        $this->expectException(\UnexpectedValueException::class);
        $opciones['progress'](0, 1500001);
    }
}
