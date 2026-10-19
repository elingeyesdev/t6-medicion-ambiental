<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SaludTest extends TestCase
{
    public function test_salud_json_sin_sesion_y_fecha_utc_sin_promesas_de_integracion(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-15T20:30:00Z'));

        $this->get('/api/salud')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertCookieMissing(config('session.cookie'))
            ->assertExactJson([
                'servicio' => 'GeoCruz',
                'estado' => 'disponible',
                'alcance' => 'aplicacion',
                'fecha' => '2026-10-15T20:30:00+00:00',
            ]);

        Http::assertNothingSent();
    }

    public function test_salud_no_depende_de_la_base_de_datos_ni_de_proveedores(): void
    {
        config(['database.default' => 'no_configurada']);
        DB::shouldReceive('connection')->never();

        $this->getJson('/api/salud')->assertOk()->assertJsonPath('alcance', 'aplicacion');

        Http::assertNothingSent();
    }

    public function test_comprobacion_up_de_laravel_ejecuta_sus_diagnosticos(): void
    {
        Event::fake([DiagnosingHealth::class]);

        $this->get('/up')->assertOk();

        Event::assertDispatched(DiagnosingHealth::class);
        Http::assertNothingSent();
    }

    public function test_fallo_del_diagnostico_no_se_presenta_como_servicio_sano(): void
    {
        Event::listen(DiagnosingHealth::class, function (): void {
            throw new \RuntimeException('Fallo de diagnóstico controlado para la prueba.');
        });

        $this->get('/up')->assertStatus(500)
            ->assertDontSee('Fallo de diagnóstico controlado');
    }

    public function test_post_no_se_admite_en_la_comprobacion_de_lectura(): void
    {
        $this->postJson('/api/salud')->assertStatus(405);
    }
}
