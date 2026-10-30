<?php

namespace Tests\Feature;

use App\Models\PuntoAmbiental;
use App\Models\User;
use App\Services\Datos\RegistrarLecturas;
use App\Services\Simulaciones\SimularEscenario;
use App\Services\Territorios\RegionesEnvirosense;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SimulacionesTest extends TestCase
{
    use RefreshDatabase;

    private function parametros(array $cambios = []): array
    {
        return $cambios + ['nombre' => 'Prueba de calor', 'metrica' => 'temperatura', 'inicio' => now()->subDays(2)->startOfHour()->toIso8601ZuluString(),
            'dias' => 1, 'intervalo' => 360, 'semilla' => 'prueba-1', 'zonas' => [['punto' => 'santacruz', 'escenario' => 'calor', 'nivel' => 'medio']]];
    }

    private function analista(): User
    {
        $u = User::factory()->create(['rol' => 'analista']);
        Sanctum::actingAs($u, ['cuenta']);

        return $u;
    }

    public function test_sin_cuenta_o_sin_permiso_no_se_inician_simulaciones(): void
    {
        $this->postJson('/api/v1/simulaciones', $this->parametros())->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create(), ['cuenta']);
        $this->postJson('/api/v1/simulaciones', $this->parametros())->assertForbidden();
        $this->get('/simulaciones')->assertOk()->assertSee('Panel de')->assertDontSee('data-control=', false)->assertDontSee('data-preparar', false);
        $this->assertDatabaseCount('simulaciones', 0);
    }

    public function test_catalogo_recupera_diez_casos_con_tres_intensidades_y_cinco_variables(): void
    {
        $this->analista();
        $r = $this->getJson('/api/v1/simulaciones/catalogo')->assertOk();
        $this->assertCount(5, $r->json('escenarios'));
        foreach ($r->json('escenarios') as $casos) {
            $this->assertCount(2, $casos);
            foreach ($casos as $caso) {
                $this->assertCount(3, $caso['niveles']);
            }
        }
        $r->assertJsonCount(61, 'puntos')->assertJsonCount(241, 'regiones');
        Http::assertNothingSent();
    }

    public function test_consulta_visualizadora_no_expone_sesiones_privadas_ni_permite_escribir(): void
    {
        $propietario = User::factory()->create(['rol' => 'analista']);
        $sesion = app(SimularEscenario::class)->crear($this->parametros(['nombre' => 'Escenario privado del analista']), $propietario->id);
        $this->actingAs(User::factory()->create(['rol' => 'visualizador']));
        $this->get('/simulaciones?sesion='.$sesion->id)->assertOk()
            ->assertSee('Simulación continua global')->assertDontSee($sesion->nombre)
            ->assertDontSee('data-control=', false)->assertDontSee('data-inyectar', false)
            ->assertDontSee('class="sim-configuracion"', false);
        $this->post('/simulaciones', $this->parametros())->assertForbidden();
        $this->post('/simulaciones/'.$sesion->id.'/accion', ['accion' => 'iniciar', 'revision' => 0, 'posicion' => 0])->assertForbidden();
        $this->post('/simulaciones/'.$sesion->id.'/historial')->assertForbidden();
        $this->assertDatabaseCount('simulaciones', 1);
        $this->assertDatabaseCount('lecturas_ambientales', 0);
        $this->assertSame('lista', $sesion->fresh()->estado);
    }

    public function test_escenario_es_reproducible_y_no_se_mezcla_automaticamente_con_lecturas(): void
    {
        $u = $this->analista();
        $a = $this->postJson('/api/v1/simulaciones', $this->parametros())->assertCreated();
        $b = $this->postJson('/api/v1/simulaciones', $this->parametros())->assertCreated();
        $this->assertSame($a->json('resultado'), $b->json('resultado'));
        $a->assertJsonPath('resultado.origen', 'simulado')->assertJsonCount(5, 'resultado.instantes')->assertJsonMissingPath('usuario_id');
        $otra = $this->postJson('/api/v1/simulaciones', $this->parametros(['semilla' => 'otra']))->assertCreated();
        $this->assertNotSame($a->json('resultado.series.0.valores'), $otra->json('resultado.series.0.valores'));
        $this->assertDatabaseCount('lecturas_ambientales', 0);
        $this->assertDatabaseCount('puntos_ambientales', 0);
        $this->assertDatabaseHas('simulaciones', ['id' => $a->json('id'), 'usuario_id' => $u->id]);
        Http::assertNothingSent();
    }

    public function test_compara_zonas_con_intensidades_distintas_y_conserva_agua_y_ruido(): void
    {
        $this->analista();
        foreach (['ica' => 'vertido', 'ruido' => 'construccion', 'humedad' => 'sequia', 'aqi' => 'incendio'] as $metrica => $escenario) {
            $r = $this->postJson('/api/v1/simulaciones', $this->parametros(['metrica' => $metrica, 'zonas' => [
                ['punto' => 'santacruz', 'escenario' => $escenario, 'nivel' => 'bajo'], ['punto' => 'lapaz', 'escenario' => $escenario, 'nivel' => 'alto'],
            ]]))->assertCreated()->assertJsonCount(2, 'resultado.series');
            $this->assertNotSame($r->json('resultado.series.0.valores'), $r->json('resultado.series.1.valores'));
        }
    }

    public function test_zona_por_region_usa_la_geometria_original_y_no_crea_puntos_al_prepararse(): void
    {
        $this->analista();
        $r = collect(app(RegionesEnvirosense::class)->listado())->first(fn ($r) => $r['pais'] === 'BO' && $r['nombre'] === 'Santa Cruz');
        $this->postJson('/api/v1/simulaciones', $this->parametros(['zonas' => [['region' => $r['codigo'], 'escenario' => 'calor', 'nivel' => 'alto']]]))
            ->assertCreated()->assertJsonPath('resultado.series.0.region', $r['codigo'])->assertJsonPath('resultado.series.0.nombre', 'Santa Cruz');
        $this->assertDatabaseCount('puntos_ambientales', 0);
    }

    public function test_personalizado_constante_respeta_el_valor_incluido_cero(): void
    {
        $this->analista();
        $this->postJson('/api/v1/simulaciones', $this->parametros(['zonas' => [['punto' => 'santacruz', 'escenario' => 'personalizado', 'nivel' => 'medio', 'inicio' => 0, 'fin' => 0, 'curva' => 'lineal']]]))
            ->assertCreated()->assertJsonPath('resultado.series.0.valores', [0, 0, 0, 0, 0]);
    }

    public function test_filtros_invalidos_y_cargas_excesivas_no_crean_sesiones(): void
    {
        $this->analista();
        foreach ([
            ['dias' => 31], ['intervalo' => 0], ['dias' => 30, 'intervalo' => 1], ['metrica' => 'inventada'], ['inicio' => '2026-02-30T12:00:00Z'],
            ['zonas' => [['punto' => 'no-existe', 'escenario' => 'calor', 'nivel' => 'medio']]],
            ['zonas' => [['punto' => 'santacruz', 'region' => '../fuera', 'escenario' => 'calor', 'nivel' => 'medio']]],
            ['zonas' => [['punto' => 'santacruz', 'escenario' => 'incendio', 'nivel' => 'medio']]],
            ['zonas' => [['punto' => 'santacruz', 'escenario' => 'calor', 'nivel' => 'medio', 'fin' => 99999]]],
            ['usuario_id' => 123], ['estado' => 'finalizada'],
        ] as $mal) {
            $this->postJson('/api/v1/simulaciones', $this->parametros($mal))->assertUnprocessable();
        }
        $this->assertDatabaseCount('simulaciones', 0);
    }

    public function test_controles_y_revision_impiden_sobrescribir_cambios_de_otra_pantalla(): void
    {
        $this->analista();
        $id = $this->postJson('/api/v1/simulaciones', $this->parametros())->json('id');
        $ruta = '/api/v1/simulaciones/'.$id.'/accion';
        $this->postJson($ruta, ['accion' => 'pausar', 'revision' => 0, 'posicion' => 0])->assertStatus(409);
        $this->postJson($ruta, ['accion' => 'iniciar', 'revision' => 0, 'posicion' => 0])->assertOk()->assertJsonPath('estado', 'activa')->assertJsonPath('revision', 1);
        $this->postJson($ruta, ['accion' => 'detener', 'revision' => 0, 'posicion' => 1])->assertStatus(409);
        $this->postJson($ruta, ['accion' => 'pausar', 'revision' => 1, 'posicion' => 2])->assertOk()->assertJsonPath('posicion', 2);
        $this->postJson($ruta, ['accion' => 'reiniciar', 'revision' => 2, 'posicion' => 2])->assertOk()->assertJsonPath('posicion', 0)->assertJsonPath('estado', 'lista');
        $this->postJson($ruta, ['accion' => 'iniciar', 'revision' => 3, 'posicion' => 0])->assertOk();
        $this->postJson($ruta, ['accion' => 'finalizar', 'revision' => 4, 'posicion' => 2])->assertStatus(409);
        $this->postJson($ruta, ['accion' => 'finalizar', 'revision' => 4, 'posicion' => 4])->assertOk()->assertJsonPath('estado', 'finalizada');
    }

    public function test_otra_cuenta_no_puede_leer_controlar_ni_publicar_la_sesion(): void
    {
        $this->analista();
        $id = $this->postJson('/api/v1/simulaciones', $this->parametros())->json('id');
        $this->analista();
        $this->getJson('/api/v1/simulaciones/'.$id)->assertNotFound();
        $this->postJson('/api/v1/simulaciones/'.$id.'/accion', ['accion' => 'iniciar', 'revision' => 0, 'posicion' => 0])->assertNotFound();
        $this->postJson('/api/v1/simulaciones/'.$id.'/historial')->assertNotFound();
        $this->get('/simulaciones?sesion='.$id)->assertNotFound();
    }

    public function test_publicacion_es_explicita_simulada_repetible_y_no_altera_observaciones(): void
    {
        $u = $this->analista();
        $r = $this->postJson('/api/v1/simulaciones', $this->parametros())->assertCreated();
        $ruta = '/api/v1/simulaciones/'.$r->json('id').'/historial';
        $this->postJson($ruta)->assertOk()->assertJsonPath('nuevas', 5)->assertJsonPath('futuras', 0);
        $this->postJson($ruta)->assertOk()->assertJsonPath('nuevas', 0)->assertJsonPath('repetidas', 5);
        app(RegistrarLecturas::class)->ejecutar([['punto' => 'santacruz', 'metrica' => 'temperatura', 'valor' => 11,
            'instante' => $r->json('resultado.instantes.0'), 'origen' => 'observado', 'fuente' => 'manual']], $u->id);
        $this->postJson($ruta)->assertOk()->assertJsonPath('nuevas', 0);
        $this->assertDatabaseCount('lecturas_ambientales', 6);
        $this->assertDatabaseHas('lecturas_ambientales', ['origen' => 'observado', 'valor' => 11]);
    }

    public function test_simular_futuro_no_lo_incorpora_al_historial_ni_crea_puntos(): void
    {
        $this->analista();
        $r = $this->postJson('/api/v1/simulaciones', $this->parametros(['inicio' => now()->addDay()->toIso8601ZuluString()]))->assertCreated();
        $this->postJson('/api/v1/simulaciones/'.$r->json('id').'/historial')->assertOk()->assertJsonPath('nuevas', 0)->assertJsonPath('futuras', 5);
        $this->assertDatabaseCount('lecturas_ambientales', 0);
        $this->assertDatabaseCount('puntos_ambientales', 0);
    }

    public function test_un_punto_movido_no_cambia_el_historico_de_la_simulacion(): void
    {
        $this->analista();
        $r = $this->postJson('/api/v1/simulaciones', $this->parametros())->assertCreated();
        $this->postJson('/api/v1/simulaciones/'.$r->json('id').'/historial')->assertOk();
        PuntoAmbiental::where('codigo', 'santacruz')->update(['latitud' => -18]);
        $this->postJson('/api/v1/simulaciones/'.$r->json('id').'/historial')->assertStatus(409);
        $this->assertDatabaseCount('lecturas_ambientales', 5);
    }

    public function test_pantalla_tiene_acceso_propio_y_no_muestra_funciones_vacias(): void
    {
        $u = User::factory()->create(['rol' => 'analista']);
        $this->actingAs($u);
        $this->get('/simulaciones')->assertOk()->assertSee('Simulaciones')->assertSee('Zonas a comparar')->assertSee('Calidad del aire')->assertDontSee('en preparación');
        $sesion = app(SimularEscenario::class)->crear($this->parametros(), $u->id);
        $this->get('/simulaciones?sesion='.$sesion->id)->assertOk()->assertSee('Reproducir')->assertSee('Reiniciar')->assertSee('Guardar lecturas pasadas');
        $this->assertDatabaseCount('simulaciones', 1);
    }
}
