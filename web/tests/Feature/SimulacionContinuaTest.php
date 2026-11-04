<?php

namespace Tests\Feature;

use App\Http\Controllers\SimulacionContinuaController;
use App\Http\Controllers\SimulacionesController;
use App\Models\SimulacionContinua;
use App\Models\User;
use App\Services\Datos\CatalogoAmbiental;
use App\Services\Simulaciones\MotorContinuo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SimulacionContinuaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        // Solo instala los snippets en la aplicación de pruebas; no edita rutas compartidas.
        Route::middleware(['api', 'auth:sanctum', 'abilities:cuenta', 'cuenta.activa'])->group(function () {
            Route::get('/api/v1/simulacion-continua/estado', [SimulacionContinuaController::class, 'estado']);
            Route::post('/api/v1/simulacion-continua/accion', [SimulacionContinuaController::class, 'accion']);
        });
        Route::middleware(['web', 'auth', 'cuenta.activa', 'can:simulaciones.gestionar'])
            ->get('/simulaciones/{id}/estado', [SimulacionesController::class, 'mostrar']);
        Mail::fake();
        Notification::fake();
    }

    private function cliente(string $rol = 'analista', bool $activo = true): User
    {
        $u = User::factory()->create(['rol' => $rol, 'activo' => $activo]);
        Sanctum::actingAs($u, ['cuenta']);

        return $u;
    }

    private function accion(string $accion, int $revision, array $extra = [])
    {
        return $this->postJson('/api/v1/simulacion-continua/accion', ['accion' => $accion, 'revision' => $revision] + $extra);
    }

    private function preparar(): array
    {
        return $this->accion('preparar', 0, ['semilla' => 'prueba-global', 'intervalo_ms' => 3000])->assertOk()->json();
    }

    public function test_consultar_y_comando_no_crean_ni_inician_simulaciones(): void
    {
        $this->cliente('visualizador');
        $this->getJson('/api/v1/simulacion-continua/estado')->assertOk()->assertJsonPath('estado', 'sin_preparar');
        $this->artisan('simulaciones:avanzar')->assertSuccessful();
        $this->assertDatabaseCount('simulacion_continua', 0);
        $this->assertDatabaseCount('lecturas_ambientales', 0);
        Http::assertNothingSent();
        Mail::assertNothingSent();
        Notification::assertNothingSent();
    }

    public function test_permisos_capacidad_y_cuenta_activa_son_obligatorios(): void
    {
        $this->getJson('/api/v1/simulacion-continua/estado')->assertUnauthorized();
        $u = $this->cliente('visualizador');
        $this->accion('preparar', 0, ['semilla' => 'x'])->assertForbidden();
        $this->get('/reportes?vista=historial')->assertOk()->assertDontSee('data-control=', false)->assertSee('Simulación continua global');
        $this->assertSame('visualizador', $u->fresh()->rol->value);
        Sanctum::actingAs($u, ['otra']);
        $this->getJson('/api/v1/simulacion-continua/estado')->assertForbidden();
        $this->cliente('analista', false);
        $this->getJson('/api/v1/simulacion-continua/estado')->assertUnauthorized();
    }

    public function test_global_comparte_el_estado_sin_ceder_controles_a_otra_cuenta(): void
    {
        $propietario = $this->cliente();
        $s = $this->preparar();
        $this->assertCount(count(app(CatalogoAmbiental::class)->leer()['puntos']), $s['puntos']);
        $this->travel(10)->seconds();
        $this->getJson('/api/v1/simulacion-continua/estado')->assertJsonPath('paso', 0)->assertJsonPath('estado', 'lista');
        $this->accion('iniciar', 1)->assertOk();
        $this->travel(6)->seconds();
        $s = $this->getJson('/api/v1/simulacion-continua/estado')->assertOk()->assertJsonPath('paso', 2)->json();
        $this->cliente();
        $this->getJson('/api/v1/simulacion-continua/estado')->assertOk()->assertJsonPath('puntos', $s['puntos'])->assertJsonPath('propia', false)->assertDontSee('usuario_id');
        $this->accion('pausar', $s['revision'])->assertNotFound();
        $this->accion('preparar', $s['revision'], ['semilla' => 'robo'])->assertNotFound();
        Sanctum::actingAs($propietario, ['cuenta']);
        $this->accion('pausar', 2)->assertConflict();
        $this->accion('pausar', $s['revision'])->assertOk()->assertJsonPath('estado', 'pausada');
        $this->assertDatabaseCount('lecturas_ambientales', 0);
    }

    public function test_pausa_reinicio_inyeccion_y_revisiones_conservan_cero_y_procedencia(): void
    {
        $this->cliente();
        $inicial = $this->preparar();
        $this->accion('inyectar', 1, ['punto' => 'santacruz', 'valores' => ['aqi' => 0]])->assertConflict();
        $this->accion('iniciar', 1)->assertOk();
        $s = $this->accion('inyectar', 2, ['punto' => 'santacruz', 'valores' => ['aqi' => 0]])->assertOk()->json();
        $p = collect($s['puntos'])->firstWhere('codigo', 'santacruz');
        $this->assertEquals(0, $p['valores']['aqi']);
        $this->assertTrue($p['inyecciones']['aqi']);
        $this->assertCount(1, $s['ajustes']);
        $this->accion('pausar', 3)->assertOk();
        $this->travel(2)->hours();
        $this->getJson('/api/v1/simulacion-continua/estado')->assertJsonPath('puntos', $s['puntos'])->assertJsonPath('paso', 0);
        $r = $this->accion('reiniciar', 4)->assertOk()->assertJsonPath('estado', 'lista')->assertJsonPath('puntos', $inicial['puntos'])->json();
        $this->assertNotSame($inicial['version'], $r['version']);
        $this->accion('iniciar', 5)->assertOk();
        $this->accion('detener', 6)->assertOk();
        $this->cliente();
        $this->accion('preparar', 7, ['semilla' => 'otra'])->assertOk()->assertJsonPath('propia', true);
    }

    public function test_validacion_rechaza_inyecciones_arbitrarias_sin_cambios_parciales(): void
    {
        $this->cliente();
        foreach ([['intervalo_ms' => 1], ['semilla' => '../clave'], ['usuario_id' => 50]] as $mal) {
            $this->accion('preparar', 0, $mal + ['semilla' => 'x'])->assertUnprocessable();
        }
        $this->preparar();
        $this->accion('iniciar', 1)->assertOk();
        $antes = SimulacionContinua::find(1)->contenido;
        foreach ([['punto' => 'no-existe', 'valores' => ['aqi' => 0]], ['punto' => 'santacruz', 'valores' => ['password' => 1]],
            ['punto' => 'santacruz', 'valores' => ['aqi' => 0, 'temperatura' => 1000]], ['punto' => 'santacruz', 'valores' => []],
            ['punto' => 'santacruz', 'valores' => ['aqi' => 'NaN']]] as $mal) {
            $this->accion('inyectar', 2, $mal)->assertUnprocessable();
            $this->assertSame($antes, SimulacionContinua::find(1)->contenido);
        }
    }

    public function test_recuperacion_esta_acotada_y_repetir_consultas_no_duplica_pasos(): void
    {
        $this->cliente();
        $this->preparar();
        $this->accion('iniciar', 1)->assertOk();
        $this->travel(3)->minutes();
        foreach ([20, 40, 60, 60] as $paso) {
            $this->getJson('/api/v1/simulacion-continua/estado')->assertOk()->assertJsonPath('paso', $paso);
        }
        $this->assertDatabaseCount('lecturas_ambientales', 0);
        $this->assertDatabaseCount('puntos_ambientales', 0);
        Http::assertNothingSent();
        Mail::assertNothingSent();
        Notification::assertNothingSent();
    }

    public function test_motor_repite_el_recorrido_y_respeta_rangos_de_cada_localidad(): void
    {
        $m = app(MotorContinuo::class);
        $a = $m->inicial('misma');
        $b = $m->inicial('misma');
        for ($paso = 1; $paso <= 30; $paso++) {
            $ms = (int) now()->getPreciseTimestamp(3) + $paso * 3000;
            $a = $m->siguiente($a, 'misma', $paso, $ms);
            $b = $m->siguiente($b, 'misma', $paso, $ms);
        }
        $this->assertSame($a, $b);
        foreach ($a as $p) {
            foreach ($p['valores'] as $k => $valor) {
                $this->assertGreaterThanOrEqual($p['rangosSimulacion'][$k][0], $valor);
                $this->assertLessThanOrEqual($p['rangosSimulacion'][$k][1], $valor);
            }
        }
    }

    public function test_pausa_fija_el_tramo_pendiente_sin_saltar_pasos_ni_avanzar_durante_la_pausa(): void
    {
        $this->cliente();
        $this->preparar();
        $this->accion('iniciar', 1)->assertOk();
        $this->travel(3)->minutes();
        $this->accion('pausar', 2)->assertOk()->assertJsonPath('paso', 20)->assertJsonPath('estado', 'pausada');
        $this->travel(1)->hours();
        $this->getJson('/api/v1/simulacion-continua/estado')->assertOk()->assertJsonPath('paso', 40)->assertJsonPath('pendientes', 20);
        $this->getJson('/api/v1/simulacion-continua/estado')->assertOk()->assertJsonPath('paso', 60)->assertJsonPath('pendientes', 0);
        $this->getJson('/api/v1/simulacion-continua/estado')->assertOk()->assertJsonPath('paso', 60);
    }

    public function test_escenario_avanza_en_servidor_y_otras_pantallas_observan_pausa_y_final(): void
    {
        $this->cliente();
        $id = $this->postJson('/api/v1/simulaciones', ['nombre' => 'Dos pantallas', 'metrica' => 'temperatura', 'inicio' => now()->toIso8601ZuluString(),
            'dias' => 1, 'intervalo' => 360, 'semilla' => 'dos', 'zonas' => [['punto' => 'santacruz', 'escenario' => 'calor', 'nivel' => 'medio']]])->assertCreated()->json('id');
        $accion = '/api/v1/simulaciones/'.$id.'/accion';
        $this->postJson($accion, ['accion' => 'iniciar', 'posicion' => 0, 'revision' => 0])->assertOk();
        $this->travel(1400)->milliseconds();
        $this->getJson('/simulaciones/'.$id.'/estado')->assertOk()->assertJsonPath('posicion', 2)->assertJsonPath('estado', 'activa');
        $this->postJson($accion, ['accion' => 'pausar', 'posicion' => 2, 'revision' => 1])->assertOk();
        $this->travel(1)->minutes();
        $this->getJson('/api/v1/simulaciones/'.$id)->assertJsonPath('posicion', 2)->assertJsonPath('estado', 'pausada');
        $this->postJson($accion, ['accion' => 'iniciar', 'posicion' => 2, 'revision' => 2])->assertOk();
        $this->travel(2)->seconds();
        $this->getJson('/api/v1/simulaciones/'.$id)->assertJsonPath('posicion', 4)->assertJsonPath('estado', 'finalizada')->assertJsonPath('revision', 4);
    }
}
