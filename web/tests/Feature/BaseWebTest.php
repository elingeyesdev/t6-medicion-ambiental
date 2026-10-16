<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BaseWebTest extends TestCase
{
    public function test_inicio_y_enlaces_de_los_cinco_modulos_sin_datos_inventados(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('lang="es"', false)
            ->assertSee('Estamos preparando las consultas')
            ->assertSee('css/geocruz.css')
            ->assertDontSee('45 focos')
            ->assertDontSee('api_key');

        foreach (config('geocruz.modulos') as $id => $modulo) {
            $response->assertSee($modulo['nombre'])
                ->assertSee(route('modulos.mostrar', $id), false);
        }

        Http::assertNothingSent();
    }

    #[DataProvider('modulos')]
    public function test_modulo_navegable_informa_que_la_consulta_esta_pendiente(string $id, string $nombre): void
    {
        $this->get('/modulos/'.$id)
            ->assertOk()
            ->assertSee($nombre)
            ->assertSee('Este módulo está en preparación')
            ->assertSee('no representa una consulta sin resultados')
            ->assertSee('aria-current="page"', false)
            ->assertSee(route('inicio'), false);

        Http::assertNothingSent();
    }

    public static function modulos(): array
    {
        return [
            ['focos-calor', 'Focos de calor'],
            ['sequias', 'Sequías'],
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
        $this->get('/api/v1/sequias')->assertStatus(503)->assertJsonPath('error.codigo', 'MODULO_NO_DISPONIBLE');
    }
}
