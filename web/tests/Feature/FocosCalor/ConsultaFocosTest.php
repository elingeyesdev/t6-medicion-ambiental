<?php

namespace Tests\Feature\FocosCalor;

use App\Services\FocosCalor\NormalizadorFirms;
use App\Services\Territorios\CatalogoTerritorial;
use App\Services\Territorios\GeometriaTerritorial;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConsultaFocosTest extends TestCase
{
    private const BASE = '/api/v1/focos-calor?desde=2026-10-10T00:00:00Z&hasta=2026-10-11T00:00:00Z';

    protected function setUp(): void
    {
        parent::setUp();
        config(['focos.clave' => 'claveFicticiaNoUtilizable1234567890']);
        Http::preventStrayRequests();
    }

    private function nasa(string $filas = '', string $desde = '2026-10-01', string $hasta = '2026-10-16'): void
    {
        Http::fake([
            '*/data_availability/*' => fn () => Http::response("data_id,min_date,max_date\nVIIRS_SNPP_NRT,$desde,$hasta\n"),
            '*/area/*' => fn () => Http::response("latitude,longitude,acq_date,acq_time,satellite,instrument,confidence,frp,version\n".$filas),
        ]);
    }

    public function test_catalogo_y_limite_sin_clave_ni_llamadas_a_nasa(): void
    {
        config(['focos.clave' => '']);
        $this->getJson('/api/v1/territorios?tipo=provincia&padre=BO07')->assertOk()->assertJsonCount(15, 'datos')->assertJsonPath('catalogo.estado', 'propuesta_pendiente_revision');
        $this->getJson('/api/v1/territorios?tipo=municipio')->assertOk()->assertJsonCount(56, 'datos');
        $this->getJson('/api/v1/territorios/BO070101/limite')->assertOk()->assertJsonPath('limite.type', 'Feature')->assertJsonPath('territorio.municipio', 'BO070101');
        $this->getJson('/api/v1/territorios/BO99/limite')->assertNotFound()->assertJsonPath('error.codigo', 'TERRITORIO_NO_ENCONTRADO');
        $this->getJson('/api/v1/territorios?padre=BO99')->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_coherencia_de_filtros_resumen_detalle_y_paginacion_estable(): void
    {
        $this->nasa("-17.7834,-63.1821,2026-10-10,0305,N,VIIRS,h,0,2.0NRT\n-17.784,-63.183,2026-10-10,1420,N,VIIRS,n,,2.0NRT\n-17.7834,-63.1821,2026-10-10,0305,N,VIIRS,h,0,2.0NRT\n-20,-70,2026-10-10,1420,N,VIIRS,h,9,2.0NRT\n-17.78,-63.18,2026-10-11,0000,N,VIIRS,h,7,2.0NRT\n91,-63,2026-10-10,1400,N,VIIRS,h,2,2.0NRT\n");
        $primera = $this->getJson(self::BASE.'&municipio=BO070101&tamanioPagina=1')->assertOk()
            ->assertJsonCount(1, 'datos')->assertJsonPath('consulta.filtrosEfectivos.provincia', 'BO0701')
            ->assertJsonPath('resumen.totalRegistros', 2)->assertJsonPath('resumen.registrosConFaltantes', 1)
            ->assertJsonPath('resumen.indicadores.2.valor', 0)->assertJsonPath('paginacion.total', 2)
            ->assertJsonPath('datos.0.atributos.potenciaRadiativa', null)->assertJsonPath('representacionMapa.alcance', 'pagina')
            ->assertJsonPath('consulta.obtencion.tipo', 'consulta_proveedor');
        $token = $primera->json('paginacion.versionConjunto');
        $recuperacion = $primera->json('consulta.obtencion.fechaRecuperacion');
        $this->travel(1)->minutes();
        $this->getJson(self::BASE.'&municipio=BO070101&tamanioPagina=1&pagina=2&versionConjunto='.$token)->assertOk()
            ->assertJsonPath('datos.0.atributos.potenciaRadiativa', 0)->assertJsonPath('paginacion.siguiente', null)
            ->assertJsonPath('consulta.obtencion.tipo', 'cache')->assertJsonPath('consulta.obtencion.fechaRecuperacion', $recuperacion)
            ->assertJsonPath('datos.0.fechas.observadoDesde', '2026-10-10T03:05:00Z');
        $this->getJson(self::BASE.'&municipio=BO070101&pagina=999&versionConjunto='.$token)->assertOk()->assertJsonCount(0, 'datos')->assertJsonPath('resumen.totalRegistros', 2);
        Http::assertSentCount(2);
    }

    public function test_vacio_no_declara_ausencia_de_incendios_ni_potencia_cero(): void
    {
        $this->nasa();
        $this->getJson(self::BASE)->assertOk()->assertJsonCount(0, 'datos')
            ->assertJsonPath('resumen.indicadores.2.valor', null)->assertJsonPath('resumen.totalRegistros', 0)
            ->assertJsonPath('cobertura.estado', 'desconocida');
    }

    public function test_confianza_filtra_antes_del_resumen_y_del_mapa(): void
    {
        $this->nasa("-17.7834,-63.1821,2026-10-10,1000,N,VIIRS,h,2,2.0NRT\n-17.784,-63.183,2026-10-10,1100,N,VIIRS,n,99,2.0NRT\n");
        $this->getJson(self::BASE.'&confianza=alta')->assertOk()->assertJsonCount(1, 'datos')->assertJsonPath('resumen.totalRegistros', 1)->assertJsonPath('resumen.indicadores.2.valor', 2);
    }

    public function test_rechaza_filtros_ambiguos_y_fechas_invalidas_antes_de_consultar(): void
    {
        foreach (['&desconocido=x', '&confianza[]=alta', '&confianza=alta&confianza=baja', '&dias=7', '&tamanioPagina=501', '&pagina=0', '&provincia=BO0702&municipio=BO070101', '&provincia=BO01', '&confianza=severo'] as $extra) {
            $this->getJson(self::BASE.$extra)->assertUnprocessable()->assertJsonStructure(['error' => ['codigo', 'mensaje', 'identificadorConsulta', 'campos']]);
        }
        foreach ([['2026-02-30T00:00:00Z', '2026-03-01T00:00:00Z'], ['2026-10-10', '2026-10-11'], ['2026-10-11T00:00:00Z', '2026-10-10T00:00:00Z'], ['2026-10-01T00:00:00Z', '2026-10-09T00:00:00Z']] as [$desde, $hasta]) {
            $this->getJson('/api/v1/focos-calor?'.http_build_query(compact('desde', 'hasta')))->assertUnprocessable();
        }
        Http::assertNothingSent();
    }

    public function test_error_de_nasa_no_filtra_la_clave_ni_se_convierte_en_demo(): void
    {
        config(['focos.clave' => 'secretoControlado1234567890']);
        Http::fake(['*' => Http::response('Error secretoControlado1234567890', 403)]);
        $this->getJson(self::BASE)->assertStatus(503)->assertJsonPath('error.codigo', 'PROVEEDOR_NO_DISPONIBLE')->assertJsonMissingPath('datos')->assertDontSee('secretoControlado1234567890');
        Http::assertSentCount(1);
    }

    public function test_configuracion_ausente_es_503_y_demo_no_se_inventa(): void
    {
        config(['focos.clave' => '']);
        $this->getJson(self::BASE)->assertStatus(503)->assertJsonPath('error.codigo', 'PROVEEDOR_NO_DISPONIBLE');
        $this->getJson(self::BASE.'&modo=demostracion')->assertStatus(503)->assertJsonPath('error.codigo', 'MODULO_NO_DISPONIBLE');
        Http::assertNothingSent();
    }

    public function test_fallo_de_conexion_no_expone_credenciales_ni_reutiliza_datos_vencidos(): void
    {
        $this->nasa("-17.7834,-63.1821,2026-10-10,1000,N,VIIRS,h,2,2.0NRT\n");
        $this->getJson(self::BASE)->assertOk()->assertJsonPath('resumen.totalRegistros', 1);
        $this->travel(6)->minutes();
        Http::fake(['*' => Http::failedConnection('URL privada de prueba')]);
        $this->getJson(self::BASE)->assertStatus(503)->assertJsonMissingPath('datos')->assertDontSee('URL privada');
    }

    public function test_catalogo_inaccesible_no_se_sustituye_con_una_caja_o_datos_de_prueba(): void
    {
        config(['focos.datos_territoriales' => storage_path('no-existe')]);
        $this->getJson(self::BASE)->assertStatus(503)->assertJsonPath('error.codigo', 'CATALOGO_NO_DISPONIBLE')->assertJsonMissingPath('datos');
        Http::assertNothingSent();
    }

    public function test_duplicados_conflictivos_se_resuelven_antes_de_aplicar_confianza(): void
    {
        $a = "-17.7834,-63.1821,2026-10-10,1000,N,VIIRS,h,2,2.0NRT\n";
        $b = "-17.7834,-63.1821,2026-10-10,1000,N,VIIRS,n,8,2.0NRT\n";
        $this->nasa($a.$b);
        $primera = $this->getJson(self::BASE)->assertOk()->assertJsonPath('resumen.totalRegistros', 1)->json('datos');
        Cache::flush();
        $this->nasa($b.$a);
        $segunda = $this->getJson(self::BASE)->assertOk()->json('datos');
        $this->assertSame($primera, $segunda);
    }

    public function test_borde_compartido_no_asigna_un_municipio_arbitrario(): void
    {
        $ruta = storage_path('framework/testing/bordes-'.uniqid());
        mkdir($ruta.'/limites', 0777, true);
        $territorios = [
            ['identificador' => 'BO07', 'nombre' => 'Santa Cruz', 'tipo' => 'departamento', 'padre' => null],
            ['identificador' => 'P', 'nombre' => 'Provincia de prueba', 'tipo' => 'provincia', 'padre' => 'BO07'],
            ['identificador' => 'A', 'nombre' => 'Municipio A de prueba', 'tipo' => 'municipio', 'padre' => 'P'],
            ['identificador' => 'B', 'nombre' => 'Municipio B de prueba', 'tipo' => 'municipio', 'padre' => 'P'],
        ];
        file_put_contents($ruta.'/catalogo.json', json_encode(['espacioIdentificadores' => 'PCODE_OCHA_CODAB_BOL_v02', 'version' => 'fixture', 'territorios' => $territorios]));
        $cuadrado = fn ($x) => ['type' => 'Polygon', 'coordinates' => [[[$x, 0], [$x + 1, 0], [$x + 1, 1], [$x, 1], [$x, 0]]]];
        file_put_contents($ruta.'/limites/municipios.geojson', json_encode(['versionCatalogo' => 'fixture', 'features' => [['id' => 'A', 'geometry' => $cuadrado(0)], ['id' => 'B', 'geometry' => $cuadrado(1)]]]));
        try {
            config(['focos.datos_territoriales' => $ruta]);
            $catalogo = new CatalogoTerritorial($ruta);
            $g = new GeometriaTerritorial($catalogo);
            $borde = $g->asignar(1, 0.5);
            $this->assertNull($borde['municipio']);
            $this->assertSame('P', $borde['provincia']);
            $this->assertSame('frontera_compartida', $borde['motivoAsignacion']);
        } finally {
            unlink($ruta.'/catalogo.json');
            unlink($ruta.'/limites/municipios.geojson');
            rmdir($ruta.'/limites');
            rmdir($ruta);
        }
    }

    public function test_respuesta_html_200_o_csv_roto_es_error_no_vacio(): void
    {
        foreach (['<html>Error</html>', 'latitude,longitude,acq_date,acq_time'."\n-17,-63,2026-10-10\n"] as $cuerpo) {
            Http::fake(['*/data_availability/*' => Http::response("data_id,min_date,max_date\nVIIRS_SNPP_NRT,2026-10-01,2026-10-16\n"), '*/area/*' => Http::response($cuerpo)]);
            $this->getJson(self::BASE)->assertStatus(503)->assertJsonPath('error.codigo', 'PROVEEDOR_NO_DISPONIBLE');
        }
    }

    public function test_ventana_utc_168_horas_se_divide_en_bloques_de_maximo_cinco_dias(): void
    {
        $this->nasa("-17.7834,-63.1821,2026-10-10,0400,N,VIIRS,h,1,2.0NRT\n");
        $url = '/api/v1/focos-calor?'.http_build_query(['desde' => '2026-10-10T00:00:00-04:00', 'hasta' => '2026-10-17T00:00:00-04:00']);
        $this->getJson($url)->assertOk()->assertJsonPath('consulta.periodo.desde', '2026-10-10T04:00:00Z')->assertJsonPath('cobertura.estado', 'parcial');
        Http::assertSentCount(3);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/5/2026-10-10'));
        Http::assertSent(fn ($r) => str_contains($r->url(), '/2/2026-10-15'));
    }

    public function test_disponibilidad_comprobada_fuera_del_periodo_no_hace_consulta_area(): void
    {
        $this->nasa('', '2026-10-12', '2026-10-16');
        $this->getJson(self::BASE)->assertOk()->assertJsonPath('cobertura.estado', 'sin_cobertura')->assertJsonCount(0, 'datos');
        Http::assertSentCount(1);
    }

    public function test_conjunto_expirado_y_cambio_de_filtros_producen_409(): void
    {
        $this->nasa();
        $token = $this->getJson(self::BASE)->assertOk()->json('paginacion.versionConjunto');
        $this->getJson(self::BASE.'&confianza=alta&versionConjunto='.$token)->assertStatus(409);
        $this->travel(16)->minutes();
        $this->getJson(self::BASE.'&pagina=2&versionConjunto='.$token)->assertStatus(409)->assertJsonPath('error.codigo', 'CONJUNTO_CAMBIADO');
        $this->getJson(self::BASE.'&pagina=2')->assertStatus(409);
        Http::assertSentCount(2);
    }

    public function test_normalizador_conserva_ausencias_fecha_utc_y_confianza_original(): void
    {
        $n = new NormalizadorFirms;
        $f = $n->convertir(['longitude' => '-63', 'latitude' => '-17', 'acq_date' => '2026-10-10', 'acq_time' => '5', 'confidence' => 'inusual', 'frp' => '-1']);
        $this->assertSame('2026-10-10T00:05:00Z', $f['fechas']['observadoDesde']);
        $this->assertSame('desconocida', $f['atributos']['confianza']);
        $this->assertSame('inusual', $f['atributos']['confianzaOriginal']);
        $this->assertNull($f['atributos']['satelite']);
        $this->assertNull($f['atributos']['potenciaRadiativa']);
        $this->assertNull($n->convertir(['longitude' => 'nan', 'latitude' => '-17']));
        $this->assertNull($n->convertir(['longitude' => '-63', 'latitude' => '-17', 'acq_date' => '2026-02-30', 'acq_time' => '2400']));
    }

    public function test_poligonos_huecos_multipoligonos_y_bordes_no_equivalen_a_la_caja(): void
    {
        $g = app(GeometriaTerritorial::class);
        $p = ['type' => 'Polygon', 'coordinates' => [[[0, 0], [4, 0], [4, 4], [0, 4], [0, 0]], [[1, 1], [3, 1], [3, 3], [1, 3], [1, 1]]]];
        $this->assertTrue($g->contiene($p, 0.5, 0.5));
        $this->assertFalse($g->contiene($p, 2, 2));
        $this->assertTrue($g->contiene($p, 0, 2));
        $this->assertTrue($g->contiene($p, 1, 2));
        $this->assertFalse($g->contiene($p, 5, 5));
        $this->assertTrue($g->contiene(['type' => 'MultiPolygon', 'coordinates' => [$p['coordinates']]], 0.5, 0.5));
        $a = $g->asignar(-63.1821, -17.7834);
        $this->assertSame('BO070101', $a['municipio']);
        $this->assertSame('BO0701', $a['provincia']);
    }

    public function test_errores_http_y_modulos_futuros_tienen_envoltura_controlada(): void
    {
        $this->postJson('/api/v1/focos-calor')->assertStatus(405)->assertJsonPath('error.codigo', 'METODO_NO_ADMITIDO');
        $this->getJson('/api/v1/no-existe')->assertNotFound()->assertJsonPath('error.codigo', 'RUTA_NO_ENCONTRADA');
        $this->getJson('/api/v1/inundaciones')->assertStatus(503)->assertJsonPath('error.codigo', 'MODULO_NO_DISPONIBLE');
        $this->getJson('/api/incendios')->assertNotFound();
        Http::assertNothingSent();
    }
}
