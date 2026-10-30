<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Aire\ConsultarCalidadAire;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CalidadAireTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-29T16:30:00Z'));
        Http::preventStrayRequests();
        Cache::flush();
    }

    private function respuesta(float $latitud = 0, float $longitud = 0): array
    {
        $hora = now()->startOfHour()->timestamp;
        $unidades = ['time' => 'unixtime', 'interval' => 'seconds'];
        $actual = ['time' => $hora, 'interval' => 3600];
        $serie = ['time' => range($hora - 24 * 3600, $hora, 3600)];
        foreach (ConsultarCalidadAire::VARIABLES as [$campo, $nombre, $unidad]) {
            $unidades[$campo] = $unidad;
            $actual[$campo] = 0;
            $serie[$campo] = array_fill(0, 25, 0);
            $serie[$campo][2] = null;
        }

        return ['latitude' => $latitud, 'longitude' => $longitud, 'utc_offset_seconds' => 0,
            'current_units' => $unidades, 'hourly_units' => $unidades, 'current' => $actual, 'hourly' => $serie];
    }

    private function cliente(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['cuenta']);
    }

    public function test_sesion_cuenta_activa_permiso_y_coordenadas_se_revisan_antes_de_consultar(): void
    {
        $url = '/api/v1/aire?latitud=0&longitud=0';
        $this->getJson($url)->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create(), ['otro']);
        $this->getJson($url)->assertForbidden();
        Sanctum::actingAs(User::factory()->create(['activo' => false]), ['cuenta']);
        $this->getJson($url)->assertUnauthorized();
        $this->cliente();
        foreach (['latitud=91&longitud=0', 'latitud=0&longitud=-181', 'latitud=NaN&longitud=0', 'latitud[]=0&longitud=0', 'latitud=0'] as $filtro) {
            $this->getJson('/api/v1/aire?'.$filtro)->assertUnprocessable();
        }
        Http::assertNothingSent();
    }

    public function test_indice_europeo_preserva_cero_faltantes_y_procedencia_sin_guardar_lecturas(): void
    {
        $this->cliente();
        $opciones = [];
        Http::fake(function ($r, $configuracion) use (&$opciones) {
            $opciones = $configuracion;

            return Http::response($this->respuesta());
        });
        $r = $this->getJson('/api/v1/aire?latitud=0&longitud=0')->assertOk()
            ->assertJsonPath('escala', 'european_aqi')->assertJsonPath('origen', 'estimado')
            ->assertJsonPath('actual.indice', 0)->assertJsonPath('actual.pm25', 0)
            ->assertJsonPath('horas.1.indice', null)->assertJsonPath('variables.indice.unidad', 'EAQI')
            ->assertJsonPath('variables.pm25.unidad', 'μg/m³')->assertJsonPath('celda.latitud', 0)
            ->assertJsonCount(24, 'horas')->assertHeader('Cache-Control', 'no-store, private');
        foreach ($r->json('horas') as $fila) {
            $this->assertLessThanOrEqual(now()->timestamp, strtotime($fila['instante']));
            $this->assertGreaterThanOrEqual(now()->subDay()->timestamp, strtotime($fila['instante']));
        }
        Http::assertSent(fn ($r) => str_starts_with($r->url(), 'https://air-quality-api.open-meteo.com/v1/air-quality?') &&
            $r['domains'] === 'cams_global' && $r['timeformat'] === 'unixtime' && $r['timezone'] === 'UTC' &&
            $r['past_hours'] === 24 && ! str_contains($r['current'], 'us_aqi') && ! $r->hasHeader('Authorization'));
        $this->assertFalse($opciones['allow_redirects']);
        $this->assertSame(12, $opciones['timeout']);
        $this->assertDatabaseCount('lecturas_ambientales', 0);
        $this->assertDatabaseCount('puntos_ambientales', 0);
        $this->assertDatabaseCount('alertas_ambientales', 0);
    }

    public function test_los_pronosticos_horarios_futuros_no_salen_como_historial(): void
    {
        $this->cliente();
        $r = $this->respuesta();
        $r['hourly']['time'][] = now()->addHour()->startOfHour()->timestamp;
        foreach (ConsultarCalidadAire::VARIABLES as [$campo]) {
            $r['hourly'][$campo][] = 999;
        }
        Http::fake(['air-quality-api.open-meteo.com/*' => Http::response($r)]);
        $this->getJson('/api/v1/aire?latitud=0&longitud=0')->assertOk()->assertJsonCount(24, 'horas')->assertDontSee('999');
    }

    public function test_solo_una_respuesta_valida_se_guarda_temporalmente_y_se_separa_por_lugar(): void
    {
        $this->cliente();
        Http::fake(fn ($r) => Http::response($this->respuesta((float) $r['latitude'], (float) $r['longitude'])));
        $this->getJson('/api/v1/aire?latitud=0&longitud=0')->assertOk();
        $this->getJson('/api/v1/aire?latitud=0&longitud=0')->assertOk();
        Http::assertSentCount(1);
        $this->getJson('/api/v1/aire?latitud=-17.8&longitud=-63.2')->assertOk();
        Http::assertSentCount(2);
        $this->travel(16)->minutes();
        $this->getJson('/api/v1/aire?latitud=0&longitud=0')->assertOk();
        Http::assertSentCount(3);
    }

    public function test_errores_y_redirecciones_no_se_publican_no_revelan_detalles_y_permiten_reintentar(): void
    {
        $this->cliente();
        Http::fake(['air-quality-api.open-meteo.com/*' => Http::sequence()->push(['reason' => 'detalle privado'], 429)
            ->push('', 302, ['Location' => 'https://otro.ejemplo/privado'])->push($this->respuesta())]);
        $url = '/api/v1/aire?latitud=0&longitud=0';
        for ($i = 0; $i < 2; $i++) {
            $this->getJson($url)->assertStatus(503)->assertJsonPath('error.codigo', 'CALIDAD_AIRE_NO_DISPONIBLE')->assertDontSee('privado');
        }
        $this->getJson($url)->assertOk();
        Http::assertSentCount(3);
    }

    public function test_series_unidades_ubicaciones_y_fechas_invalidas_se_rechazan_completas(): void
    {
        $this->cliente();
        $r = null;
        Http::fake(function () use (&$r) {
            return Http::response($r);
        });
        $casos = [
            fn (&$r) => $r['current_units']['european_aqi'] = 'USAQI',
            fn (&$r) => $r['hourly_units']['pm2_5'] = 'mg/m³',
            fn (&$r) => $r['current']['time'] = now()->subHours(3)->startOfHour()->timestamp,
            fn (&$r) => $r['current']['time'] = now()->addHour()->startOfHour()->timestamp,
            fn (&$r) => $r['current']['interval'] = 900,
            fn (&$r) => $r['latitude'] = 15,
            fn (&$r) => $r['utc_offset_seconds'] = -14400,
            fn (&$r) => $r['hourly']['pm2_5'] = [0],
            fn (&$r) => $r['hourly']['time'][1] = $r['hourly']['time'][0],
            fn (&$r) => $r['hourly']['european_aqi'][3] = -1,
            fn (&$r) => $r['current']['pm10'] = '3.2',
            fn (&$r) => $r['current']['pm10'] = true,
            function (&$r) {
                unset($r['hourly']['ozone']);
            },
        ];
        foreach ($casos as $i => $cambiar) {
            Cache::flush();
            $r = $this->respuesta();
            $cambiar($r);
            $this->getJson('/api/v1/aire?latitud=0&longitud=0')->assertStatus(503);
        }
        $this->assertDatabaseCount('lecturas_ambientales', 0);
    }

    public function test_null_se_conserva_pero_sin_ninguna_variable_actual_no_se_ofrece_una_consulta_vacia(): void
    {
        $this->cliente();
        $r = $this->respuesta();
        $r['current']['european_aqi'] = null;
        Http::fake(function () use (&$r) {
            return Http::response($r);
        });
        $this->getJson('/api/v1/aire?latitud=0&longitud=0')->assertOk()->assertJsonPath('actual.indice', null);
        Cache::flush();
        foreach (ConsultarCalidadAire::VARIABLES as [$campo]) {
            $r['current'][$campo] = null;
        }
        $this->getJson('/api/v1/aire?latitud=0&longitud=0')->assertStatus(503);
    }

    public function test_json_invalido_respuesta_grande_y_error_de_conexion_no_se_convierten_en_datos(): void
    {
        $this->cliente();
        Http::fake(['air-quality-api.open-meteo.com/*' => Http::sequence()->push('no es JSON')->push(str_repeat('x', 524289))->pushFailedConnection()]);
        for ($i = 0; $i < 3; $i++) {
            $this->getJson('/api/v1/aire?latitud=0&longitud=0')->assertStatus(503);
        }
    }

    public function test_la_web_no_consulta_al_abrirse_y_tiene_el_mismo_contrato_que_la_api(): void
    {
        $this->actingAs(User::factory()->create())->get('/mapa')->assertOk()
            ->assertViewHas('configuracionMonitoreo', fn ($datos) => $datos['aire'] === route('mapa.aire'));
        Http::assertNothingSent();
        Http::fake(['air-quality-api.open-meteo.com/*' => Http::response($this->respuesta())]);
        $this->getJson('/mapa/aire?latitud=0&longitud=0')->assertOk()->assertJsonPath('escala', 'european_aqi');
        $this->getJson('/mapa/aire?latitud=91&longitud=0')->assertUnprocessable()->assertJsonPath('error.codigo', 'FILTROS_INVALIDOS');
    }

    public function test_limite_de_frecuencia_por_cuenta_y_fallo_web_son_controlados(): void
    {
        $this->actingAs(User::factory()->create());
        Http::fake(['air-quality-api.open-meteo.com/*' => Http::response(['reason' => 'detalle privado'], 503)]);
        $url = '/mapa/aire?latitud=0&longitud=0';
        for ($i = 0; $i < 12; $i++) {
            $this->getJson($url)->assertStatus(503)->assertJsonPath('error.codigo', 'CALIDAD_AIRE_NO_DISPONIBLE')->assertDontSee('privado');
        }
        $this->getJson($url)->assertStatus(429);
        Http::assertSentCount(12);
    }
}
