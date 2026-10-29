<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BuscarLugarTest extends TestCase
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

    private function lugares(array $cambios = []): array
    {
        return ['type' => 'FeatureCollection', 'features' => [array_replace([
            'text' => 'Lugar cero', 'place_name' => 'Lugar cero, referencia', 'center' => [0, 0], 'bbox' => [-1, -1, 1, 1],
        ], $cambios)]];
    }

    public function test_requiere_cuenta_activa_capacidad_y_texto_valido_antes_de_salir_a_la_red(): void
    {
        $this->getJson('/api/v1/mapa/buscar?texto=Tokio')->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create(), ['otro']);
        $this->getJson('/api/v1/mapa/buscar?texto=Tokio')->assertForbidden();
        Sanctum::actingAs(User::factory()->create(['activo' => false]), ['cuenta']);
        $this->getJson('/api/v1/mapa/buscar?texto=Tokio')->assertUnauthorized();
        $this->cliente();
        foreach (['', 'a', str_repeat('a', 121), 'Tokio;Japón', 'Tokio%3bJapón', str_repeat('palabra ', 21), "Tokio\nJapón"] as $texto) {
            $this->getJson('/api/v1/mapa/buscar?'.http_build_query(['texto' => $texto]))->assertUnprocessable();
        }
        $this->getJson('/api/v1/mapa/buscar?texto[]=Tokio')->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_busca_mundialmente_sin_enviar_datos_de_cuenta_y_preserva_coordenadas_cero(): void
    {
        $this->cliente();
        Http::fake(['api.mapbox.com/*' => Http::response($this->lugares())]);
        $this->getJson('/api/v1/mapa/buscar?texto=Tokio&url=https://example.invalid&email=privado&country=BO')
            ->assertOk()->assertJsonPath('fuente', 'Mapbox')->assertJsonPath('datos.0.latitud', 0)->assertJsonPath('datos.0.longitud', 0)
            ->assertJsonPath('datos.0.limites', [-1, -1, 1, 1])->assertDontSee('pk.prueba-local');
        Http::assertSent(fn ($r) => str_starts_with($r->url(), 'https://api.mapbox.com/search/geocode/v6/forward?') &&
            $r['q'] === 'Tokio' && $r['limit'] === 6 && $r['format'] === 'v5' && $r['autocomplete'] === 'false' &&
            ! isset($r['email']) && ! isset($r['country']) && ! $r->hasHeader('Authorization'));
        $this->assertDatabaseCount('puntos_ambientales', 0);
        $this->assertDatabaseCount('lecturas_ambientales', 0);
    }

    public function test_resultados_temporales_no_se_cachean_ni_se_guardan(): void
    {
        $this->cliente();
        Http::fake(['api.mapbox.com/*' => Http::response($this->lugares())]);
        for ($i = 0; $i < 2; $i++) {
            $this->getJson('/api/v1/mapa/buscar?texto=Tokio')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        }
        Http::assertSentCount(2);
        $this->assertDatabaseCount('puntos_ambientales', 0);
    }

    public function test_sin_resultados_es_distinto_de_un_error_y_no_se_inventa_una_ubicacion(): void
    {
        $this->cliente();
        Http::fake(['api.mapbox.com/*' => Http::sequence()->push(['type' => 'FeatureCollection', 'features' => []])->push(['message' => 'pk.prueba-local'], 403)]);
        $this->getJson('/api/v1/mapa/buscar?texto=Tokio')->assertOk()->assertJsonPath('datos', []);
        $this->getJson('/api/v1/mapa/buscar?texto=Tokio')->assertStatus(503)->assertJsonPath('error.codigo', 'BUSCADOR_NO_DISPONIBLE')->assertDontSee('pk.prueba-local');
    }

    public function test_rechaza_respuestas_invalidas_y_no_usa_coordenadas_inventadas(): void
    {
        $this->cliente();
        $respuesta = null;
        Http::fake(function () use (&$respuesta) {
            return Http::response($respuesta);
        });
        foreach ([['center' => [0, 91]], ['center' => ['0', 0]], ['center' => [0]], ['text' => []], ['place_name' => str_repeat('a', 1001)]] as $malo) {
            $respuesta = $this->lugares($malo);
            $this->getJson('/api/v1/mapa/buscar?texto=Tokio')->assertStatus(503);
        }
        $respuesta = 'no es JSON';
        $this->getJson('/api/v1/mapa/buscar?texto=Tokio')->assertStatus(503);
        $respuesta = $this->lugares(['bbox' => [180, 10, -180, 20]]);
        $this->getJson('/api/v1/mapa/buscar?texto=Tokio')->assertOk()->assertJsonPath('datos.0.limites', null);
    }

    public function test_proveedor_sin_configurar_no_hace_peticion_y_el_mapa_no_busca_al_abrirse(): void
    {
        $this->actingAs(User::factory()->create())->get('/mapa')->assertOk()->assertSee('id="buscar-lugar"', false)->assertSee('País, ciudad o lugar');
        Http::assertNothingSent();
        config(['mapas.token_publico' => null]);
        $this->getJson('/mapa/buscar?texto=Tokio')->assertStatus(503)->assertJsonPath('error.codigo', 'BUSCADOR_NO_DISPONIBLE');
        Http::assertNothingSent();
    }

    public function test_limita_consultas_por_cuenta(): void
    {
        $this->cliente();
        Http::fake(['api.mapbox.com/*' => Http::response($this->lugares())]);
        for ($i = 0; $i < 12; $i++) {
            $this->getJson('/api/v1/mapa/buscar?texto=Tokio')->assertOk();
        }
        $this->getJson('/api/v1/mapa/buscar?texto=Tokio')->assertStatus(429);
        Http::assertSentCount(12);
    }
}
