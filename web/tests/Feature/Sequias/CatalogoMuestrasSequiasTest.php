<?php

namespace Tests\Feature\Sequias;

use App\Services\Sequias\Experimental\CatalogoMuestrasSequias;
use App\Services\Sequias\LectorJsonLocal;
use App\Services\Sequias\SeriePrecipitacionInvalida;
use App\Services\Territorios\CatalogoTerritorial;
use App\Services\Territorios\GeometriaTerritorial;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CatalogoMuestrasSequiasTest extends TestCase
{
    private array $temporales = [];

    protected function tearDown(): void
    {
        foreach ($this->temporales as $temporal) {
            if (is_file($temporal)) {
                unlink($temporal);
            }
        }
        parent::tearDown();
    }

    private function documento(): array
    {
        return json_decode(file_get_contents(config('sequias.archivo_muestras')), true, flags: JSON_THROW_ON_ERROR);
    }

    private function cargarDocumento(array $d): CatalogoMuestrasSequias
    {
        $archivo = tempnam(sys_get_temp_dir(), 'geocruz-muestras-');
        $this->temporales[] = $archivo;
        file_put_contents($archivo, json_encode($d, JSON_THROW_ON_ERROR));

        return new CatalogoMuestrasSequias($archivo, dirname(config('focos.datos_territoriales')), app(LectorJsonLocal::class),
            app(CatalogoTerritorial::class), app(GeometriaTerritorial::class));
    }

    private function error(string $codigo, callable $accion): void
    {
        try {
            $accion();
            $this->fail('El catálogo o filtro inválido fue aceptado.');
        } catch (SeriePrecipitacionInvalida $e) {
            $this->assertSame($codigo, $e->codigo);
        }
        Http::assertNothingSent();
    }

    public function test_los_56_puntos_cubren_el_catalogo_sin_inventar_cobertura_climatica(): void
    {
        $c = app(CatalogoMuestrasSequias::class);
        $r = $c->consultar();
        $this->assertCount(56, $r['datos']);
        $this->assertSame(56, $r['resumen']['municipiosEnTerritorio']);
        $this->assertSame(0, $r['resumen']['consultasClimaticasEnEstaOperacion']);
        $this->assertSame(0, $r['resumen']['spiCalculadosEnEstaOperacion']);
        $this->assertCount(56, array_unique(array_column($r['datos'], 'identificador')));
        $this->assertCount(56, array_unique(array_column($r['datos'], 'municipio')));
        $this->assertCount(15, array_unique(array_column($r['datos'], 'provincia')));
        $this->assertFalse($r['representacionMapa']['rellenarPoligonos']);
        $geografia = app(GeometriaTerritorial::class);
        foreach ($r['datos'] as $s) {
            [$x, $y] = $s['coordenadaSolicitada'];
            $this->assertSame($s['municipio'], $geografia->asignar($x, $y)['municipio']);
            $this->assertSame($s['provincia'], app(CatalogoTerritorial::class)->buscar($s['municipio'])['padre']);
            $this->assertNull($s['spi']);
            $this->assertNull($s['coordenadaCelda']);
        }
        Http::assertNothingSent();
    }

    public function test_provincia_municipio_y_muestra_conservan_piloto_e_inferencia_del_padre(): void
    {
        $c = app(CatalogoMuestrasSequias::class);
        $p = $c->consultar('BO0701');
        $this->assertCount(5, $p['datos']);
        $this->assertSame(5, $p['resumen']['municipiosEnTerritorio']);
        $m = $c->consultar(municipio: 'BO070101');
        $this->assertSame('BO0701', $m['filtrosEfectivos']['provincia']);
        $this->assertCount(1, $m['datos']);
        $s = $m['datos'][0];
        $this->assertSame('scz-piloto-01', $s['identificador']);
        $this->assertSame([-63.1821, -17.7833], $s['coordenadaSolicitada']);
        $filtrada = $c->consultar('BO0701', muestra: 'scz-piloto-01');
        $this->assertCount(1, $filtrada['datos']);
        $this->assertSame(5, $filtrada['resumen']['muestrasEnTerritorio']);
        $this->assertSame(1, $filtrada['resumen']['muestrasSeleccionadas']);
        Http::assertNothingSent();
    }

    public static function filtrosInvalidos(): iterable
    {
        yield 'territorio' => [['provincia' => 'BO99'], 'TERRITORIO_NO_DISPONIBLE'];
        yield 'nivel' => [['provincia' => 'BO070101'], 'TIPO_TERRITORIAL_INVALIDO'];
        yield 'padres-distintos' => [['provincia' => 'BO0702', 'municipio' => 'BO070101'], 'FILTROS_INCOMPATIBLES'];
        yield 'muestra-ajena' => [['provincia' => 'BO0702', 'muestra' => 'scz-piloto-01'], 'FILTROS_INCOMPATIBLES'];
        yield 'muestra-desconocida' => [['muestra' => '../archivo'], 'MUESTRA_NO_DISPONIBLE'];
        yield 'vacio' => [['municipio' => ''], 'TERRITORIO_NO_DISPONIBLE'];
    }

    #[DataProvider('filtrosInvalidos')]
    public function test_filtros_invalidos_no_llegan_a_red(array $p, string $codigo): void
    {
        $this->error($codigo, fn () => app(CatalogoMuestrasSequias::class)->consultar(...$p));
    }

    public static function modificacionesInvalidas(): iterable
    {
        foreach (['version', 'fuente', 'versionTerritorial', 'crs', 'orden', 'extra', 'incompleto', 'duplicado',
            'provincia', 'municipio', 'fuera', 'coordenadaTexto', 'coordenadaPrecision', 'pilotoMovido', 'seleccion', 'generacion', 'id'] as $tipo) {
            yield $tipo => [$tipo];
        }
    }

    #[DataProvider('modificacionesInvalidas')]
    public function test_catalogo_inconsistente_no_se_admite(string $tipo): void
    {
        $d = $this->documento();
        switch ($tipo) {
            case 'version': $d['version'] = 'otra';
                break;
            case 'fuente': $d['fuentesTerritorialesSha256']['datos/catalogo.json'] = str_repeat('0', 64);
                break;
            case 'versionTerritorial': $d['versionCatalogoTerritorial'] = 'otra';
                break;
            case 'crs': $d['crs'] = 'EPSG:3857';
                break;
            case 'orden': $d['ordenCoordenadas'] = ['latitud', 'longitud'];
                break;
            case 'extra': $d['spi'] = 0;
                break;
            case 'incompleto': array_pop($d['muestras']);
                break;
            case 'duplicado': $d['muestras'][1] = $d['muestras'][0];
                break;
            case 'provincia': $d['muestras'][1]['provincia'] = 'BO0702';
                break;
            case 'municipio': $d['muestras'][1]['municipio'] = 'BO99';
                break;
            case 'fuera': $d['muestras'][1]['coordenadaSolicitada'] = [-70, -40];
                break;
            case 'coordenadaTexto': $d['muestras'][1]['coordenadaSolicitada'] = ['-63', '-17'];
                break;
            case 'coordenadaPrecision': $d['muestras'][1]['coordenadaSolicitada'][0] += 1e-8;
                break;
            case 'pilotoMovido': $d['muestras'][0]['coordenadaSolicitada'][0] += 0.001;
                break;
            case 'seleccion': $d['muestras'][1]['seleccion'] = 'centroide_sin_verificar';
                break;
            case 'generacion': $d['generacion']['algoritmo'] = 'otro';
                break;
            case 'id': $d['muestras'][1]['identificador'] = 'otro';
                break;
        }
        $this->error('CATALOGO_MUESTRAS_INVALIDO', fn () => $this->cargarDocumento($d)->consultar());
    }

    public function test_un_punto_asignado_al_municipio_equivocado_se_rechaza_aunque_este_en_santa_cruz(): void
    {
        $d = $this->documento();
        $d['muestras'][1]['coordenadaSolicitada'] = $d['muestras'][2]['coordenadaSolicitada'];
        $this->error('CATALOGO_MUESTRAS_INVALIDO', fn () => $this->cargarDocumento($d)->consultar());
    }

    public function test_geojson_exporta_puntos_contexto_y_null_sin_hacer_peticiones(): void
    {
        $this->assertSame(0, Artisan::call('sequias:muestras', ['--provincia' => 'BO0701', '--geojson' => true]));
        $g = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('FeatureCollection', $g['type']);
        $this->assertCount(5, $g['features']);
        $this->assertSame('Point', $g['features'][0]['geometry']['type']);
        $this->assertSame([-63.1821, -17.7833], $g['features'][0]['geometry']['coordinates']);
        $this->assertNull($g['features'][0]['properties']['spi']);
        $this->assertSame(2, Artisan::call('sequias:muestras', ['--muestra' => 'no-existe']));
        $this->assertSame('MUESTRA_NO_DISPONIBLE', json_decode(Artisan::output(), true)['error']['codigo']);
        $this->getJson('/api/v1/sequias')->assertStatus(503);
        Http::assertNothingSent();
    }

    public function test_interior_estricto_excluye_bordes_huecos_y_admite_multipoligonos_sin_cambiar_focos(): void
    {
        $g = app(GeometriaTerritorial::class);
        $p = ['type' => 'Polygon', 'coordinates' => [[[0, 0], [10, 0], [10, 10], [0, 10], [0, 0]], [[3, 3], [7, 3], [7, 7], [3, 7], [3, 3]]]];
        $this->assertTrue($g->interiorEstricto($p, 1, 1));
        foreach ([[0, 5], [3, 5], [5, 5], [11, 5]] as [$x, $y]) {
            $this->assertFalse($g->interiorEstricto($p, $x, $y));
        }
        $this->assertTrue($g->contiene($p, 0, 5));
        $this->assertTrue($g->contiene($p, 3, 5));
        $multi = ['type' => 'MultiPolygon', 'coordinates' => [$p['coordinates'], [[[20, 0], [30, 0], [30, 10], [20, 10], [20, 0]]]]];
        $this->assertTrue($g->interiorEstricto($multi, 25, 5));
        $this->assertFalse($g->interiorEstricto($multi, 20, 5));
    }
}
