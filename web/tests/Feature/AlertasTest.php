<?php

namespace Tests\Feature;

use App\Models\AlertaAmbiental;
use App\Models\AvisoAmbiental;
use App\Models\PreferenciasAvisos;
use App\Models\PuntoAmbiental;
use App\Models\User;
use App\Services\Alertas\PrepararAvisos;
use App\Services\Alertas\UmbralesAmbientales;
use App\Services\Api\ErrorConsulta;
use App\Services\Datos\RegistrarLecturas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AlertasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        Http::preventStrayRequests();
        Mail::fake();
        PuntoAmbiental::create(['codigo' => 'campus', 'nombre' => 'Campus', 'pais' => 'BO', 'latitud' => -17.8, 'longitud' => -63.2, 'tipo' => 'manual']);
    }

    private function cliente(string $rol = 'analista'): User
    {
        $u = User::factory()->create(['rol' => $rol]);
        Sanctum::actingAs($u, ['cuenta']);

        return $u;
    }

    private function lectura(float $valor, array $cambios = []): array
    {
        return $cambios + ['punto' => 'campus', 'metrica' => 'temperatura', 'valor' => $valor, 'instante' => now()->subMinute()->toIso8601ZuluString(), 'origen' => 'observado', 'fuente' => 'manual'];
    }

    private function registrar(array $filas): void
    {
        app(RegistrarLecturas::class)->ejecutar($filas);
    }

    private function suscribir(User $u, bool $simulados = false): void
    {
        PreferenciasAvisos::create(['usuario_id' => $u->id, 'activos' => true, 'simulados' => $simulados, 'metricas' => ['temperatura', 'aqi']]);
    }

    public function test_catalogo_reproduce_29_niveles_y_no_convierte_huecos_decimales_en_emergencias(): void
    {
        $u = app(UmbralesAmbientales::class);
        $c = $u->catalogo();
        $this->assertCount(5, $c['metricas']);
        $this->assertSame(29, array_sum(array_map('count', $c['metricas'])));
        $this->assertSame(hash_file('sha256', base_path('../'.$c['procedencia']['archivo'])), $c['procedencia']['sha256']);
        $this->assertSame(1, $u->clasificar('aqi', 0)['nivel']);
        $this->assertSame(1, $u->clasificar('aqi', 50)['nivel']);
        $this->assertSame(2, $u->clasificar('aqi', 50.5)['nivel']);
        $this->assertSame(3, $u->clasificar('aqi', 100.4)['nivel']);
        $this->assertSame('informativa', $u->clasificar('aqi', 50.5)['severidad']);
        $this->assertSame(6, $u->clasificar('temperatura', 38)['nivel']);
        $this->assertSame(7, $u->clasificar('temperatura', 38.01)['nivel']);
        $this->assertSame(2, $u->clasificar('ica', 25.5)['nivel']);
        $this->assertSame(1, $u->clasificar('ruido', 30)['nivel']);
    }

    public function test_alerta_solo_al_cambiar_de_nivel_y_el_estado_sobrevive_entre_peticiones(): void
    {
        $this->registrar([$this->lectura(20, ['instante' => now()->subMinutes(5)->toIso8601ZuluString()]),
            $this->lectura(45, ['instante' => now()->subMinutes(4)->toIso8601ZuluString()]),
            $this->lectura(46, ['instante' => now()->subMinutes(3)->toIso8601ZuluString()]),
            $this->lectura(25, ['instante' => now()->subMinutes(2)->toIso8601ZuluString()])]);
        $this->registrar([$this->lectura(45)]);
        $this->registrar([$this->lectura(45)]);
        $this->assertDatabaseCount('alertas_ambientales', 2);
        $this->assertDatabaseCount('estados_alertas', 1);
        $this->assertDatabaseCount('lecturas_ambientales', 5);
    }

    public function test_datos_atrasados_no_hacen_regresar_el_estado_actual(): void
    {
        $this->registrar([$this->lectura(45)]);
        $this->registrar([$this->lectura(20, ['instante' => now()->subHour()->toIso8601ZuluString()])]);
        $this->registrar([$this->lectura(46, ['instante' => now()->toIso8601ZuluString()])]);
        $this->assertDatabaseCount('alertas_ambientales', 1);
        $this->assertSame(7, (int) DB::table('estados_alertas')->value('nivel'));
    }

    public function test_observaciones_y_simulados_tienen_estados_y_filtros_separados(): void
    {
        $this->registrar([$this->lectura(45), $this->lectura(45, ['origen' => 'simulado']), $this->lectura(45, ['fuente' => 'otra'])]);
        $this->assertDatabaseCount('alertas_ambientales', 3);
        $this->assertDatabaseCount('estados_alertas', 3);
        $this->cliente('visualizador');
        $this->getJson('/api/v1/alertas')->assertOk()->assertJsonCount(2, 'datos');
        $this->getJson('/api/v1/alertas?origen=simulado')->assertOk()->assertJsonCount(1, 'datos')->assertJsonPath('datos.0.origen', 'simulado');
    }

    public function test_reconocer_exige_permiso_y_registra_el_actor_real_sin_reemplazarlo(): void
    {
        $this->registrar([$this->lectura(45)]);
        $id = AlertaAmbiental::firstOrFail()->id;
        $this->getJson('/api/v1/alertas')->assertUnauthorized();
        $this->cliente('visualizador');
        $this->patchJson('/api/v1/alertas/'.$id.'/reconocer')->assertForbidden();
        $u = $this->cliente();
        $this->patchJson('/api/v1/alertas/'.$id.'/reconocer', ['usuarioId' => 99])->assertUnprocessable();
        $this->patchJson('/api/v1/alertas/'.$id.'/reconocer')->assertOk()->assertJsonPath('reconocida', true);
        $this->assertSame($u->id, AlertaAmbiental::first()->reconocida_por);
        $this->cliente('admin');
        $this->patchJson('/api/v1/alertas/'.$id.'/reconocer')->assertOk();
        $this->assertSame($u->id, AlertaAmbiental::first()->reconocida_por);
        $this->getJson('/api/v1/alertas?reconocida=1')->assertJsonCount(1, 'datos');
        $this->getJson('/api/v1/alertas?reconocida=0')->assertJsonCount(0, 'datos');
    }

    public function test_avisos_respetan_opt_in_distancia_cuentas_activas_y_simulacion(): void
    {
        $cerca = User::factory()->create();
        $lejos = User::factory()->create();
        $inactivo = User::factory()->create(['activo' => false]);
        $cerca->preferencias()->create(['latitud' => -17.8, 'longitud' => -63.2]);
        $lejos->preferencias()->create(['latitud' => 0, 'longitud' => 0]);
        foreach ([$cerca, $lejos, $inactivo] as $u) {
            $this->suscribir($u);
        }
        $this->registrar([$this->lectura(45)]);
        $this->assertDatabaseCount('avisos_ambientales', 1);
        $this->assertDatabaseHas('avisos_ambientales', ['usuario_id' => $cerca->id]);
        $this->registrar([$this->lectura(45, ['origen' => 'simulado'])]);
        $this->assertDatabaseCount('avisos_ambientales', 1);
        $this->assertEqualsWithDelta(0, PrepararAvisos::distancia(0, 0, 0, 0), 0.001);
        Mail::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_cooldown_limita_avisos_pero_conserva_las_alertas(): void
    {
        $u = User::factory()->create();
        $this->suscribir($u);
        $this->registrar([$this->lectura(160, ['metrica' => 'aqi', 'instante' => now()->subSeconds(20)->toIso8601ZuluString()]),
            $this->lectura(250, ['metrica' => 'aqi', 'instante' => now()->subSeconds(10)->toIso8601ZuluString()])]);
        $this->assertDatabaseCount('alertas_ambientales', 2);
        $this->assertDatabaseCount('avisos_ambientales', 1);
        $this->travel(3)->minutes();
        $this->registrar([$this->lectura(400, ['metrica' => 'aqi'])]);
        $this->assertDatabaseCount('avisos_ambientales', 2);
    }

    public function test_un_escenario_solo_produce_aviso_si_la_cuenta_lo_eligio(): void
    {
        $u = User::factory()->create();
        $this->suscribir($u, true);
        $this->registrar([$this->lectura(45, ['origen' => 'simulado'])]);
        $this->assertDatabaseCount('avisos_ambientales', 1);
        $this->assertSame('simulado', AvisoAmbiental::first()->alerta->origen);
    }

    public function test_no_hay_avisos_sin_suscripcion_ni_por_cargar_datos_historicos(): void
    {
        User::factory()->create();
        $u = User::factory()->create();
        $this->suscribir($u);
        $this->registrar([$this->lectura(45, ['instante' => now()->subDay()->toIso8601ZuluString()])]);
        $this->assertDatabaseCount('alertas_ambientales', 1);
        $this->assertDatabaseCount('avisos_ambientales', 0);
    }

    public function test_bandeja_y_preferencias_son_privadas_y_leer_es_idempotente(): void
    {
        $u = $this->cliente('visualizador');
        $this->putJson('/api/v1/avisos/preferencias', ['activos' => true, 'simulados' => false, 'metricas' => ['temperatura']])->assertOk();
        $this->registrar([$this->lectura(45)]);
        $id = AvisoAmbiental::first()->id;
        $this->getJson('/api/v1/avisos')->assertOk()->assertJsonPath('sinLeer', 1);
        $this->patchJson('/api/v1/avisos/'.$id.'/leer')->assertOk();
        $fecha = AvisoAmbiental::first()->leido_el;
        $this->patchJson('/api/v1/avisos/'.$id.'/leer')->assertOk();
        $this->assertTrue($fecha->equalTo(AvisoAmbiental::first()->leido_el));
        $this->getJson('/api/v1/avisos')->assertJsonPath('sinLeer', 0);
        $this->cliente();
        $this->patchJson('/api/v1/avisos/'.$id.'/leer')->assertNotFound();
        $this->getJson('/api/v1/avisos')->assertJsonCount(0, 'datos')->assertJsonPath('preferencias.activos', false);
        $this->assertDatabaseHas('preferencias_avisos', ['usuario_id' => $u->id, 'activos' => true]);
    }

    public function test_alertas_y_estado_se_revierten_si_el_lote_tiene_conflicto(): void
    {
        $vieja = $this->lectura(20);
        $this->registrar([$vieja]);
        try {
            $this->registrar([$this->lectura(45, ['instante' => now()->toIso8601ZuluString()]), array_replace($vieja, ['valor' => 30])]);
        } catch (ErrorConsulta $e) {
            $this->assertSame(409, $e->estado);
        }
        $this->assertDatabaseCount('lecturas_ambientales', 1);
        $this->assertDatabaseCount('alertas_ambientales', 0);
        $this->assertSame(4, (int) DB::table('estados_alertas')->value('nivel'));
    }

    public function test_pantallas_catalogo_y_filtros_incorrectos(): void
    {
        $this->cliente('visualizador');
        $this->get('/alertas')->assertOk()->assertSee('Consultar alertas')->assertDontSee('Reconocer alerta');
        $this->get('/avisos')->assertOk()->assertSee('Configurar avisos');
        $this->get('/umbrales')->assertOk()->assertSee('Niveles ambientales');
        $this->getJson('/api/v1/umbrales')->assertOk()->assertJsonCount(7, 'metricas.temperatura');
        $this->getJson('/api/v1/alertas?desde=2026-02-30T00:00:00Z')->assertUnprocessable();
        $this->getJson('/api/v1/alertas?metrica=falsa')->assertUnprocessable();
        $this->getJson('/api/v1/alertas?origen[]=observado')->assertUnprocessable();
    }

    public function test_revision_del_historial_se_puede_repetir_sin_duplicar(): void
    {
        DB::table('lecturas_ambientales')->insert(['punto_id' => PuntoAmbiental::first()->id, 'metrica' => 'temperatura', 'valor' => 45, 'instante' => now()->subDay()->format('Y-m-d H:i:s'), 'hora_utc' => now()->subDay()->startOfHour()->format('Y-m-d H:i:s'), 'origen' => 'observado', 'fuente' => 'manual', 'latitud' => -17.8, 'longitud' => -63.2]);
        $this->artisan('alertas:evaluar')->expectsOutputToContain('Alertas nuevas: 1')->assertSuccessful();
        $this->artisan('alertas:evaluar')->expectsOutputToContain('Alertas nuevas: 0')->assertSuccessful();
        $this->assertDatabaseCount('alertas_ambientales', 1);
        $this->assertDatabaseCount('avisos_ambientales', 0);
    }
}
