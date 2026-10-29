<?php

namespace Tests\Feature;

use App\Models\PuntoAmbiental;
use App\Models\User;
use App\Services\Datos\RegistrarLecturas;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HistorialMapaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-28T20:00:00Z'));
        Http::preventStrayRequests();
        PuntoAmbiental::create(['codigo' => 'campus', 'nombre' => 'Campus', 'pais' => 'BO', 'latitud' => 0, 'longitud' => 0, 'tipo' => 'manual']);
    }

    private function lectura(int $hora, float $valor, string $origen = 'observado', string $fuente = 'manual'): void
    {
        app(RegistrarLecturas::class)->ejecutar([['punto' => 'campus', 'metrica' => 'temperatura', 'valor' => $valor,
            'instante' => sprintf('2026-10-28T%02d:00:00Z', $hora), 'origen' => $origen, 'fuente' => $fuente]]);
    }

    private function url(array $cambios = [], string $ruta = '/mapa/puntos'): string
    {
        return $ruta.'?'.http_build_query(array_replace(['desde' => '2026-10-28T06:00:00Z', 'hasta' => '2026-10-28T18:00:00Z', 'momento' => '2026-10-28T08:00:00Z'], $cambios));
    }

    public function test_el_momento_incluye_esa_lectura_y_no_utiliza_lecturas_posteriores(): void
    {
        $this->lectura(7, 5);
        $this->lectura(8, 0);
        $this->lectura(10, 20);
        $this->lectura(18, 30);
        $this->actingAs(User::factory()->create())->getJson($this->url())->assertOk()
            ->assertJsonPath('momento', '2026-10-28T08:00:00Z')->assertJsonPath('datos.0.lecturas.0.valor', 0)
            ->assertJsonPath('datos.0.lecturas.0.instante', '2026-10-28T08:00:00Z');
        $this->getJson($this->url(['momento' => '2026-10-28T10:00:00Z']))->assertOk()->assertJsonPath('datos.0.lecturas.0.valor', 20);
        $this->getJson('/mapa/puntos?desde=2026-10-28T06:00:00Z&hasta=2026-10-28T18:00:00Z')->assertOk()
            ->assertJsonPath('momento', null)->assertJsonPath('datos.0.lecturas.0.valor', 20);
        Http::assertNothingSent();
    }

    public function test_sin_lecturas_anteriores_el_punto_se_muestra_sin_datos(): void
    {
        $this->lectura(9, 10);
        $this->actingAs(User::factory()->create())->getJson($this->url())->assertOk()->assertJsonPath('datos.0.lecturas', []);
        $this->getJson($this->url(['momento' => '2026-10-28T09:00:00Z']))->assertOk()->assertJsonPath('datos.0.lecturas.0.valor', 10);
    }

    public function test_conserva_cada_fuente_y_origen_y_excluye_simulados_hasta_elegirlos(): void
    {
        $this->lectura(7, 1);
        $this->lectura(8, 0);
        $this->lectura(9, 10);
        $this->lectura(8, 15, 'estimado', 'modelo-a');
        $this->lectura(8, 20, 'estimado', 'modelo-b');
        $this->lectura(8, 40, 'simulado');
        $r = $this->actingAs(User::factory()->create())->getJson($this->url())->assertOk()->assertJsonCount(3, 'datos.0.lecturas');
        $porFuente = collect($r->json('datos.0.lecturas'))->keyBy(fn ($l) => $l['origen'].'/'.$l['fuente']);
        $this->assertEquals(0, $porFuente['observado/manual']['valor']);
        $this->assertEquals(15, $porFuente['estimado/modelo-a']['valor']);
        $this->assertEquals(20, $porFuente['estimado/modelo-b']['valor']);
        $this->getJson($this->url(['origen' => 'simulado']))->assertOk()->assertJsonCount(1, 'datos.0.lecturas')->assertJsonPath('datos.0.lecturas.0.valor', 40);
        $this->assertDatabaseCount('lecturas_ambientales', 6);
    }

    public function test_rechaza_momentos_fuera_del_periodo_fechas_sin_zona_y_futuro(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (['2026-10-28T05:59:59Z', '2026-10-28T18:00:00Z', '2026-10-29T08:00:00Z', '2026-10-28T08:00:00', '2026-02-30T08:00:00Z'] as $momento) {
            $this->getJson($this->url(['momento' => $momento]))->assertUnprocessable()->assertJsonPath('error.codigo', 'FILTROS_INVALIDOS');
        }
        $this->getJson($this->url(['hasta' => '2026-10-29T18:00:00Z']))->assertUnprocessable();
        $this->getJson($this->url().'&momento=2026-10-28T07:00:00Z')->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_la_api_de_flutter_compartida_exige_cuenta_activa_y_capacidad(): void
    {
        $url = $this->url([], '/api/v1/mapa/puntos');
        $this->getJson($url)->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create(), ['otro']);
        $this->getJson($url)->assertForbidden();
        Sanctum::actingAs(User::factory()->create(['activo' => false]), ['cuenta']);
        $this->getJson($url)->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create(), ['cuenta']);
        $this->getJson($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertJsonPath('momento', '2026-10-28T08:00:00Z');
    }

    public function test_la_ubicacion_historica_no_se_reescribe_con_la_actual(): void
    {
        $this->lectura(8, 0);
        PuntoAmbiental::first()->update(['latitud' => -17, 'longitud' => -63]);
        $this->lectura(10, 20);
        $this->actingAs(User::factory()->create())->getJson($this->url())->assertOk()
            ->assertJsonPath('datos.0.latitud', -17)->assertJsonPath('datos.0.lecturas.0.latitud', 0)->assertJsonPath('datos.0.lecturas.0.longitud', 0);
    }
}
