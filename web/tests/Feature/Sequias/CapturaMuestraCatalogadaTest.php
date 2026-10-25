<?php

namespace Tests\Feature\Sequias;

use App\Services\Sequias\Experimental\CapturaMuestraCatalogada;
use App\Services\Sequias\RepositorioSeriesPrecipitacion;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CapturaMuestraCatalogadaTest extends TestCase
{
    private string $directorio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directorio = base_path('../tmp/tests-muestra-'.bin2hex(random_bytes(12)));
        mkdir($this->directorio, 0700, true);
        config(['sequias.directorio_series' => $this->directorio.'/almacen']);
        app()->forgetInstance(RepositorioSeriesPrecipitacion::class);
        Cache::flush();
    }

    protected function tearDown(): void
    {
        $ruta = realpath($this->directorio);
        $padre = realpath(base_path('../tmp'));
        if ($ruta !== false) {
            if ($padre === false || ! str_starts_with($ruta, $padre.DIRECTORY_SEPARATOR)
                || ! preg_match('/^tests-muestra-[a-f0-9]{24}$/D', basename($ruta))) {
                throw new \LogicException('Ruta de limpieza ajena a la prueba de muestra.');
            }
            File::deleteDirectory($ruta);
        }
        parent::tearDown();
    }

    private function simular(bool $incompleta = false): void
    {
        $datos = [...json_decode(file_get_contents(base_path('../docs/ejemplos/sequias/serie-completa.json')), true, flags: JSON_THROW_ON_ERROR),
            'longitude' => -63.25, 'latitude' => -17.75];
        if ($incompleta) {
            $datos['daily']['precipitation_sum'][1] = null;
        }
        Http::fake(fn () => Http::response($datos, 200));
    }

    public function test_piloto_usa_coordenadas_registradas_y_conserva_municipio_aunque_centro_era5_este_en_otro(): void
    {
        $this->simular();
        $r = app(CapturaMuestraCatalogada::class)->ejecutar('scz-piloto-01', '2024-06-01', '2024-06-30');
        $this->assertSame('BO070101', $r['muestraCatalogada']['municipio']);
        $this->assertSame('BO0701', $r['muestraCatalogada']['provincia']);
        $this->assertSame([-63.1821, -17.7833], $r['muestraCatalogada']['coordenadaSolicitada']);
        $this->assertSame('BO070103', $r['municipioDelCentroCelda']);
        $this->assertNull($r['motivoAsignacionCentro']);
        $this->assertStringContainsString('se conserva el contexto', implode(' ', $r['advertencias']));
        $this->assertCount(2, $r['muestraCatalogada']['fuentesTerritorialesSha256']);
        $this->assertSame('scz-piloto-01', $r['version']['procedencia']['muestra']);
        Http::assertSent(function (Request $q) {
            parse_str(parse_url($q->url(), PHP_URL_QUERY), $p);

            return $p['longitude'] === '-63.1821' && $p['latitude'] === '-17.7833';
        });
        $cache = app(CapturaMuestraCatalogada::class)->ejecutar('scz-piloto-01', '2024-06-01', '2024-06-30');
        $this->assertSame('cache_captura', $cache['tipoObtencion']);
        $this->assertSame($r['muestraCatalogada'], $cache['muestraCatalogada']);
        $this->assertSame($r['version'], $cache['version']);
        Http::assertSentCount(1);
    }

    public function test_comando_rechaza_muestra_ajena_y_filtros_incompatibles_antes_de_http(): void
    {
        foreach ([['muestra' => '../fuera'], ['muestra' => 'scz-piloto-01', '--provincia' => 'BO0702'],
            ['muestra' => 'scz-piloto-01', '--municipio' => 'BO070102']] as $p) {
            $this->assertSame(2, Artisan::call('sequias:capturar-muestra', [...$p, '--desde' => '2024-06-01', '--hasta' => '2024-06-30']));
            $this->assertArrayHasKey('error', json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR));
        }
        Http::assertNothingSent();
        $this->assertDirectoryDoesNotExist($this->directorio.'/almacen');
    }

    public function test_consola_preserva_salida_incompleta_contexto_y_modulo_en_preparacion(): void
    {
        $this->simular(true);
        $this->assertSame(1, Artisan::call('sequias:capturar-muestra', ['muestra' => 'scz-piloto-01', '--desde' => '2024-06-01', '--hasta' => '2024-06-30']));
        $r = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('incompleta', $r['version']['informe']['estado']);
        $this->assertSame('BO070101', $r['muestraCatalogada']['municipio']);
        $this->assertSame('BO070103', $r['municipioDelCentroCelda']);
        $this->assertArrayNotHasKey('meses', $r['version']['informe']);
        $this->getJson('/api/v1/inundaciones')->assertStatus(503);
        Http::assertSentCount(1);
    }

    public function test_fallo_del_proveedor_se_conserva_como_error_sin_simular_celdas_o_datos(): void
    {
        Http::fake(fn () => Http::response('Error controlado', 503));
        $this->assertSame(2, Artisan::call('sequias:capturar-muestra', ['muestra' => 'scz-piloto-01', '--desde' => '2024-06-01', '--hasta' => '2024-06-30']));
        $r = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('PROVEEDOR_NO_DISPONIBLE', $r['error']['codigo']);
        $this->assertArrayNotHasKey('muestraCatalogada', $r);
        $this->assertDirectoryDoesNotExist($this->directorio.'/almacen');
        Http::assertSentCount(1);
    }
}
