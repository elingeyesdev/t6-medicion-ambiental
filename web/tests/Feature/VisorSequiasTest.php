<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VisorSequiasTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->make(['id' => 1]));
    }

    public function test_la_pantalla_no_descarga_lluvia_ni_publica_indices_al_abrirse(): void
    {
        Http::preventStrayRequests();
        config(['focos.clave' => 'secretoSoloBackend']);
        $this->travelTo(now('UTC')->setDate(2026, 10, 25));

        $respuesta = $this->get('/modulos/sequias?mesFinal=2024-09&municipio=BO070101');
        $respuesta->assertOk()->assertSee('aria-current="page"', false)
            ->assertSee('id="sq-resultados" hidden', false)
            ->assertSee('id="sq-detalle" hidden', false)
            ->assertSee('id="sq-lista"', false)->assertSee('id="sq-mapa"', false)
            ->assertSee('max="2026-09"', false)->assertSee('1991–2020')
            ->assertSee('no todo el municipio')->assertSee('no confirman daños')
            ->assertDontSee('Vigencia técnica')->assertDontSee('SHA-256')
            ->assertDontSee('secretoSoloBackend')->assertDontSee('-0.641048');

        preg_match('/<script id="sq-catalogos" type="application\/json">(.*?)<\/script>/s', $respuesta->getContent(), $partes);
        $catalogos = json_decode($partes[1], true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(56, $catalogos['muestras']);
        foreach ($catalogos['muestras'] as $muestra) {
            $this->assertNull($muestra['spi']);
            $this->assertNull($muestra['coordenadaCelda']);
        }
        Http::assertNothingSent();
    }

    public function test_la_navegacion_diferencia_sequias_por_muestras_y_modulos_pendientes(): void
    {
        $this->get('/')->assertOk()->assertSee('Abrir Sequías')->assertDontSee('en preparación');
        foreach (['inundaciones', 'deforestacion', 'calidad-aire'] as $id) {
            $this->get('/modulos/'.$id)->assertNotFound();
        }
    }
}
