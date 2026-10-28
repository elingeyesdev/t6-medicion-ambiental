<?php

namespace Tests\Feature;

use App\Models\PuntoAmbiental;
use App\Models\User;
use App\Services\Datos\RegistrarLecturas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MapaMonitoreoTest extends TestCase
{
    use RefreshDatabase;

    private function punto(array $datos = []): PuntoAmbiental
    {
        return PuntoAmbiental::create($datos + ['codigo' => 'campus', 'nombre' => 'Campus', 'pais' => 'BO', 'latitud' => -17.78, 'longitud' => -63.18, 'tipo' => 'manual']);
    }

    private function lectura(array $datos = []): void
    {
        app(RegistrarLecturas::class)->ejecutar([$datos + [
            'punto' => 'campus', 'metrica' => 'temperatura', 'valor' => 0,
            'instante' => now()->subHour()->startOfHour()->toIso8601ZuluString(), 'origen' => 'observado', 'fuente' => 'manual',
        ]]);
    }

    public function test_mapa_principal_no_se_sustituye_por_la_pantalla_de_focos(): void
    {
        $this->actingAs(User::factory()->create())->get('/mapa')->assertOk()
            ->assertSee('id="mapa-monitoreo"', false)->assertDontSee('id="mapa-focos"', false)
            ->assertSee('Monitoreo ambiental')->assertSee('País o territorio')->assertSee('Humedad')->assertSee('Ruido')
            ->assertSee('Calidad del aire')->assertSee('Índice de agua')->assertSee('Registros y estimaciones')
            ->assertSee(route('modulos.mostrar', 'focos-calor'), false)->assertSee(route('modulos.mostrar', 'sequias'), false)
            ->assertSee('id="mon-detalle"', false)->assertSee('Cerrar punto seleccionado')
            ->assertDontSee('en preparación');
        $this->get('/modulos/focos-calor')->assertOk()->assertSee('id="mapa-focos"', false)->assertDontSee('id="mapa-monitoreo"', false);
        Http::assertNothingSent();
    }

    public function test_portada_presenta_la_base_ambiental_antes_de_los_analisis_agregados(): void
    {
        $this->get('/')->assertOk()->assertSeeInOrder(['Mapa de Monitoreo', 'Mediciones e historial', 'Análisis del territorio', 'Focos de calor'])
            ->assertSee('Santa Cruz, Bolivia')->assertDontSee('cobertura completa');
    }

    public function test_consulta_vacia_no_importa_catalogos_ni_simula_datos_automaticamente(): void
    {
        $this->getJson('/mapa/puntos')->assertUnauthorized();
        $this->getJson('/api/v1/mapa/puntos')->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson('/mapa/puntos')->assertOk()
            ->assertJsonPath('datos', [])->assertJsonPath('total', 0)->assertJsonPath('origen', 'sin-simulaciones')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertDatabaseCount('puntos_ambientales', 0);
        $this->assertDatabaseCount('lecturas_ambientales', 0);
        Http::assertNothingSent();
    }

    public function test_filtro_de_pais_no_inventa_lecturas_y_excluye_puntos_archivados(): void
    {
        $this->punto();
        $this->punto(['codigo' => 'baires', 'pais' => 'AR']);
        $archivado = $this->punto(['codigo' => 'archivado']);
        $archivado->forceFill(['activo' => false])->save();
        $this->actingAs(User::factory()->create())->getJson('/mapa/puntos?pais=BO')->assertOk()
            ->assertJsonPath('total', 1)->assertJsonPath('datos.0.codigo', 'campus')->assertJsonPath('datos.0.lecturas', []);
        Http::assertNothingSent();
    }

    public function test_ultima_lectura_del_periodo_se_conserva_por_fuente_y_origen_sin_promediar(): void
    {
        $this->punto();
        $this->lectura(['valor' => 10, 'instante' => now()->subHours(4)->toIso8601ZuluString()]);
        $this->lectura(['valor' => 20, 'instante' => now()->subHours(3)->toIso8601ZuluString()]);
        $this->lectura(['valor' => 30, 'instante' => now()->subHour()->toIso8601ZuluString()]);
        $this->lectura(['valor' => 12, 'origen' => 'estimado', 'fuente' => 'modelo-a', 'instante' => now()->subHours(3)->toIso8601ZuluString()]);
        $this->lectura(['valor' => 15, 'origen' => 'estimado', 'fuente' => 'modelo-b', 'instante' => now()->subHours(3)->toIso8601ZuluString()]);
        $this->lectura(['metrica' => 'humedad', 'valor' => 90]);
        $hasta = now()->subHours(2)->toIso8601ZuluString();
        $respuesta = $this->actingAs(User::factory()->create())->getJson('/mapa/puntos?hasta='.$hasta)->assertOk();
        $series = collect($respuesta->json('datos.0.lecturas'))->keyBy(fn ($l) => $l['origen'].'/'.$l['fuente']);
        $this->assertCount(3, $series);
        $this->assertSame(20, $series['observado/manual']['valor']);
        $this->assertSame(12, $series['estimado/modelo-a']['valor']);
        $this->assertSame(15, $series['estimado/modelo-b']['valor']);
        $respuesta->assertJsonPath('metrica', 'temperatura')->assertJsonPath('unidad', '°C');
    }

    public function test_simulaciones_solo_entran_por_seleccion_explicita_y_cero_no_es_un_faltante(): void
    {
        $this->punto();
        $this->lectura();
        $this->lectura(['origen' => 'simulado', 'valor' => 40]);
        $this->actingAs(User::factory()->create())->getJson('/mapa/puntos')->assertOk()
            ->assertJsonCount(1, 'datos.0.lecturas')->assertJsonPath('datos.0.lecturas.0.valor', 0);
        $this->getJson('/mapa/puntos?origen=simulado')->assertOk()
            ->assertJsonCount(1, 'datos.0.lecturas')->assertJsonPath('datos.0.lecturas.0.valor', 40);
        $this->getJson('/mapa/puntos?origen=todos')->assertOk()->assertJsonCount(2, 'datos.0.lecturas');
    }

    public function test_fechas_incluyen_el_inicio_excluyen_el_final_y_rechazan_mas_de_siete_dias_o_futuro(): void
    {
        $this->punto();
        $desde = now()->subHours(4)->startOfHour();
        $hasta = $desde->copy()->addHours(2);
        $this->lectura(['valor' => -5, 'instante' => $desde->toIso8601ZuluString()]);
        $this->lectura(['valor' => 20, 'instante' => $hasta->toIso8601ZuluString()]);
        $this->actingAs(User::factory()->create())->getJson('/mapa/puntos?'.http_build_query([
            'desde' => $desde->toIso8601ZuluString(), 'hasta' => $hasta->toIso8601ZuluString(),
        ]))->assertOk()->assertJsonPath('datos.0.lecturas.0.valor', -5);
        foreach ([['desde' => $hasta->toIso8601ZuluString(), 'hasta' => $desde->toIso8601ZuluString()],
            ['desde' => now()->subDays(8)->toIso8601ZuluString(), 'hasta' => now()->toIso8601ZuluString()],
            ['hasta' => now()->addDay()->toIso8601ZuluString()], ['desde' => '2026-02-30T00:00:00Z']] as $filtro) {
            $this->getJson('/mapa/puntos?'.http_build_query($filtro))->assertUnprocessable()->assertJsonPath('error.codigo', 'FILTROS_INVALIDOS');
        }
    }

    public function test_api_no_acepta_filtros_desconocidos_repetidos_o_no_validos(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (['pais=XX', 'pais=BO&pais=AR', 'metrica=clave', 'origen=real', 'pagina=0', 'pais[]=BO', 'campo=ignorado', 'pais='] as $consulta) {
            $this->getJson('/mapa/puntos?'.$consulta)->assertUnprocessable()->assertJsonPath('error.codigo', 'FILTROS_INVALIDOS');
        }
        $this->postJson('/mapa/puntos')->assertStatus(405);
        Http::assertNothingSent();
    }

    public function test_paginacion_determinista_no_oculta_que_hay_mas_puntos(): void
    {
        $filas = [];
        for ($i = 0; $i < 251; $i++) {
            $filas[] = ['codigo' => sprintf('p-%03d', $i), 'nombre' => 'Punto '.$i, 'pais' => 'BO', 'latitud' => -17, 'longitud' => -63, 'tipo' => 'manual'];
        }
        DB::table('puntos_ambientales')->insert($filas);
        $this->actingAs(User::factory()->create())->getJson('/mapa/puntos')->assertOk()
            ->assertJsonCount(250, 'datos')->assertJsonPath('total', 251)->assertJsonPath('ultimaPagina', 2);
        $this->getJson('/mapa/puntos?pagina=2')->assertOk()->assertJsonCount(1, 'datos')->assertJsonPath('datos.0.codigo', 'p-250');
    }

    public function test_coordenadas_de_una_lectura_no_se_reescriben_al_mover_el_punto(): void
    {
        $punto = $this->punto();
        $this->lectura();
        $punto->update(['latitud' => -18]);
        $this->actingAs(User::factory()->create())->getJson('/mapa/puntos')->assertOk()
            ->assertJsonPath('datos.0.latitud', -18)->assertJsonPath('datos.0.lecturas.0.latitud', -17.78);
    }

    public function test_movil_comparte_la_consulta_con_token_y_exige_habilidad_de_cuenta(): void
    {
        $this->punto();
        $this->lectura();
        $usuario = User::factory()->create();
        $sinPermiso = $usuario->createToken('Otro cliente', ['otro'])->plainTextToken;
        $this->withToken($sinPermiso)->getJson('/api/v1/mapa/puntos')->assertForbidden();
        $this->app['auth']->forgetGuards();
        $token = $usuario->createToken('Móvil', ['cuenta'])->plainTextToken;
        $this->withToken($token)->getJson('/api/v1/mapa/puntos')->assertOk()->assertJsonPath('datos.0.codigo', 'campus')
            ->assertJsonMissingPath('datos.0.registrado_por')->assertJsonMissingPath('datos.0.id')->assertJsonMissingPath('usuario');
        Http::assertNothingSent();
    }

    public function test_enlaces_anteriores_de_focos_conservan_su_destino(): void
    {
        $this->actingAs(User::factory()->create())->get('/mapa?modo=externo&confianza=alta')
            ->assertRedirect(route('modulos.mostrar', ['modulo' => 'focos-calor', 'modo' => 'externo', 'confianza' => 'alta']));
    }
}
