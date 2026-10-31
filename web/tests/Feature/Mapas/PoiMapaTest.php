<?php

namespace Tests\Feature\Mapas;

use App\Models\User;
use App\Services\Mapas\Capas\ErroresConsultaMapa;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PoiMapaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::shouldReceive('connection')->never();
        config(['capas.poi_habilitado' => true, 'capas.poi_token' => 'pk.transporte-controlado']);
        $this->app->make(ExceptionHandler::class)->renderable(new ErroresConsultaMapa);
        Route::middleware('web')->group(base_path('../docs/integracion62/rutas-web.php'));
        Route::getRoutes()->refreshNameLookups();
        $usuario = new User;
        $usuario->forceFill(['id' => 6201, 'sesion_version' => 1]);
        $this->actingAs($usuario);
    }

    private function respuesta(): array
    {
        return ['type' => 'FeatureCollection', 'features' => [[
            'type' => 'Feature', 'geometry' => ['type' => 'Point', 'coordinates' => [-3.7, 40.4]],
            'properties' => ['feature_type' => 'poi', 'name' => 'Establecimiento de prueba', 'full_address' => 'Dirección de prueba',
                'context' => ['country' => ['country_code' => 'ES']]],
        ]]];
    }

    public function test_consulta_search_box_con_cobertura_explicita_sin_datos_de_cuenta(): void
    {
        Http::fake(['api.mapbox.com/*' => Http::response($this->respuesta())]);
        $this->getJson('/mapa/poi?texto=museo&pais=es&url=https://otro.invalid&email=privado&types=address')
            ->assertOk()->assertJsonPath('fuente', 'Mapbox Search Box')->assertJsonPath('datos.0.latitud', 40.4)
            ->assertHeader('Cache-Control', 'no-store, private')->assertDontSee('pk.transporte-controlado');
        Http::assertSent(fn ($r) => str_starts_with($r->url(), 'https://api.mapbox.com/search/searchbox/v1/forward?') &&
            $r['q'] === 'museo' && $r['country'] === 'es' && $r['types'] === 'poi' && $r['limit'] === 6 && $r['language'] === 'es' &&
            ! isset($r['email']) && ! isset($r['url']) && ! $r->hasHeader('Authorization'));
    }

    public function test_no_guarda_resultados_temporales_y_distingue_busqueda_vacia_de_fallo(): void
    {
        Http::fake(['api.mapbox.com/*' => Http::sequence()->push($this->respuesta())->push(['type' => 'FeatureCollection', 'features' => []])->push(['message' => 'pk.transporte-controlado'], 403)]);
        $this->getJson('/mapa/poi?texto=museo&pais=es')->assertOk();
        $this->getJson('/mapa/poi?texto=museo&pais=es')->assertOk()->assertJsonPath('datos', []);
        $this->getJson('/mapa/poi?texto=museo&pais=es')->assertStatus(503)->assertJsonPath('error.codigo', 'POI_NO_DISPONIBLE')->assertDontSee('pk.transporte-controlado');
        Http::assertSentCount(3);
    }

    public function test_pais_sin_cobertura_y_textos_invalidos_no_salen_a_la_red(): void
    {
        foreach (['pais=bo&texto=museo', 'pais=es&texto=a', 'pais=es&texto[]=museo', 'pais[]=es&texto=museo', 'pais=es&texto=museo%3Bcomercio'] as $query) {
            $this->getJson('/mapa/poi?'.$query)->assertUnprocessable()->assertJsonPath('error.codigo', 'FILTROS_INVALIDOS');
        }
        Http::assertNothingSent();
    }

    public function test_proveedor_desactivado_o_sin_clave_no_afirma_una_conexion(): void
    {
        config(['capas.poi_habilitado' => false]);
        $this->getJson('/mapa/poi?texto=museo&pais=es')->assertStatus(503);
        config(['capas.poi_habilitado' => true, 'capas.poi_token' => null]);
        $this->getJson('/mapa/poi?texto=museo&pais=es')->assertStatus(503);
        Http::assertNothingSent();
    }

    public function test_rechaza_respuestas_malformadas_coordenadas_ausentes_y_otro_pais(): void
    {
        $respuesta = null;
        Http::fake(fn () => Http::response($respuesta));
        $mala = $this->respuesta();
        foreach ([['geometry', ['type' => 'Point', 'coordinates' => [null, 40]]], ['geometry', ['type' => 'Point', 'coordinates' => [0, 91]]],
            ['properties', ['feature_type' => 'address']], ['properties', ['feature_type' => 'poi', 'name' => 'x', 'context' => ['country' => ['country_code' => 'BO']]]]] as [$clave, $valor]) {
            $respuesta = $mala;
            $respuesta['features'][0][$clave] = $valor;
            $this->getJson('/mapa/poi?texto=museo&pais=es')->assertStatus(503);
        }
        $respuesta = 'no JSON';
        $this->getJson('/mapa/poi?texto=museo&pais=es')->assertStatus(503);
    }

    public function test_cero_no_se_reemplaza_por_otras_coordenadas(): void
    {
        $respuesta = $this->respuesta();
        $respuesta['features'][0]['geometry']['coordinates'] = [0, 0];
        Http::fake(['api.mapbox.com/*' => Http::response($respuesta)]);
        $this->getJson('/mapa/poi?texto=museo&pais=es')->assertOk()->assertJsonPath('datos.0.latitud', 0)->assertJsonPath('datos.0.longitud', 0);
    }

    public function test_fallo_de_transporte_y_redireccion_no_filtran_detalles(): void
    {
        Http::fake(['api.mapbox.com/*' => Http::sequence()->pushStatus(302, ['Location' => 'https://otro.invalid'])->pushFailedConnection()]);
        for ($i = 0; $i < 2; $i++) {
            $this->getJson('/mapa/poi?texto=museo&pais=es')->assertStatus(503)->assertDontSee('otro.invalid')->assertDontSee('pk.transporte-controlado');
        }
        Http::assertSentCount(2);
    }

    public function test_limita_busquedas_por_cuenta_antes_de_consultar_proveedor(): void
    {
        Http::fake(['api.mapbox.com/*' => Http::response(['type' => 'FeatureCollection', 'features' => []])]);
        for ($i = 0; $i < 12; $i++) {
            $this->getJson('/mapa/poi?texto=museo&pais=es')->assertOk();
        }
        $this->getJson('/mapa/poi?texto=museo&pais=es')->assertStatus(429)->assertJsonPath('error.codigo', 'SOLICITUDES_EXCESIVAS');
        Http::assertSentCount(12);
    }
}
