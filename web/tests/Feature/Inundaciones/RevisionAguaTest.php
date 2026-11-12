<?php

namespace Tests\Feature\Inundaciones;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RevisionAguaTest extends TestCase
{
    use RefreshDatabase;

    public function test_revision_no_aparece_en_el_mapa_normal(): void
    {
        $this->actingAs(User::factory()->make(['id' => 1]));
        $this->get('/mapa')->assertOk()->assertDontSee('id="revision-agua"', false)->assertDontSee('Datos de prueba');
        $this->get('/revision/agua')->assertNotFound();
        config(['inundaciones.revision_local' => true]);
        $this->get('/mapa')->assertOk()->assertDontSee('id="revision-agua"', false);
    }

    public function test_revision_local_conserva_mapa_y_muestra_aviso_permanente(): void
    {
        config(['inundaciones.revision_local' => true]);
        $this->actingAs(User::factory()->make(['id' => 1]));
        $this->get('/revision/agua')->assertOk()->assertSee('id="mapa-monitoreo"', false)
            ->assertSee('id="revision-agua"', false)->assertSee('Datos de prueba')
            ->assertSee('Cerrar agua y cambios')->assertSee('Sin comparación')->assertSee('prueba_controlada');
        Http::assertNothingSent();
    }

    public function test_en_produccion_no_se_puede_habilitar_la_revision(): void
    {
        config(['inundaciones.revision_local' => true]);
        $this->app->instance('env', 'production');
        $this->actingAs(User::factory()->make(['id' => 1]));
        $this->get('/revision/agua')->assertNotFound();
    }

    public function test_revision_exige_una_cuenta_activa(): void
    {
        config(['inundaciones.revision_local' => true]);
        $this->get('/revision/agua')->assertRedirect('/login');
        $this->actingAs(User::factory()->make(['id' => 2, 'activo' => false]));
        $this->get('/revision/agua')->assertRedirect('/login');
    }
}
