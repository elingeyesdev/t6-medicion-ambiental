<?php

namespace Tests\Feature\Inundaciones;

use App\Models\User;
use App\Services\Api\ErrorConsulta;
use App\Services\Inundaciones\CatalogoProductosAgua;
use App\Services\Inundaciones\PublicadorProductosAgua;
use App\Services\Inundaciones\ValidadorProductoAgua;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RegistrarProductoAguaTest extends TestCase
{
    private string $directorio;

    private CatalogoProductosAgua $catalogo;

    private PublicadorProductosAgua $publicador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directorio = sys_get_temp_dir().'/geosur-publicar112-'.bin2hex(random_bytes(12));
        config(['productos_agua.directorio' => $this->directorio]);
        $this->catalogo = $this->app->make(CatalogoProductosAgua::class);
        $this->publicador = $this->app->make(PublicadorProductosAgua::class);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directorio);
        parent::tearDown();
    }

    /** Solo datos sintéticos del contrato para probar el registro. */
    private function dato(): array
    {
        $dato = json_decode(file_get_contents(base_path('../compartido/api/inundaciones/ejemplos/cambio.json')), true, 64, JSON_THROW_ON_ERROR);
        $dato['tipoEvidencia'] = 'resultado_validado';

        return $dato;
    }

    public function test_registra_y_la_api_lee_la_misma_version_sin_descargar(): void
    {
        $sha = $this->publicador->registrar($this->dato());
        Sanctum::actingAs(User::factory()->make(['id' => 123]), ['cuenta']);
        $this->getJson('/api/v1/inundaciones/consulta?territorio=BO070101&referencia=2024-09-01&comparacion=2025-03-07')
            ->assertOk()->assertExactJson($this->dato())->assertHeader('X-Producto-SHA256', $sha);
        $this->assertFileExists($this->directorio.'/'.$sha.'.json');
        Http::assertNothingSent();
    }

    public function test_repetir_no_duplica_y_preserva_el_indice(): void
    {
        $a = $this->publicador->registrar($this->dato());
        $indice = file_get_contents($this->directorio.'/indice.json');
        $this->assertSame($a, $this->publicador->registrar($this->dato()));
        $this->assertSame($indice, file_get_contents($this->directorio.'/indice.json'));
        $this->assertCount(1, $this->catalogo->catalogo()['productos']);
    }

    public function test_un_cambio_exige_reemplazo_expreso_y_conserva_ambas_versiones(): void
    {
        $a = $this->publicador->registrar($this->dato());
        $indice = file_get_contents($this->directorio.'/indice.json');
        $nuevo = $this->dato();
        $nuevo['fuente']['metodo'] = 'Revisión sintética del contrato';
        try {
            $this->publicador->registrar($nuevo);
            $this->fail('Debía exigir --reemplazar.');
        } catch (ErrorConsulta $error) {
            $this->assertSame('PRODUCTO_EXISTENTE', $error->codigo);
        }
        $this->assertSame($indice, file_get_contents($this->directorio.'/indice.json'));
        $b = $this->publicador->registrar($nuevo, true);
        $this->assertNotSame($a, $b);
        $this->assertSame($b, $this->catalogo->catalogo()['productos'][0]['id']);
        $this->assertFileExists($this->directorio.'/'.$a.'.json');
        $this->assertFileExists($this->directorio.'/'.$b.'.json');
    }

    public function test_no_reetiqueta_ejemplos_y_un_error_conserva_el_producto_anterior(): void
    {
        $a = $this->publicador->registrar($this->dato());
        $indice = file_get_contents($this->directorio.'/indice.json');
        foreach (['prueba', 'territorio', 'area'] as $caso) {
            $dato = $this->dato();
            if ($caso === 'prueba') {
                $dato['tipoEvidencia'] = 'prueba_controlada';
            } elseif ($caso === 'territorio') {
                $dato['territorio']['identificador'] = 'BO0799';
            } else {
                $dato['resumen']['aguaNuevaKm2'] = -1;
            }
            try {
                $this->publicador->registrar($dato, true);
                $this->fail('Debía rechazar el producto.');
            } catch (ErrorConsulta $error) {
                $this->assertSame('PRODUCTO_INVALIDO', $error->codigo);
            }
            $this->assertSame($indice, file_get_contents($this->directorio.'/indice.json'));
            $this->assertSame($a, $this->catalogo->catalogo()['productos'][0]['id']);
        }
    }

    public function test_un_archivo_preexistente_alterado_no_sustituye_la_version_activa(): void
    {
        $a = $this->publicador->registrar($this->dato());
        $nuevo = $this->dato();
        $nuevo['fuente']['metodo'] = 'Otro ensayo del contrato';
        $raw = json_encode($nuevo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
        $b = hash('sha256', $raw);
        file_put_contents($this->directorio.'/'.$b.'.json', 'alterado');
        try {
            $this->publicador->registrar($nuevo, true);
            $this->fail('Debía rechazar el archivo alterado.');
        } catch (ErrorConsulta $error) {
            $this->assertSame('PRODUCTO_INVALIDO', $error->codigo);
        }
        $this->assertSame($a, $this->catalogo->catalogo()['productos'][0]['id']);
    }

    public function test_el_comando_registra_un_archivo_local_y_no_descarga(): void
    {
        mkdir($this->directorio, 0700);
        $entrada = $this->directorio.'/entrada-controlada.json';
        file_put_contents($entrada, json_encode($this->dato(), JSON_THROW_ON_ERROR));
        $this->artisan('agua:registrar', ['archivo' => $entrada])->assertSuccessful();
        $this->assertCount(1, $this->catalogo->catalogo()['productos']);
        Http::assertNothingSent();
    }

    public function test_comando_rechaza_json_invalido_sin_crear_indice(): void
    {
        mkdir($this->directorio, 0700);
        $entrada = $this->directorio.'/entrada-controlada.json';
        file_put_contents($entrada, '{');
        $this->artisan('agua:registrar', ['archivo' => $entrada])->assertFailed();
        $this->assertFileDoesNotExist($this->directorio.'/indice.json');
    }

    public function test_el_validador_comparte_el_esquema_y_rechaza_una_estructura_sin_datos(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->app->make(ValidadorProductoAgua::class)->validar([]);
    }
}
