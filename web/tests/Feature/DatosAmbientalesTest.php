<?php

namespace Tests\Feature;

use App\Enums\RolCuenta;
use App\Models\LecturaAmbiental;
use App\Models\PuntoAmbiental;
use App\Models\User;
use App\Services\Api\ErrorConsulta;
use App\Services\Datos\CapturarTiempo;
use App\Services\Datos\CatalogoAmbiental;
use App\Services\Datos\CercaniaPuntos;
use App\Services\Datos\RegistrarLecturas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DatosAmbientalesTest extends TestCase
{
    use RefreshDatabase;

    private function punto(array $datos = []): PuntoAmbiental
    {
        return PuntoAmbiental::create($datos + ['codigo' => 'campus', 'nombre' => 'Campus de prueba', 'pais' => 'BO', 'latitud' => -17.78, 'longitud' => -63.18, 'tipo' => 'manual']);
    }

    private function fila(array $datos = []): array
    {
        return $datos + ['punto' => 'campus', 'metrica' => 'temperatura', 'valor' => 22.5, 'instante' => now()->subHour()->startOfHour()->toIso8601ZuluString(), 'origen' => 'observado', 'fuente' => 'manual'];
    }

    private function token(User $usuario): static
    {
        $token = $usuario->createToken('Prueba de datos', ['cuenta'])->plainTextToken;
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }

    public function test_catalogo_completo_tiene_procedencia_sin_estaciones_o_lecturas_inventadas(): void
    {
        $catalogo = app(CatalogoAmbiental::class)->leer();
        $this->assertCount(61, $catalogo['puntos']);
        $this->assertCount(5, $catalogo['metricas']);
        $this->assertCount(13, array_unique(array_column($catalogo['puntos'], 'pais')));
        foreach ($catalogo['fuentes'] as $fuente) {
            $this->assertSame($fuente['sha256'], hash_file('sha256', base_path('../'.$fuente['archivo'])));
        }
        $this->artisan('geosur:preparar-puntos')->assertSuccessful();
        $this->artisan('geosur:preparar-puntos')->assertSuccessful();
        $this->assertDatabaseCount('puntos_ambientales', 61);
        $this->assertDatabaseCount('lecturas_ambientales', 0);
        $this->assertSame('referencia', PuntoAmbiental::where('codigo', 'santacruz')->sole()->tipo);
        Http::assertNothingSent();
    }

    public function test_sesion_y_roles_se_comprueban_en_web_y_api(): void
    {
        $this->punto();
        $this->get('/sensores')->assertRedirect('/login');
        $this->getJson('/api/v1/sensores')->assertUnauthorized();
        $lector = User::factory()->create();
        $this->actingAs($lector)->get('/sensores')->assertOk()->assertSee('Mediciones ambientales')->assertDontSee('Agregar punto de monitoreo')->assertDontSee('Registrar una lectura');
        $this->post('/sensores', ['nombre' => 'Sin permiso'])->assertForbidden();
        $this->token($lector)->postJson('/api/v1/sensores/lecturas', ['lecturas' => [$this->fila()]])->assertForbidden();
        $this->token($lector)->getJson('/api/v1/sensores')->assertOk()->assertJsonPath('datos.0.codigo', 'campus');
        $this->assertDatabaseCount('lecturas_ambientales', 0);
    }

    public function test_analista_y_administrador_crean_puntos_y_lecturas_con_permisos_controlados(): void
    {
        foreach ([RolCuenta::Administrador, RolCuenta::Analista] as $i => $rol) {
            $actor = User::factory()->create(['rol' => $rol]);
            $this->token($actor)->postJson('/api/v1/sensores', ['codigo' => 'punto-'.$i, 'nombre' => 'Punto de prueba', 'pais' => 'BO', 'latitud' => -17, 'longitud' => -63])->assertCreated();
            $this->token($actor)->postJson('/api/v1/sensores/lecturas', ['lecturas' => [$this->fila(['punto' => 'punto-'.$i])]])->assertOk()->assertJsonPath('nuevas', 1);
            $this->assertDatabaseHas('lecturas_ambientales', ['registrado_por' => $actor->id]);
        }
    }

    public function test_coordenadas_pais_tipo_y_estado_no_aceptan_datos_arbitrarios(): void
    {
        $actor = User::factory()->create(['rol' => RolCuenta::Analista]);
        foreach ([['latitud' => 91], ['longitud' => -181], ['pais' => 'XX'], ['tipo' => 'real'], ['activo' => false], ['nombre' => []]] as $invalido) {
            $this->token($actor)->postJson('/api/v1/sensores', $invalido + ['codigo' => 'punto', 'nombre' => 'Prueba', 'pais' => 'BO', 'latitud' => 0, 'longitud' => 0])->assertUnprocessable();
        }
        $this->assertDatabaseCount('puntos_ambientales', 0);
    }

    public function test_importacion_repetida_y_fechas_equivalentes_no_duplican_y_conservan_coordenadas(): void
    {
        $punto = $this->punto();
        $instante = now()->subHour()->startOfHour();
        $registrar = app(RegistrarLecturas::class);
        $this->assertSame(['nuevas' => 1, 'repetidas' => 0], $registrar->ejecutar([$this->fila()]));
        $punto->update(['latitud' => -18]);
        $this->assertSame(['nuevas' => 0, 'repetidas' => 1], $registrar->ejecutar([$this->fila(['instante' => $instante->setTimezone('America/La_Paz')->format('Y-m-d\TH:i:sP')])]));
        $this->assertSame(-17.78, LecturaAmbiental::sole()->latitud);
    }

    public function test_un_conflicto_no_sobrescribe_y_revierte_todo_el_lote(): void
    {
        $this->punto();
        $registrar = app(RegistrarLecturas::class);
        $registrar->ejecutar([$this->fila()]);
        try {
            $registrar->ejecutar([$this->fila(['metrica' => 'humedad', 'valor' => 50]), $this->fila(['valor' => 30])]);
            $this->fail('Debía rechazar el conflicto.');
        } catch (ErrorConsulta $e) {
            $this->assertSame(409, $e->estado);
        }
        $this->assertDatabaseCount('lecturas_ambientales', 1);
        $this->assertSame(22.5, LecturaAmbiental::sole()->valor);
    }

    public function test_validacion_del_lote_rechaza_nulos_metricas_limites_futuro_y_campos_ajenos(): void
    {
        $this->punto();
        foreach ([['valor' => null], ['valor' => 61], ['metrica' => 'inventada'], ['instante' => 'fecha'], ['instante' => now()->addHour()->toIso8601ZuluString()], ['origen' => 'real'], ['fuente' => 'https://ejemplo.test/?clave=privada'], ['otro' => 'campo']] as $invalido) {
            try {
                app(RegistrarLecturas::class)->ejecutar([$this->fila(['metrica' => 'humedad', 'valor' => 50]), $this->fila($invalido)]);
                $this->fail('Debía rechazar el lote.');
            } catch (ValidationException|ErrorConsulta $e) {
                $this->assertDatabaseCount('lecturas_ambientales', 0);
            }
        }
    }

    public function test_cero_y_temperatura_negativa_se_conservan(): void
    {
        $this->punto();
        app(RegistrarLecturas::class)->ejecutar([$this->fila(['valor' => -12]), $this->fila(['metrica' => 'ruido', 'valor' => 0])]);
        $this->assertDatabaseHas('lecturas_ambientales', ['metrica' => 'ruido', 'valor' => 0]);
        $this->assertDatabaseHas('lecturas_ambientales', ['metrica' => 'temperatura', 'valor' => -12]);
    }

    public function test_resumen_y_horas_no_mezclan_origenes_ni_fuentes(): void
    {
        $this->punto();
        app(RegistrarLecturas::class)->ejecutar([$this->fila(['valor' => 10]), $this->fila(['origen' => 'estimado', 'valor' => 20]), $this->fila(['origen' => 'simulado', 'valor' => 40]), $this->fila(['fuente' => 'otra', 'valor' => 30])]);
        $this->token(User::factory()->create())->getJson('/api/v1/sensores/campus/lecturas?metrica=temperatura')->assertOk()->assertJsonCount(4, 'resumen')->assertJsonCount(4, 'horas')->assertJsonCount(4, 'datos')->assertJsonPath('datos.0.unidad', '°C');
        $this->getJson('/api/v1/sensores/campus/lecturas?origen=estimado')->assertOk()->assertJsonCount(1, 'resumen')->assertJsonPath('resumen.0.media', 20);
        $this->actingAs(User::factory()->create())->get('/sensores?punto=campus')->assertOk()->assertSee('Esta consulta contiene datos simulados.')->assertSee('no son mediciones de una estación')->assertSee('Promedios por hora');
    }

    public function test_fechas_incluyen_inicio_excluyen_final_y_paginan_sin_perder_registros(): void
    {
        $this->punto();
        $inicio = now()->subDay()->startOfHour();
        $filas = [];
        for ($i = 0; $i < 51; $i++) {
            $filas[] = $this->fila(['instante' => $inicio->copy()->addMinutes($i)->toIso8601ZuluString()]);
        }
        app(RegistrarLecturas::class)->ejecutar($filas);
        $this->token(User::factory()->create())->getJson('/api/v1/sensores/campus/lecturas')->assertOk()->assertJsonPath('total', 51)->assertJsonPath('ultimaPagina', 2)->assertJsonCount(50, 'datos');
        $this->getJson('/api/v1/sensores/campus/lecturas?pagina=2')->assertOk()->assertJsonCount(1, 'datos');
        $query = http_build_query(['desde' => $inicio->toIso8601ZuluString(), 'hasta' => $inicio->copy()->addMinute()->toIso8601ZuluString()]);
        $this->getJson('/api/v1/sensores/campus/lecturas?'.$query)->assertOk()->assertJsonPath('total', 1);
    }

    public function test_filtros_desconocidos_repetidos_futuros_y_periodos_largos_son_rechazados(): void
    {
        $this->punto();
        $this->token(User::factory()->create());
        foreach (['otro=x', 'metrica=temperatura&metrica=humedad', 'pagina=0', 'origen=real', http_build_query(['hasta' => now()->addHour()->toIso8601ZuluString()]), http_build_query(['desde' => now()->subDays(8)->toIso8601ZuluString(), 'hasta' => now()->toIso8601ZuluString()])] as $query) {
            $this->getJson('/api/v1/sensores/campus/lecturas?'.$query)->assertUnprocessable();
        }
    }

    public function test_archivar_conserva_historial_impide_escritura_y_permite_reactivar(): void
    {
        $this->punto();
        app(RegistrarLecturas::class)->ejecutar([$this->fila()]);
        $this->token(User::factory()->create(['rol' => RolCuenta::Analista]))->patchJson('/api/v1/sensores/campus/estado', ['activo' => false])->assertOk();
        $this->postJson('/api/v1/sensores/lecturas', ['lecturas' => [$this->fila(['valor' => 25])]])->assertUnprocessable();
        $this->getJson('/api/v1/sensores')->assertOk()->assertJsonPath('total', 0);
        $this->getJson('/api/v1/sensores/campus/lecturas')->assertOk()->assertJsonPath('total', 1);
        $this->patchJson('/api/v1/sensores/campus/estado', ['activo' => true])->assertOk();
        $this->getJson('/api/v1/sensores')->assertOk()->assertJsonPath('total', 1);
    }

    public function test_cercania_no_inventa_datos_y_funciona_en_antimeridiano(): void
    {
        $this->punto(['latitud' => 0, 'longitud' => 179.99]);
        $this->token(User::factory()->create())->getJson('/api/v1/sensores/cercanos?latitud=0&longitud=-179.99&radioKm=10')->assertOk()->assertJsonPath('datos.0.codigo', 'campus')->assertJsonMissingPath('datos.0.valor');
        $this->getJson('/api/v1/sensores/cercanos?latitud=90&longitud=0')->assertOk()->assertJsonCount(0, 'datos');
        $this->getJson('/api/v1/sensores/cercanos?latitud=91&longitud=0')->assertUnprocessable();
        $this->assertEqualsWithDelta(2.224, CercaniaPuntos::distancia(0, 179.99, 0, -179.99), 0.01);
    }

    public function test_csv_web_es_atomico_repetible_y_rechaza_archivo_mal_formado(): void
    {
        $this->punto();
        $actor = User::factory()->create(['rol' => RolCuenta::Analista]);
        $fila = implode(',', array_values($this->fila()));
        $contenido = "punto,metrica,valor,instante,origen,fuente\n$fila\n";
        $this->actingAs($actor)->post('/sensores/importar', ['archivo' => UploadedFile::fake()->createWithContent('lecturas.csv', $contenido)])->assertSessionHasNoErrors();
        $this->post('/sensores/importar', ['archivo' => UploadedFile::fake()->createWithContent('lecturas.csv', $contenido)])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('lecturas_ambientales', 1);
        $this->post('/sensores/importar', ['archivo' => UploadedFile::fake()->createWithContent('lecturas.csv', 'otra,cabecera')])->assertSessionHasErrors('archivo');
        $this->assertDatabaseCount('lecturas_ambientales', 1);
    }

    public function test_formularios_exigen_csrf_y_las_fechas_invalidas_no_generan_error_interno(): void
    {
        $this->punto();
        $this->actingAs(User::factory()->create(['rol' => RolCuenta::Analista]))->get('/sensores?desde=fecha')->assertRedirect('/sensores')->assertSessionHasErrors('periodo');
        $this->app->instance('env', 'local');
        $this->post('/sensores', ['nombre' => 'Sin token'])->assertStatus(419);
        $this->post('/sensores/campus/lecturas', [])->assertStatus(419);
    }

    public function test_demo_solo_local_explicita_repetible_y_siempre_simulada(): void
    {
        $this->artisan('geosur:datos-demo')->assertFailed();
        $this->artisan('geosur:datos-demo', ['--confirmar' => true])->assertSuccessful();
        $this->assertDatabaseCount('lecturas_ambientales', 360);
        $this->artisan('geosur:datos-demo', ['--confirmar' => true])->assertSuccessful();
        $this->assertDatabaseCount('lecturas_ambientales', 360);
        $this->assertSame(0, LecturaAmbiental::where('origen', '!=', 'simulado')->count());
        $this->app->instance('env', 'production');
        $this->artisan('geosur:datos-demo', ['--confirmar' => true])->assertFailed();
        Http::assertNothingSent();
    }

    public function test_proveedor_no_se_conecta_por_defecto_ni_desde_una_consulta_get(): void
    {
        $punto = $this->punto();
        $this->artisan('geosur:capturar-tiempo', ['punto' => 'campus'])->assertFailed();
        $this->artisan('geosur:capturar-tiempo', ['punto' => 'campus', '--consultar-proveedor' => true])->assertFailed();
        $this->token(User::factory()->create())->getJson('/api/v1/sensores/campus/lecturas')->assertOk();
        Http::assertNothingSent();
    }

    public function test_captura_valida_guarda_modelo_como_estimacion_y_es_repetible(): void
    {
        config(['datos.tiempo_habilitado' => true]);
        $punto = $this->punto();
        Http::fake(['api.open-meteo.com/*' => Http::response(['utc_offset_seconds' => 0, 'current' => ['time' => now()->subMinutes(15)->timestamp, 'temperature_2m' => 0, 'relative_humidity_2m' => 70], 'current_units' => ['temperature_2m' => '°C', 'relative_humidity_2m' => '%']])]);
        $capturar = app(CapturarTiempo::class);
        $this->assertSame(2, $capturar->ejecutar($punto)['nuevas']);
        $this->assertSame(2, $capturar->ejecutar($punto)['repetidas']);
        $this->assertDatabaseHas('lecturas_ambientales', ['valor' => 0, 'origen' => 'estimado', 'fuente' => 'open-meteo-modelo']);
        Http::assertSent(fn ($r) => $r['timezone'] === 'UTC' && $r['timeformat'] === 'unixtime');
    }

    public function test_fallo_unidades_incompatibles_y_fecha_vieja_no_generan_datos_aleatorios(): void
    {
        config(['datos.tiempo_habilitado' => true]);
        $punto = $this->punto();
        foreach ([['utc_offset_seconds' => 0, 'current' => ['time' => now()->subDay()->timestamp]], ['utc_offset_seconds' => 0, 'current' => ['time' => now()->timestamp, 'temperature_2m' => 70], 'current_units' => ['temperature_2m' => '°F']], ['error' => true]] as $json) {
            Http::fake(['api.open-meteo.com/*' => Http::response($json)]);
            try {
                app(CapturarTiempo::class)->ejecutar($punto);
                $this->fail('No debía aceptar la captura.');
            } catch (ErrorConsulta $e) {
                $this->assertSame('PROVEEDOR_NO_DISPONIBLE', $e->codigo);
            }
        }
        $this->assertDatabaseCount('lecturas_ambientales', 0);
    }
}
