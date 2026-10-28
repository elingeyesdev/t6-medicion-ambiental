<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Tiempo\ConsultarPronostico;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TiempoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-28T16:00:00Z'));
        Http::preventStrayRequests();
        Cache::flush();
    }

    private function respuesta(): array
    {
        $horas = [now()->timestamp, now()->addHour()->timestamp, now()->addHours(2)->timestamp];
        $c = ['time' => now()->timestamp];
        $h = ['time' => $horas];
        $unidades = [];
        foreach (ConsultarPronostico::VARIABLES as [$campo, $nombre, $unidad, $min, $max]) {
            $c[$campo] = $campo === 'pressure_msl' ? 1013 : 0;
            $h[$campo] = $campo === 'pressure_msl' ? [1013, 1013, 1013] : [0, null, 1];
            $unidades[$campo] = $unidad;
        }

        return ['timezone' => 'America/La_Paz', 'current' => $c, 'current_units' => $unidades, 'hourly' => $h, 'hourly_units' => $unidades,
            'daily' => ['time' => [Carbon::parse('2026-10-28T04:00:00Z')->timestamp], 'temperature_2m_max' => [30], 'temperature_2m_min' => [15],
                'precipitation_sum' => [0], 'sunrise' => [0], 'sunset' => [null]], 'daily_units' => ['temperature_2m_max' => '°C', 'temperature_2m_min' => '°C', 'precipitation_sum' => 'mm']];
    }

    private function cliente(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['cuenta']);
    }

    public function test_sesion_permiso_y_ubicacion_son_obligatorios_antes_de_salir_a_la_red(): void
    {
        $this->getJson('/api/v1/tiempo?latitud=0&longitud=0')->assertUnauthorized();
        $u = User::factory()->create();
        Sanctum::actingAs($u, ['otro']);
        $this->getJson('/api/v1/tiempo?latitud=0&longitud=0')->assertForbidden();
        $this->cliente();
        foreach (['latitud=91&longitud=0', 'latitud=0&longitud=181', 'latitud[]=0&longitud=0', 'latitud=NaN&longitud=0', 'latitud=0'] as $mala) {
            $this->getJson('/api/v1/tiempo?'.$mala)->assertUnprocessable();
        }
        Http::assertNothingSent();
    }

    public function test_consulta_global_preserva_cero_faltantes_unidades_origen_y_horas_utc(): void
    {
        $this->cliente();
        Http::fake(['api.open-meteo.com/*' => Http::response($this->respuesta())]);
        $this->getJson('/api/v1/tiempo?latitud=0&longitud=0')->assertOk()->assertJsonPath('origen', 'estimado')
            ->assertJsonPath('fuente', 'Open-Meteo')->assertJsonPath('actual.temperatura', 0)->assertJsonPath('actual.lluvia', 0)
            ->assertJsonPath('horas.1.temperatura', null)->assertJsonPath('horas.0.instante', '2026-10-28T16:00:00Z')
            ->assertJsonPath('dias.0.fecha', '2026-10-28')->assertJsonPath('dias.0.amanecer', null)->assertJsonPath('variables.nieve.unidad', 'cm')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertDatabaseCount('lecturas_ambientales', 0);
        $this->assertDatabaseCount('puntos_ambientales', 0);
        Http::assertSent(fn ($r) => str_starts_with($r->url(), 'https://api.open-meteo.com/v1/forecast?') &&
            $r['timeformat'] === 'unixtime' && $r['timezone'] === 'auto' && ! $r->hasHeader('Authorization') && ! isset($r['email']));
    }

    public function test_cache_solo_guarda_respuestas_validas_y_caduca_a_los_15_minutos(): void
    {
        $this->cliente();
        Http::fake(['api.open-meteo.com/*' => Http::response($this->respuesta())]);
        $url = '/api/v1/tiempo?latitud=-17.7833&longitud=-63.1833';
        $this->getJson($url)->assertOk();
        $this->getJson($url)->assertOk();
        Http::assertSentCount(1);
        $this->travel(16)->minutes();
        $this->getJson($url)->assertOk();
        Http::assertSentCount(2);
    }

    public function test_fallo_del_proveedor_no_inventa_pronostico_ni_se_conserva_en_cache(): void
    {
        $this->cliente();
        Http::fake(['api.open-meteo.com/*' => Http::sequence()->push(['reason' => 'privado'], 429)->push($this->respuesta())]);
        $url = '/api/v1/tiempo?latitud=-17.8&longitud=-63.2';
        $this->getJson($url)->assertStatus(503)->assertJsonPath('error.codigo', 'PRONOSTICO_NO_DISPONIBLE')->assertDontSee('privado');
        $this->getJson($url)->assertOk();
        Http::assertSentCount(2);
    }

    public function test_respuestas_malformadas_fechas_antiguas_unidades_y_vectores_incoherentes_se_rechazan(): void
    {
        $this->cliente();
        foreach (['unidad', 'antiguo', 'horas', 'valor', 'zona', 'repetida', 'json'] as $caso) {
            Cache::flush();
            $r = $this->respuesta();
            if ($caso === 'unidad') {
                $r['hourly_units']['temperature_2m'] = '°F';
            }
            if ($caso === 'antiguo') {
                $r['current']['time'] = now()->subDay()->timestamp;
            }
            if ($caso === 'horas') {
                $r['hourly']['temperature_2m'] = [0];
            }
            if ($caso === 'valor') {
                $r['hourly']['relative_humidity_2m'] = [0, 200, 10];
            }
            if ($caso === 'zona') {
                $r['timezone'] = '<script>';
            }
            if ($caso === 'repetida') {
                $r['hourly']['time'][1] = $r['hourly']['time'][0];
            }
            if ($caso === 'json') {
                $r = 'No es JSON';
            }
            Http::fake(['api.open-meteo.com/*' => Http::response($r)]);
            $this->getJson('/api/v1/tiempo?latitud=0&longitud=0')->assertStatus(503);
        }
    }

    public function test_el_mapa_no_consulta_al_proveedor_hasta_elegir_el_tiempo_de_un_lugar(): void
    {
        $this->actingAs(User::factory()->create())->get('/mapa')->assertOk()->assertSee('Tiempo de un lugar');
        Http::assertNothingSent();
        Http::fake(['api.open-meteo.com/*' => Http::response($this->respuesta())]);
        $this->getJson('/mapa/tiempo?latitud=-17.8&longitud=-63.2')->assertOk();
    }

    public function test_cuenta_inactiva_y_limite_de_consultas(): void
    {
        Sanctum::actingAs(User::factory()->create(['activo' => false]), ['cuenta']);
        $this->getJson('/api/v1/tiempo?latitud=0&longitud=0')->assertUnauthorized();
        $this->cliente();
        Http::fake(['api.open-meteo.com/*' => Http::response($this->respuesta())]);
        for ($i = 0; $i < 12; $i++) {
            $this->getJson('/api/v1/tiempo?latitud=0&longitud=0')->assertOk();
        }
        $this->getJson('/api/v1/tiempo?latitud=0&longitud=0')->assertStatus(429);
    }
}
