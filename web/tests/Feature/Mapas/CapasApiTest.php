<?php

namespace Tests\Feature\Mapas;

use App\Enums\RolCuenta;
use App\Models\User;
use App\Services\Mapas\Radar\PublicadorRaster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CapasApiTest extends TestCase
{
    use RefreshDatabase;

    private string $directorio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directorio = storage_path('framework/views-pruebas/capas-api-'.bin2hex(random_bytes(8)));
        mkdir($this->directorio, 0700, true);
        config(['capas.directorio' => $this->directorio]);
        file_put_contents($this->directorio.'/prueba.png', $this->png());
        (new PublicadorRaster)->publicar($this->directorio, ['version' => 1, 'cuadros' => [[
            'id' => 'prueba-api', 'capa' => 'temperatura', 'archivo' => 'prueba.png', 'instante' => '2026-10-28T12:00:00Z',
            'codificacion' => 'temp-era5', 'fuente' => 'Textura técnica de prueba', 'producto' => 'Contrato controlado',
            'rejilla' => ['proyeccion' => 'EPSG:4326', 'registro' => 'centros', 'filas' => 'sur-norte', 'oeste' => -180, 'este' => 180, 'sur' => -90, 'norte' => 90],
        ]]]);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directorio.'/*') as $archivo) {
            if (is_file($archivo) || is_link($archivo)) {
                unlink($archivo);
            }
        }
        rmdir($this->directorio);
        parent::tearDown();
    }

    private function png(): string
    {
        $chunk = fn ($tipo, $datos) => pack('N', strlen($datos)).$tipo.$datos.hash('crc32b', $tipo.$datos, true);

        // Imagen de prueba, sin valores ambientales reales.
        return "\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', 2, 2, 8, 6, 0, 0, 0)).
            $chunk('IDAT', gzcompress("\0\0\0\0\xff\xff\0\0\xff\0\x80\0\0\xff\0\0\0\0")).$chunk('IEND', '');
    }

    private function usuario(bool $activo = true): User
    {
        $usuario = User::factory()->create(['rol' => RolCuenta::Visualizador, 'activo' => $activo]);
        Sanctum::actingAs($usuario, ['cuenta']);

        return $usuario;
    }

    public function test_catalogo_y_png_requieren_cuenta_activa_y_habilidad(): void
    {
        $this->getJson('/api/v1/mapa/capas')->assertUnauthorized();
        $this->getJson('/api/v1/mapa/capas/cuadros/prueba-api.png')->assertUnauthorized();
        $this->usuario(false);
        $this->getJson('/api/v1/mapa/capas')->assertUnauthorized();
        $this->getJson('/api/v1/mapa/capas/cuadros/prueba-api.png')->assertUnauthorized();
        $usuario = $this->usuario();
        Sanctum::actingAs($usuario, []);
        $this->getJson('/api/v1/mapa/capas')->assertForbidden();
        $this->getJson('/api/v1/mapa/capas/cuadros/prueba-api.png')->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_un_visualizador_recibe_urls_api_y_el_png_verificado(): void
    {
        $this->usuario();
        $this->getJson('/api/v1/mapa/capas')->assertOk()
            ->assertJsonPath('capas.0.cuadros.0.url', url('/api/v1/mapa/capas/cuadros/prueba-api.png'));
        $respuesta = $this->get('/api/v1/mapa/capas/cuadros/prueba-api.png')->assertOk()
            ->assertHeader('Content-Type', 'image/png')->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame($this->png(), $respuesta->getContent());
        Http::assertNothingSent();
    }

    public function test_catalogo_y_png_comparten_un_limite_propio_sin_bloquear_la_cuenta(): void
    {
        $this->usuario();
        for ($i = 0; $i < 120; $i++) {
            $ruta = $i % 2 === 0 ? '/api/v1/mapa/capas' : '/api/v1/mapa/capas/cuadros/prueba-api.png';
            $this->getJson($ruta)->assertOk();
        }
        $this->getJson('/api/v1/mapa/capas')->assertStatus(429);
        $this->getJson('/api/v1/cuenta')->assertOk();
        Http::assertNothingSent();
    }

    public function test_agotar_otra_consulta_no_bloquea_las_capas(): void
    {
        $this->usuario();
        for ($i = 0; $i < 30; $i++) {
            $this->getJson('/api/v1/cuenta')->assertOk();
        }
        $this->getJson('/api/v1/cuenta')->assertStatus(429);
        $this->getJson('/api/v1/mapa/capas')->assertOk();
        $this->get('/api/v1/mapa/capas/cuadros/prueba-api.png')->assertOk();
        Http::assertNothingSent();
    }
}
