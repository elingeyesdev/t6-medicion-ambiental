<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BaseWebTest extends TestCase
{
    public function test_el_panel_publica_solo_consultas_disponibles_sin_datos_inventados(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('lang="es"', false)
            ->assertSee('Panel de monitoreo')
            ->assertSee('EnviroSense')
            ->assertSee('vendor/tabler/tabler.min.css')
            ->assertSee('css/panel/panel.css')
            ->assertDontSee('Próximamente')
            ->assertDontSee('en preparación')
            ->assertDontSee('45 focos')
            ->assertDontSee('api_key');

        foreach (config('geocruz.modulos') as $id => $modulo) {
            if ($modulo['habilitado']) {
                $response->assertSee($modulo['nombre'])->assertSee(route('modulos.mostrar', $id), false);
            } else {
                $response->assertDontSee($modulo['nombre'])->assertDontSee(route('modulos.mostrar', $id), false);
            }
        }

        Http::assertNothingSent();
    }

    #[DataProvider('modulos')]
    public function test_las_pantallas_pendientes_tampoco_se_publican_por_url_directa(string $id, string $nombre): void
    {
        $this->get('/modulos/'.$id)
            ->assertNotFound()
            ->assertSee('Página no encontrada')
            ->assertDontSee('en preparación')
            ->assertSee(route('inicio'), false);

        Http::assertNothingSent();
    }

    public static function modulos(): array
    {
        return [
            ['inundaciones', 'Inundaciones'],
            ['deforestacion', 'Deforestación'],
            ['calidad-aire', 'Calidad del aire'],
        ];
    }

    public function test_modulo_desconocido_no_genera_una_pantalla_valida(): void
    {
        $this->get('/modulos/no-existe')->assertNotFound();
    }

    public function test_no_se_publican_las_operaciones_ambientales_pendientes(): void
    {
        $this->get('/api/v1/inundaciones')->assertStatus(503)->assertJsonPath('error.codigo', 'MODULO_NO_DISPONIBLE');
    }

    public function test_los_componentes_comparten_menu_accesible_sin_consultar_proveedores(): void
    {
        foreach (['/', '/modulos/focos-calor', '/modulos/sequias'] as $ruta) {
            $this->get($ruta)->assertOk()
                ->assertSee('aria-label="Navegación principal"', false)
                ->assertSee('aria-controls="menu-panel"', false)
                ->assertSee('href="#contenido"', false)
                ->assertDontSee('Próximamente');
        }
        Http::assertNothingSent();
    }
}
