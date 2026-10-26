<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VisorFocosTest extends TestCase
{
    public function test_visores_html_no_consultan_nasa_y_ofrecen_el_recorrido_accesible(): void
    {
        config(['focos.clave' => 'secretoLocalSoloParaProbar']);
        $this->get('/modulos/focos-calor')->assertOk()
            ->assertSee('id="filtros-focos"', false)->assertSee('id="lista-focos"', false)
            ->assertSee('id="detalle-foco"', false)->assertSee('id="mapa-focos"', false)
            ->assertSee('aria-live="polite"', false)->assertSee('id="resultados-focos" hidden', false)
            ->assertSee('no confirma un incendio')->assertSee('js/focos-calor/inicio.js')
            ->assertDontSee('secretoLocalSoloParaProbar')->assertDontSee('45 focos')
            ->assertDontSee('Este módulo está en preparación');
        Http::assertNothingSent();
    }

    public function test_el_inicio_diferencia_el_modulo_disponible_de_los_pendientes(): void
    {
        $this->get('/')->assertOk()->assertSee('Abrir Focos de calor')->assertDontSee('en preparación');
        $this->get('/modulos/sequias')->assertOk()->assertSee('id="sq-formulario"', false)->assertDontSee('Este módulo está en preparación');
        Http::assertNothingSent();
    }

    public function test_leaflet_publico_conserva_integridad_y_licencia_para_otra_instalacion(): void
    {
        $this->assertFileExists(public_path('vendor/leaflet/LICENSE'));
        $this->assertSame('20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=', base64_encode(hash_file('sha256', public_path('vendor/leaflet/leaflet.js'), true)));
        $this->assertSame('p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=', base64_encode(hash_file('sha256', public_path('vendor/leaflet/leaflet.css'), true)));
    }
}
