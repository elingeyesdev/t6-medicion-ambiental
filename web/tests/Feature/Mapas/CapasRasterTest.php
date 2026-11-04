<?php

namespace Tests\Feature\Mapas;

use App\Enums\RolCuenta;
use App\Models\User;
use App\Services\Mapas\Capas\ErroresConsultaMapa;
use App\Services\Mapas\Radar\PublicadorRaster;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CapasRasterTest extends TestCase
{
    private string $directorio;

    protected function setUp(): void
    {
        parent::setUp();
        // Sin migraciones, cuentas persistidas ni SQL. También detecta accesos accidentales.
        DB::shouldReceive('connection')->never();
        $this->directorio = storage_path('framework/views-pruebas/capas-'.bin2hex(random_bytes(8)));
        mkdir($this->directorio, 0700, true);
        config(['capas.directorio' => $this->directorio]);
        $this->app->make(ExceptionHandler::class)->renderable(new ErroresConsultaMapa);
        Route::middleware('web')->group(base_path('../docs/integracion62/rutas-web.php'));
        Route::getRoutes()->refreshNameLookups();
    }

    protected function tearDown(): void
    {
        // Solo el directorio aleatorio propio, sin seguir enlaces ni borrar directorios ajenos.
        foreach (glob($this->directorio.'/*') as $archivo) {
            if (is_file($archivo) || is_link($archivo)) {
                unlink($archivo);
            }
        }
        rmdir($this->directorio);
        parent::tearDown();
    }

    private function usuario(RolCuenta $rol = RolCuenta::Visualizador, bool $activo = true): User
    {
        $u = new User;
        $u->forceFill(['id' => 6200, 'rol' => $rol, 'activo' => $activo, 'sesion_version' => 1]);
        $this->actingAs($u);

        return $u;
    }

    private function png(): string
    {
        $chunk = fn ($t, $s) => pack('N', strlen($s)).$t.$s.hash('crc32b', $t.$s, true);

        // Textura técnica 2x2, nunca presentada como datos ambientales.
        return "\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', 2, 2, 8, 6, 0, 0, 0)).
            $chunk('IDAT', gzcompress("\0\0\0\0\xff\xff\0\0\xff\0\x80\0\0\xff\0\0\0\0")).$chunk('IEND', '');
    }

    private function cuadro(array $cambios = []): array
    {
        return array_replace([
            'id' => 'prueba-62', 'capa' => 'temperatura', 'archivo' => 'prueba.png', 'instante' => '2026-10-28T12:00:00Z',
            'codificacion' => 'temp-era5', 'fuente' => 'Textura técnica de prueba', 'producto' => 'Contrato controlado',
            'rejilla' => ['proyeccion' => 'EPSG:4326', 'registro' => 'centros', 'filas' => 'sur-norte', 'oeste' => -180, 'este' => 180, 'sur' => -90, 'norte' => 90],
        ], $cambios);
    }

    private function publicar(array $cuadros = []): void
    {
        file_put_contents($this->directorio.'/prueba.png', $this->png());
        (new PublicadorRaster)->publicar($this->directorio, ['version' => 1, 'cuadros' => $cuadros ?: [$this->cuadro()]]);
    }

    public function test_requiere_cuenta_activa_y_ambos_permisos_incluso_para_png(): void
    {
        foreach (['/mapa/capas', '/mapa/capas/cuadros/prueba-62.png', '/mapa/poi?texto=museo&pais=es'] as $ruta) {
            $this->getJson($ruta)->assertUnauthorized();
        }
        $this->usuario(activo: false);
        $this->getJson('/mapa/capas')->assertUnauthorized()->assertJsonPath('error.codigo', 'NO_AUTENTICADO');
        $this->usuario();
        Gate::define('datos.consultar', fn () => false);
        $this->getJson('/mapa/capas')->assertForbidden();
        $this->getJson('/mapa/capas/cuadros/prueba-62.png')->assertForbidden();
        $this->getJson('/mapa/poi?texto=museo&pais=es')->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_conserva_acceso_de_los_roles_originales_incluido_visualizador_demo(): void
    {
        foreach (RolCuenta::cases() as $rol) {
            $this->usuario($rol);
            $this->getJson('/mapa/capas')->assertOk()->assertJsonPath('estado', 'sin_insumos');
        }
        Http::assertNothingSent();
    }

    public function test_solo_enumera_fechas_publicadas_y_no_filtra_archivos_privados(): void
    {
        $this->usuario();
        $this->publicar([$this->cuadro(['id' => 'b', 'instante' => '2026-10-28T18:00:00Z']), $this->cuadro(['id' => 'a'])]);
        $this->getJson('/mapa/capas')->assertOk()->assertJsonPath('estado', 'disponible')
            ->assertJsonCount(11, 'capas')->assertJsonCount(2, 'capas.0.cuadros')
            ->assertJsonPath('capas.0.cuadros.0.id', 'a')->assertJsonPath('capas.0.cuadros.1.id', 'b')
            ->assertJsonPath('capas.0.cuadros.0.origen', 'estimado')->assertJsonPath('capas.0.unidad', '°C')
            ->assertJsonPath('capas.0.cuadros.0.url', '/mapa/capas/cuadros/a.png')
            ->assertDontSee($this->directorio)->assertDontSee('prueba.png')->assertDontSee('sha256');
        Http::assertNothingSent();
    }

    public function test_entrega_png_con_huella_verificada_y_rechaza_corrupcion_sin_otra_fecha_de_respaldo(): void
    {
        $this->usuario();
        $this->publicar();
        $r = $this->get('/mapa/capas/cuadros/prueba-62.png')->assertOk()->assertHeader('Content-Type', 'image/png')
            ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame($this->png(), $r->getContent());
        $datos = json_decode(file_get_contents($this->directorio.'/catalogo.json'), true);
        $datos['cuadros'][0]['sha256'] = str_repeat('0', 64);
        file_put_contents($this->directorio.'/catalogo.json', json_encode($datos));
        $this->getJson('/mapa/capas/cuadros/prueba-62.png')->assertStatus(503)->assertJsonPath('error.codigo', 'CUADRO_NO_DISPONIBLE');
        $this->getJson('/mapa/capas/cuadros/desconocido.png')->assertNotFound();
    }

    public function test_faltantes_no_se_ofrecen_en_la_linea_temporal(): void
    {
        $this->usuario();
        $this->publicar();
        unlink($this->directorio.'/prueba.png');
        $this->getJson('/mapa/capas')->assertOk()->assertJsonPath('estado', 'sin_insumos')
            ->assertJsonPath('faltantes', 1)->assertJsonCount(0, 'capas.0.cuadros');
        $this->getJson('/mapa/capas/cuadros/prueba-62.png')->assertNotFound();
    }

    public function test_catalogo_malformado_no_expone_rutas_ni_finge_exito(): void
    {
        $this->usuario();
        foreach (['{', '{"version":2,"cuadros":[]}', '{"version":1,"cuadros":"x"}'] as $json) {
            file_put_contents($this->directorio.'/catalogo.json', $json);
            $this->getJson('/mapa/capas')->assertStatus(503)->assertJsonPath('error.codigo', 'CAPAS_NO_DISPONIBLES')->assertDontSee($this->directorio);
        }
    }

    public function test_publicador_rechaza_rutas_arbitrarias_codificaciones_falsas_y_fechas_inventadas(): void
    {
        $this->publicar();
        $original = file_get_contents($this->directorio.'/catalogo.json');
        foreach ([['archivo' => '../prueba.png'], ['archivo' => 'https://example.invalid/a.png'], ['archivo' => 'C:/a.png'],
            ['codificacion' => 'uv'], ['instante' => '2026-02-31T12:00:00Z'], ['instante' => '2026-10-28T12:00:00'],
            ['capa' => 'observado'], ['fuente' => ''], ['rejilla' => ['proyeccion' => 'EPSG:3857']]] as $malo) {
            try {
                (new PublicadorRaster)->publicar($this->directorio, ['version' => 1, 'cuadros' => [$this->cuadro($malo)]]);
                $this->fail('Se aceptó una receta inválida.');
            } catch (\UnexpectedValueException) {
                $this->assertSame($original, file_get_contents($this->directorio.'/catalogo.json'));
            }
        }
        Http::assertNothingSent();
    }

    public function test_rechaza_png_truncado_y_limita_recursos_sin_reemplazar_publicacion(): void
    {
        $this->publicar();
        $original = file_get_contents($this->directorio.'/catalogo.json');
        foreach ([substr($this->png(), 0, -12), str_repeat('x', 100), str_repeat('x', config('capas.max_bytes') + 1)] as $bytes) {
            file_put_contents($this->directorio.'/prueba.png', $bytes);
            try {
                (new PublicadorRaster)->publicar($this->directorio, ['version' => 1, 'cuadros' => [$this->cuadro()]]);
                $this->fail('Se aceptó un PNG inválido.');
            } catch (\UnexpectedValueException) {
                $this->assertSame($original, file_get_contents($this->directorio.'/catalogo.json'));
            }
        }
    }

    public function test_rechaza_fechas_duplicadas_para_no_elegir_una_fuente_implicitamente(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->publicar([$this->cuadro(), $this->cuadro(['id' => 'otro'])]);
    }

    public function test_componente_blade_tiene_controles_accesibles_y_no_contiene_tokens(): void
    {
        $html = view('components.mapas.capas')->render();
        foreach (['capas-mapa', 'capa-recorrido', 'Comparar capas o fechas', 'Leer capas en el centro', 'Sin cobertura de Bolivia.', 'aria-live="polite"'] as $texto) {
            $this->assertStringContainsString($texto, $html);
        }
        $this->assertStringNotContainsString('access_token', $html);
        Http::assertNothingSent();
    }
}
