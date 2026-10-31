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

    private function respuestaConAyer(string $zona = 'America/La_Paz'): array
    {
        $r = $this->respuesta();
        $local = now()->setTimezone($zona);
        $inicio = Carbon::parse($local->format('Y-m-d').'T00:00:00Z')->subSeconds($local->offset)->subDay();
        $r['timezone'] = $zona;
        $r['utc_offset_seconds'] = $local->offset;
        $r['hourly']['time'] = array_map(fn ($i) => $inicio->copy()->addHours($i)->timestamp, range(0, 191));
        foreach (ConsultarPronostico::VARIABLES as [$campo]) {
            $r['hourly'][$campo] = array_fill(0, 192, $campo === 'pressure_msl' ? 1013 : 0);
        }
        $r['hourly']['temperature_2m'][18] = null;
        $r['daily'] = ['time' => array_map(fn ($i) => $inicio->copy()->addDays($i)->timestamp, range(0, 7)),
            'temperature_2m_max' => array_fill(0, 8, 30), 'temperature_2m_min' => array_fill(0, 8, 15),
            'precipitation_sum' => array_fill(0, 8, 0), 'sunrise' => array_fill(0, 8, 0), 'sunset' => array_fill(0, 8, null)];

        return $r;
    }

    public function test_recupera_24_horas_recientes_sin_anteponer_ayer_al_pronostico(): void
    {
        $this->travelTo(Carbon::parse('2026-10-28T16:15:00Z'));
        $this->cliente();
        Http::fake(['api.open-meteo.com/*' => Http::response($this->respuestaConAyer())]);
        $r = $this->getJson('/api/v1/tiempo?latitud=-17.8&longitud=-63.2')->assertOk()
            ->assertJsonCount(24, 'historial')->assertJsonCount(168, 'horas')->assertJsonCount(7, 'dias')
            ->assertJsonPath('historial.0.instante', '2026-10-27T17:00:00Z')
            ->assertJsonPath('historial.23.instante', '2026-10-28T16:00:00Z')
            ->assertJsonPath('historial.5.temperatura', null)->assertJsonPath('historial.0.lluvia', 0)
            ->assertJsonPath('horas.0.instante', '2026-10-28T04:00:00Z')
            ->assertJsonPath('dias.0.fecha', '2026-10-28')->assertJsonPath('dias.6.fecha', '2026-11-03')
            ->assertJsonPath('origen', 'estimado')->assertJsonPath('fuente', 'Open-Meteo');
        foreach ($r->json('historial') as $fila) {
            $t = Carbon::parse($fila['instante']);
            $this->assertGreaterThan(now()->subDay()->timestamp, $t->timestamp);
            $this->assertLessThanOrEqual(now()->timestamp, $t->timestamp);
        }
        Http::assertSent(fn ($r) => $r['past_days'] === 1 && $r['forecast_days'] === 7 && $r['timeformat'] === 'unixtime');
        Http::assertSentCount(1);
        $this->assertDatabaseCount('lecturas_ambientales', 0);
        $this->assertDatabaseCount('puntos_ambientales', 0);
    }

    public function test_respeta_24_horas_reales_y_siete_fechas_en_cambios_de_horario_y_offsets_fraccionarios(): void
    {
        $this->cliente();
        $r = [];
        Http::fake(function () use (&$r) {
            return Http::response($r);
        });
        foreach ([['2026-10-31T16:15:00Z', 'America/New_York'], ['2026-11-01T17:15:00Z', 'America/New_York'],
            ['2026-10-31T16:15:00Z', 'Asia/Kathmandu']] as [$instante, $zona]) {
            $this->travelTo(Carbon::parse($instante));
            Cache::flush();
            $r = $this->respuestaConAyer($zona);
            $respuesta = $this->getJson('/api/v1/tiempo?latitud=0&longitud=0')->assertOk()
                ->assertJsonCount(24, 'historial')->assertJsonCount(168, 'horas')->assertJsonCount(7, 'dias')
                ->assertJsonPath('zonaHoraria', $zona);
            $hoy = Carbon::parse(now()->setTimezone($zona)->format('Y-m-d').'T00:00:00Z');
            $this->assertSame(array_map(fn ($i) => $hoy->copy()->addDays($i)->format('Y-m-d'), range(0, 6)), array_column($respuesta->json('dias'), 'fecha'));
            $historial = $respuesta->json('historial');
            $this->assertSame(23 * 3600, Carbon::parse($historial[23]['instante'])->timestamp - Carbon::parse($historial[0]['instante'])->timestamp);
            if ($instante === '2026-11-01T17:15:00Z') {
                $horasLocales = array_map(fn ($f) => Carbon::parse($f['instante'])->setTimezone($zona)->format('Y-m-d H:i P'), $historial);
                $this->assertContains('2026-11-01 01:00 -04:00', $horasLocales);
                $this->assertContains('2026-11-01 01:00 -05:00', $horasLocales);
            }
        }
    }

    public function test_current_de_ayer_al_cruzar_medianoche_no_agrega_un_octavo_dia_al_pronostico(): void
    {
        $this->travelTo(Carbon::parse('2026-10-29T04:05:00Z'));
        $this->cliente();
        $r = $this->respuestaConAyer();
        $r['current']['time'] = Carbon::parse('2026-10-29T03:45:00Z')->timestamp;
        Http::fake(['api.open-meteo.com/*' => Http::response($r)]);
        $this->getJson('/api/v1/tiempo?latitud=-17.8&longitud=-63.2')->assertOk()
            ->assertJsonCount(24, 'historial')->assertJsonCount(168, 'horas')->assertJsonCount(7, 'dias')
            ->assertJsonPath('actual.instante', '2026-10-29T03:45:00Z')
            ->assertJsonPath('historial.23.instante', '2026-10-29T03:00:00Z')
            ->assertJsonPath('horas.0.instante', '2026-10-29T04:00:00Z')
            ->assertJsonPath('dias.0.fecha', '2026-10-29')->assertJsonPath('dias.6.fecha', '2026-11-04');
    }

    public function test_historial_puede_ser_parcial_o_vacio_sin_rellenarlo_con_ceros_ni_con_futuro(): void
    {
        $this->cliente();
        $r = [];
        Http::fake(function () use (&$r) {
            return Http::response($r);
        });
        foreach ([0, 4] as $cantidad) {
            Cache::flush();
            $r = $this->respuestaConAyer();
            $indices = array_keys(array_filter($r['hourly']['time'], fn ($t) => $cantidad === 0 ? $t > now()->timestamp : $t > now()->subHours($cantidad)->timestamp));
            foreach ($r['hourly'] as $campo => $valores) {
                $r['hourly'][$campo] = array_map(fn ($i) => $valores[$i], $indices);
            }
            $this->getJson('/api/v1/tiempo?latitud=0&longitud=0')->assertOk()->assertJsonCount($cantidad, 'historial');
        }
    }

    public function test_instante_actual_futuro_o_desfase_incoherente_no_se_presentan_como_tiempo_pasado(): void
    {
        $this->cliente();
        $r = [];
        Http::fake(function () use (&$r) {
            return Http::response($r);
        });
        foreach (['futuro', 'offset', 'horas', 'dias'] as $caso) {
            Cache::flush();
            $r = $this->respuestaConAyer();
            if ($caso === 'futuro') {
                $r['current']['time'] = now()->addHour()->timestamp;
            } elseif ($caso === 'offset') {
                $r['utc_offset_seconds'] = 3600;
            } elseif ($caso === 'horas') {
                $r['hourly']['time'] = array_map(fn ($i) => now()->subHours(36)->addMinutes($i * 30)->timestamp, range(0, 191));
            } else {
                $r['daily']['time'][] = now()->addDays(8)->timestamp;
            }
            $this->getJson('/api/v1/tiempo?latitud=0&longitud=0')->assertStatus(503)->assertJsonPath('error.codigo', 'PRONOSTICO_NO_DISPONIBLE');
        }
    }

    public function test_cache_anterior_sin_historial_no_oculta_la_recuperacion_y_web_usa_el_mismo_contrato(): void
    {
        $this->actingAs(User::factory()->create());
        Cache::put('tiempo-envirosense-v1:'.hash('sha256', json_encode([-17.8, -63.2])), ['anterior' => true], 900);
        Http::fake(['api.open-meteo.com/*' => Http::response($this->respuestaConAyer())]);
        $url = '/mapa/tiempo?latitud=-17.8&longitud=-63.2';
        $this->getJson($url)->assertOk()->assertJsonCount(24, 'historial')->assertJsonMissingPath('anterior')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->getJson($url)->assertOk()->assertJsonCount(24, 'historial');
        Http::assertSentCount(1);
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
        $r = null;
        Http::fake(function () use (&$r) {
            return Http::response($r);
        });
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

    public function test_error_del_proveedor_en_web_conserva_el_mismo_estado_y_mensaje_de_la_api(): void
    {
        $this->actingAs(User::factory()->create());
        Http::fake(['api.open-meteo.com/*' => Http::response(['message' => 'detalle privado'], 429)]);
        $this->getJson('/mapa/tiempo?latitud=0&longitud=0')->assertStatus(503)
            ->assertJsonPath('error.codigo', 'PRONOSTICO_NO_DISPONIBLE')->assertDontSee('detalle privado');
        $this->getJson('/mapa/tiempo?latitud=91&longitud=0')->assertUnprocessable()
            ->assertJsonPath('error.codigo', 'FILTROS_INVALIDOS');
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
