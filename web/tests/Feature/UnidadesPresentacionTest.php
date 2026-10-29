<?php

namespace Tests\Feature;

use App\Models\PuntoAmbiental;
use App\Models\User;
use App\Services\Datos\RegistrarLecturas;
use App\Services\Datos\UnidadesPresentacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UnidadesPresentacionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    public function test_las_unidades_por_defecto_no_crean_preferencias_ni_se_comparten_entre_cuentas(): void
    {
        $uno = User::factory()->create();
        $otro = User::factory()->create();
        $this->actingAs($uno)->get('/cuenta')->assertOk()->assertSee('Unidades de medida')->assertSee('Celsius (°C)');
        $this->assertDatabaseCount('preferencias_cuenta', 0);
        $this->putJson('/cuenta/unidades', ['unidades' => ['temperatura' => 'F', 'viento' => 'mph']])->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')->assertJsonPath('unidades.temperatura', 'F');
        $this->assertNull($otro->fresh()->preferencias);
        $this->actingAs($otro)->get('/mapa')->assertOk()->assertSee('"temperatura":"C"', false);
        $this->actingAs($uno->fresh())->get('/mapa')->assertOk()->assertSee('"temperatura":"F"', false);
        Http::assertNothingSent();
    }

    public function test_guardar_parcialmente_conserva_la_otra_unidad_ubicacion_y_avisos(): void
    {
        $usuario = User::factory()->create();
        $this->actingAs($usuario)->put('/cuenta/preferencias', ['latitud' => 0, 'longitud' => 0, 'notif_email' => true])->assertSessionHasNoErrors();
        $this->putJson('/cuenta/unidades', ['unidades' => ['temperatura' => 'F', 'viento' => 'm/s']])->assertOk();
        $this->putJson('/cuenta/unidades', ['unidades' => ['temperatura' => 'K']])->assertOk()->assertJsonPath('unidades.viento', 'm/s');
        $p = $usuario->fresh()->preferencias;
        $this->assertSame(0.0, $p->latitud);
        $this->assertTrue($p->notif_email);
        $this->putJson('/cuenta/preferencias', ['latitud' => null, 'longitud' => null])->assertRedirect();
        $this->assertSame(['temperatura' => 'K', 'viento' => 'm/s'], $usuario->fresh()->preferencias->unidades);
    }

    public function test_rechaza_opciones_y_claves_ajenas_sin_cambiar_datos(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u);
        foreach ([[], ['temperatura' => null], ['temperatura' => false], ['temperatura' => ['F']], ['temperatura' => 'kelvin'], ['viento' => 'K'], ['aqi' => 'PM25'], ['temperatura' => 'F', 'rol' => 'admin']] as $unidades) {
            $this->putJson('/cuenta/unidades', compact('unidades'))->assertUnprocessable();
        }
        $this->putJson('/cuenta/unidades', ['unidades' => ['temperatura' => 'F'], 'user_id' => 99, 'rol' => 'admin'])->assertUnprocessable();
        $this->assertDatabaseCount('preferencias_cuenta', 0);
        $this->assertSame('visualizador', $u->fresh()->rol->value);
    }

    public function test_api_exige_cuenta_activa_y_capacidad_y_devuelve_preferencia_y_catalogo(): void
    {
        $this->getJson('/api/v1/cuenta/unidades')->assertUnauthorized();
        $this->putJson('/api/v1/cuenta/unidades', ['unidades' => ['temperatura' => 'F']])->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create(), ['otro']);
        $this->getJson('/api/v1/cuenta/unidades')->assertForbidden();
        Sanctum::actingAs(User::factory()->create(['activo' => false]), ['cuenta']);
        $this->putJson('/api/v1/cuenta/unidades', ['unidades' => ['temperatura' => 'F']])->assertUnauthorized();
        $usuario = User::factory()->create();
        Sanctum::actingAs($usuario, ['cuenta']);
        $this->getJson('/api/v1/cuenta/unidades')->assertOk()->assertJsonPath('unidades.temperatura', 'C')->assertJsonPath('catalogo.viento.base', 'km/h');
        $this->putJson('/api/v1/cuenta/unidades', ['unidades' => ['temperatura' => 'F']])->assertOk();
        $this->getJson('/api/v1/cuenta')->assertOk()->assertJsonPath('preferencias.unidades.temperatura', 'F')->assertJsonMissingPath('usuario.password');
    }

    public function test_la_presentacion_no_reescribe_lecturas_ni_la_unidad_de_la_api(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 28)->setTime(20, 0));
        $u = User::factory()->create();
        PuntoAmbiental::create(['codigo' => 'campus', 'nombre' => 'Campus', 'pais' => 'BO', 'latitud' => 0, 'longitud' => 0, 'tipo' => 'manual']);
        app(RegistrarLecturas::class)->ejecutar([['punto' => 'campus', 'metrica' => 'temperatura', 'valor' => 0,
            'instante' => '2026-10-28T07:00:00Z', 'origen' => 'observado', 'fuente' => 'manual']]);
        $this->actingAs($u)->putJson('/cuenta/unidades', ['unidades' => ['temperatura' => 'F']])->assertOk();
        $this->getJson('/mapa/puntos?metrica=temperatura&desde=2026-10-28T06:00:00Z&hasta=2026-10-28T08:00:00Z')
            ->assertOk()->assertJsonPath('unidad', '°C')->assertJsonPath('datos.0.lecturas.0.valor', 0);
        $this->get('/sensores?punto=campus&metrica=temperatura&desde=2026-10-28T02:00&hasta=2026-10-28T04:00')
            ->assertOk()->assertSee('32 °F')->assertSee('data-valor="0"', false);
        $this->assertDatabaseHas('lecturas_ambientales', ['valor' => 0, 'metrica' => 'temperatura']);
        $this->assertDatabaseCount('lecturas_ambientales', 1);
    }

    public function test_conversiones_y_diferencias_conservan_ceros_y_faltantes(): void
    {
        $p = app(UnidadesPresentacion::class);
        $f = ['temperatura' => 'F', 'viento' => 'm/s'];
        $this->assertSame(32.0, $p->valor('temperatura', 0, '°C', $f));
        $this->assertSame(-40.0, $p->valor('temperatura', -40, '°C', $f));
        $this->assertEqualsWithDelta(3.6, $p->valor('temperatura', 2, '°C', $f, true), 1e-10);
        $this->assertSame(0.0, $p->valor('temperatura', 0, '°C', $f, true));
        $this->assertEqualsWithDelta(273.15, $p->valor('temperatura', 0, '°C', ['temperatura' => 'K']), 1e-10);
        $this->assertSame(2.0, $p->valor('temperatura', 2, '°C', ['temperatura' => 'K'], true));
        $this->assertSame(10.0, $p->valor('viento', 36, 'km/h', $f));
        $this->assertEqualsWithDelta(10, $p->valor('viento', 16.09344, 'km/h', ['viento' => 'mph']), 1e-10);
        foreach ([null, '', '0', false, INF, NAN] as $faltante) {
            $this->assertNull($p->valor('temperatura', $faltante, '°C', $f));
            $this->assertSame('No disponible', $p->texto('temperatura', $faltante, '°C', $f));
        }
    }

    public function test_no_convierte_otra_variable_ni_repite_conversion_de_una_unidad_distinta(): void
    {
        $p = app(UnidadesPresentacion::class);
        $f = ['temperatura' => 'F', 'aqi' => 'PM25'];
        $this->assertSame(20.0, $p->valor('aqi', 20, 'AQI', $f));
        $this->assertSame('AQI', $p->unidad('aqi', 'AQI', $f));
        $this->assertSame(32.0, $p->valor('temperatura', 32, '°F', $f));
        $this->assertSame(0.0, $p->valor('temperatura', 0, '°C', ['temperatura' => 'invalida']));
    }

    public function test_formulario_funciona_sin_javascript_y_normaliza_preferencias_guardadas_invalidas(): void
    {
        $u = User::factory()->create();
        $u->preferencias()->create(['unidades' => ['temperatura' => 'otra', 'viento' => null]]);
        $this->assertSame(['temperatura' => 'C', 'viento' => 'km/h'], app(UnidadesPresentacion::class)->preferencias($u));
        $this->actingAs($u)->from('/cuenta')->put('/cuenta/unidades', ['unidades' => ['temperatura' => 'K']])->assertRedirect('/cuenta')->assertSessionHas('estado', 'Unidades guardadas.');
        $this->get('/cuenta')->assertOk()->assertSee('value="K" selected', false);
    }
}
