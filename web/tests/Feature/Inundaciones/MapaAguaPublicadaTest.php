<?php

namespace Tests\Feature\Inundaciones;

use App\Models\User;
use App\Services\Inundaciones\PublicadorProductosAgua;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MapaAguaPublicadaTest extends TestCase
{
    use RefreshDatabase;

    private string $directorio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directorio = sys_get_temp_dir().'/geosur-vis113-'.bin2hex(random_bytes(12));
        config(['productos_agua.directorio' => $this->directorio]);
        $this->actingAs(User::factory()->create());
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directorio);
        parent::tearDown();
    }

    /** Dato sintético de contrato, aislado de la instalación local del usuario. */
    private function registrar(): array
    {
        $dato = json_decode(file_get_contents(base_path('../compartido/api/inundaciones/ejemplos/cambio.json')), true, 64, JSON_THROW_ON_ERROR);
        $dato['tipoEvidencia'] = 'resultado_validado';
        $sha = $this->app->make(PublicadorProductosAgua::class)->registrar($dato);

        return [$sha, $dato];
    }

    public function test_no_hay_pantalla_ni_entrada_pendiente_sin_productos(): void
    {
        $this->get('/mapa')->assertOk()->assertDontSee('Agua e inundaciones');
        $this->get('/cuenta')->assertOk()->assertDontSee('Agua e inundaciones');
        $this->get('/mapa/agua')->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_el_menu_y_el_viso_solo_se_activan_con_un_producto_y_conservan_el_frente(): void
    {
        $this->registrar();
        $this->get('/cuenta')->assertOk()->assertSee('Agua e inundaciones')->assertSee(route('mapa.agua'), false);
        $respuesta = $this->get('/mapa/agua')->assertOk()->assertSee('id="mapa-monitoreo"', false)
            ->assertSee('data-panel-agua data-modo="publicado"', false)->assertSee('Actualizar lista')
            ->assertSee('Lugar')->assertSee('Comparación')->assertSee('Cerrar agua y cambios')
            ->assertDontSee('id="agua-ejemplos"', false)->assertDontSee('prueba_controlada');
        $this->assertSame(1, substr_count($respuesta->getContent(), 'id="mon-base"'));
        Http::assertNothingSent();
    }

    public function test_la_sesion_web_consulta_json_sin_exponer_un_token(): void
    {
        [$sha, $dato] = $this->registrar();
        $this->getJson('/mapa/agua/catalogo')->assertOk()->assertJsonPath('productos.0.id', $sha);
        $this->getJson('/mapa/agua/consulta?territorio=BO070101&referencia=2024-09-01&comparacion=2025-03-07')
            ->assertOk()->assertExactJson($dato)->assertHeader('X-Producto-SHA256', $sha);
        Http::assertNothingSent();
    }

    public function test_prod_permite_productos_guardados_y_sigue_bloqueando_ejemplos_locales(): void
    {
        $this->registrar();
        config(['inundaciones.revision_local' => true]);
        $this->app->instance('env', 'production');
        $this->get('/mapa/agua')->assertOk()->assertSee('id="agua-publicada"', false)->assertDontSee('id="agua-ejemplos"', false);
        $this->get('/revision/agua')->assertNotFound();
    }

    public function test_error_json_conserva_su_estado_y_no_muestra_rutas_o_trazas(): void
    {
        mkdir($this->directorio, 0700, true);
        file_put_contents($this->directorio.'/indice.json', '{"version":"agua-publicaciones-1","productos":{}}');
        $this->getJson('/mapa/agua/catalogo')->assertStatus(503)->assertJsonPath('error.codigo', 'PRODUCTO_INVALIDO')
            ->assertJsonMissingPath('exception')->assertJsonMissingPath('file')->assertJsonMissingPath('trace');
        $this->get('/mapa')->assertOk()->assertDontSee('Agua e inundaciones');
        $this->getJson('/mapa/agua/consulta?territorio=BO070101&referencia=2024-09-01&comparacion=2025-03-07&modo=prueba')
            ->assertStatus(422)->assertJsonPath('error.codigo', 'FILTROS_INVALIDOS');
    }

    public function test_cuenta_inactiva_no_puede_ver_ni_consultar_el_producto(): void
    {
        $this->registrar();
        $this->actingAs(User::factory()->make(['id' => 321, 'activo' => false]));
        $this->get('/mapa/agua')->assertRedirect('/login');
        $this->getJson('/mapa/agua/catalogo')->assertUnauthorized();
    }

    public function test_los_enlaces_antiguos_de_focos_conservan_su_destino(): void
    {
        $this->registrar();
        $this->get('/mapa?territorio=BO07')->assertRedirect(route('modulos.mostrar', ['modulo' => 'focos-calor', 'territorio' => 'BO07']));
        $this->get('/mapa/agua?producto=desconocido')->assertOk()->assertSee('id="agua-publicada"', false);
    }
}
