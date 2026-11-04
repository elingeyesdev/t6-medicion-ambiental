<?php

namespace Tests\Feature;

use App\Enums\RolCuenta;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccesoDenegadoWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_visualizador_recibe_un_403_en_espanol_con_salidas_accesibles(): void
    {
        $usuario = User::factory()->create(['rol' => RolCuenta::Visualizador]);

        $respuesta = $this->actingAs($usuario)->get('/usuarios');

        $respuesta->assertForbidden()
            ->assertHeader('Content-Type', 'text/html; charset=utf-8')
            ->assertSeeText('403')
            ->assertSeeText('Tu cuenta no tiene permiso para acceder a esta página.')
            ->assertDontSeeText('This action is unauthorized.');

        $documento = new DOMDocument;
        $documento->loadHTML($respuesta->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($documento);

        $this->assertSame('es', $xpath->evaluate('string(/html/@lang)'));
        $this->assertSame('Acceso denegado', trim($xpath->evaluate('string(//main//h1)')));
        $this->assertSame('Volver al mapa', trim($xpath->evaluate('string(//main//a[@href="'.route('mapa').'"])')));
        $this->assertSame('Volver al inicio', trim($xpath->evaluate('string(//main//a[@href="'.route('inicio').'"])')));

        $this->get(route('mapa'))->assertOk();
        $this->get(route('inicio'))->assertOk();
        $this->assertSame(RolCuenta::Visualizador, $usuario->fresh()->rol);
    }

    public function test_la_peticion_json_conserva_el_403_y_su_respuesta(): void
    {
        $usuario = User::factory()->create(['rol' => RolCuenta::Visualizador]);

        $this->actingAs($usuario)->getJson('/usuarios')
            ->assertForbidden()
            ->assertHeader('Content-Type', 'application/json')
            ->assertExactJson(['message' => 'This action is unauthorized.']);
    }
}
