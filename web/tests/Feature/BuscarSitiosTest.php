<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BuscarSitiosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Cache::flush();
        config(['mapas.token_publico' => 'pk.prueba-local']);
    }

    private function cliente(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['cuenta']);
    }

    private function sitios(): array
    {
        return ['type' => 'FeatureCollection', 'features' => [[
            'type' => 'Feature', 'geometry' => ['type' => 'Point', 'coordinates' => [0, 0]],
            'properties' => ['name' => 'Parque de prueba', 'feature_type' => 'poi', 'full_address' => 'Ciudad de prueba'],
        ]]];
    }

    public function test_sitios_usan_su_endpoint_y_solo_ubicacion_elegida_sin_identidad_del_usuario(): void
    {
        $this->cliente();
        Http::fake(['api.mapbox.com/*' => Http::response($this->sitios())]);
        $this->getJson('/api/v1/mapa/buscar?texto=Parque&tipo=sitio&latitud=0&longitud=0&email=privado&url=https://example.invalid')
            ->assertOk()->assertJsonPath('fuente', 'Mapbox')->assertJsonPath('datos.0.nombre', 'Parque de prueba')
            ->assertJsonPath('datos.0.descripcion', 'Ciudad de prueba')->assertJsonPath('datos.0.latitud', 0)
            ->assertJsonPath('datos.0.longitud', 0)->assertJsonPath('datos.0.limites', null)->assertDontSee('pk.prueba-local');
        Http::assertSent(fn ($r) => str_starts_with($r->url(), 'https://api.mapbox.com/search/searchbox/v1/forward?') &&
            $r['q'] === 'Parque' && $r['types'] === 'poi' && $r['proximity'] === '0,0' && $r['limit'] === 6 &&
            ! isset($r['email']) && ! isset($r['url']) && ! isset($r['session_token']) && ! isset($r['auto_complete']));
        $this->assertDatabaseCount('puntos_ambientales', 0);
        $this->assertDatabaseCount('lecturas_ambientales', 0);
    }

    public function test_rechaza_tipo_y_ubicacion_incompleta_o_fuera_de_rango_antes_de_consultar(): void
    {
        $this->cliente();
        foreach (['tipo=otro', 'tipo[]=sitio', 'tipo=sitio&latitud=0', 'tipo=sitio&longitud=0', 'tipo=sitio&latitud=91&longitud=0', 'tipo=sitio&latitud=0&longitud=181', 'tipo=sitio&latitud[]=0&longitud=0', 'tipo=sitio&latitud=1e309&longitud=0'] as $parametros) {
            $this->getJson('/api/v1/mapa/buscar?texto=Parque&'.$parametros)->assertUnprocessable();
        }
        Http::assertNothingSent();
    }

    public function test_sin_posicion_no_se_inventa_un_centro_y_los_resultados_no_se_almacenan(): void
    {
        $this->cliente();
        Http::fake(['api.mapbox.com/*' => Http::response($this->sitios())]);
        for ($i = 0; $i < 2; $i++) {
            $this->getJson('/api/v1/mapa/buscar?texto=Parque&tipo=sitio')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        }
        Http::assertSentCount(2);
        Http::assertSent(fn ($r) => ! isset($r['proximity']));
        $this->assertDatabaseCount('puntos_ambientales', 0);
    }

    public function test_respuesta_invalida_no_se_presenta_como_sitio_ni_como_cero_resultados(): void
    {
        $this->cliente();
        $respuesta = null;
        Http::fake(function () use (&$respuesta) {
            return Http::response($respuesta);
        });
        $candidatos = [];
        foreach ([['geometry', 'type', 'Polygon'], ['geometry', 'coordinates', [180, 91]], ['geometry', 'coordinates', ['0', 0]], ['properties', 'feature_type', 'address'], ['properties', 'name', ''], ['properties', 'full_address', []]] as [$seccion, $campo, $valor]) {
            $candidato = $this->sitios();
            $candidato['features'][0][$seccion][$campo] = $valor;
            $candidatos[] = $candidato;
        }
        $candidatos[] = ['type' => 'FeatureCollection', 'features' => array_fill(0, 7, $this->sitios()['features'][0])];
        $candidatos[] = '<html>error privado</html>';
        foreach ($candidatos as $respuesta) {
            $this->getJson('/api/v1/mapa/buscar?texto=Parque&tipo=sitio')->assertStatus(503)->assertJsonPath('error.codigo', 'BUSCADOR_NO_DISPONIBLE')->assertDontSee('privado');
        }
    }

    public function test_vacio_y_fallo_del_proveedor_se_distinguen_sin_exponer_sus_mensajes(): void
    {
        $this->cliente();
        Http::fake(['api.mapbox.com/*' => Http::sequence()->push(['type' => 'FeatureCollection', 'features' => []])->push(['message' => 'pk.prueba-local detalle privado'], 403)]);
        $this->getJson('/api/v1/mapa/buscar?texto=Parque&tipo=sitio')->assertOk()->assertJsonPath('datos', []);
        $this->getJson('/api/v1/mapa/buscar?texto=Parque&tipo=sitio')->assertStatus(503)->assertDontSee('pk.prueba-local')->assertDontSee('detalle privado');
    }

    public function test_cambiar_tipo_no_amplia_el_limite_por_cuenta(): void
    {
        $this->cliente();
        Http::fake(function ($r) {
            return Http::response(str_contains($r->url(), '/searchbox/') ? $this->sitios() : ['type' => 'FeatureCollection', 'features' => []]);
        });
        for ($i = 0; $i < 12; $i++) {
            $this->getJson('/api/v1/mapa/buscar?texto=Parque&tipo='.($i % 2 ? 'sitio' : 'lugar'))->assertOk();
        }
        $this->getJson('/api/v1/mapa/buscar?texto=Parque&tipo=sitio')->assertStatus(429);
        Http::assertSentCount(12);
    }

    public function test_la_web_usa_el_mismo_contrato_y_exige_una_cuenta(): void
    {
        $this->getJson('/mapa/buscar?texto=Parque&tipo=sitio')->assertUnauthorized();
        Http::fake(['api.mapbox.com/*' => Http::response($this->sitios())]);
        $this->actingAs(User::factory()->create())->getJson('/mapa/buscar?texto=Parque&tipo=sitio')->assertOk()->assertJsonPath('datos.0.nombre', 'Parque de prueba');
    }
}
