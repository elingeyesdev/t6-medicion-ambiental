<?php

namespace Tests\Feature;

use App\Enums\RolCuenta;
use App\Models\LecturaAmbiental;
use App\Models\PuntoAmbiental;
use App\Models\User;
use App\Services\Api\ErrorConsulta;
use App\Services\Datos\ImportarHistorial;
use App\Services\Datos\Mqtt\ConfiguracionMqtt;
use App\Services\Datos\Mqtt\IngerirMensajeMqtt;
use App\Services\Datos\ProcesarBandejaDatos;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class IngestionDatosTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private string $carpeta;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->actor = User::factory()->create(['rol' => RolCuenta::Analista]);
        PuntoAmbiental::create(['codigo' => 'campus', 'nombre' => 'Prueba local', 'pais' => 'BO', 'latitud' => -17.78, 'longitud' => -63.18, 'tipo' => 'manual']);
        $this->carpeta = base_path('../tmp/pruebas-datos/'.Str::uuid());
        mkdir($this->carpeta, 0777, true);
        config(['datos.mqtt.temas' => ['geosur/ejemplo/temperatura' => ['punto' => 'campus', 'metrica' => 'temperatura', 'unidad' => '°C', 'origen' => 'observado', 'fuente' => 'mqtt-configurado']]]);
    }

    protected function tearDown(): void
    {
        if (isset($this->carpeta)) {
            foreach (glob($this->carpeta.'/{*,.*}', GLOB_BRACE) as $archivo) {
                if (is_file($archivo)) {
                    unlink($archivo);
                }
            }
            rmdir($this->carpeta);
        }
        parent::tearDown();
    }

    private function fila(array $cambios = []): array
    {
        return $cambios + ['tiempo' => '2026-10-27T12:00:00Z', 'localidad' => 'campus', 'metrica' => 'temperatura', 'valor' => 25.5, 'unidad' => '°C', 'origen' => 'simulado', 'fuente' => 'ejemplo-local'];
    }

    private function documento(?array $filas = null): string
    {
        return json_encode(['formato' => 'geosur.historial.v1', 'lecturas' => $filas ?? [$this->fila()]], JSON_THROW_ON_ERROR);
    }

    private function archivo(string $contenido, string $nombre = 'historial.json'): string
    {
        $ruta = $this->carpeta.'/'.$nombre;
        file_put_contents($ruta, $contenido);

        return $ruta;
    }

    public function test_historial_con_cinco_variables_se_consulta_desde_pantalla_existente_y_repetir_no_duplica(): void
    {
        $filas = [$this->fila()];
        foreach (['humedad' => ['%', 65], 'aqi' => ['AQI', 32], 'ica' => ['ICA', 80], 'ruido' => ['dB', 40]] as $metrica => [$unidad, $valor]) {
            $filas[] = $this->fila(compact('metrica', 'unidad', 'valor'));
        }
        $importar = app(ImportarHistorial::class);
        $archivo = $this->archivo($this->documento($filas));
        $this->assertSame(['nuevas' => 5, 'repetidas' => 0], $importar->ejecutar($archivo, $this->actor->id));
        $this->assertSame(['nuevas' => 0, 'repetidas' => 5], $importar->ejecutar($archivo, $this->actor->id));
        $this->assertDatabaseHas('lecturas_ambientales', ['registrado_por' => $this->actor->id, 'origen' => 'simulado']);
        $this->actingAs($this->actor)->get('/sensores?punto=campus&desde=2026-10-27T00:00&hasta=2026-10-28T00:00')->assertOk()->assertSee('25.5')->assertSee('ejemplo-local')->assertSee('Historial saneado JSON');
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_formulario_historial_usa_misma_ruta_exige_revision_y_no_muestra_cargas_a_visualizador(): void
    {
        $subir = fn () => UploadedFile::fake()->createWithContent('historial.json', $this->documento());
        $this->actingAs($this->actor)->post('/sensores/importar', ['formato' => 'historial', 'archivo' => $subir()])->assertSessionHasErrors('saneado');
        $this->assertDatabaseCount('lecturas_ambientales', 0);
        $this->post('/sensores/importar', ['formato' => 'historial', 'saneado' => '1', 'archivo' => $subir()])->assertSessionHasNoErrors()->assertSessionHas('estado');
        $this->actingAs(User::factory()->create())->get('/sensores')->assertOk()->assertDontSee('Historial saneado JSON');
        $this->post('/sensores/importar', ['formato' => 'historial', 'saneado' => '1', 'archivo' => $subir()])->assertForbidden();
        $this->assertDatabaseCount('lecturas_ambientales', 1);
    }

    public function test_comprobar_ejerce_conflictos_y_validacion_sin_guardar_lecturas_ni_alertas(): void
    {
        $archivo = $this->archivo($this->documento([$this->fila(['metrica' => 'ruido', 'unidad' => 'dB', 'valor' => 130])]));
        $this->assertSame(['nuevas' => 1, 'repetidas' => 0], app(ImportarHistorial::class)->ejecutar($archivo, $this->actor->id, true));
        $this->assertDatabaseCount('lecturas_ambientales', 0);
        $this->assertDatabaseCount('alertas_ambientales', 0);
        $this->assertDatabaseCount('avisos_ambientales', 0);
        $this->artisan('geosur:importar-historial', ['archivo' => $archivo, '--actor' => $this->actor->id, '--confirmar-saneado' => true, '--comprobar' => true])->assertSuccessful();
        $this->assertDatabaseCount('lecturas_ambientales', 0);
    }

    public function test_todo_el_lote_se_revierte_ante_conflicto_duplicado_interno_y_punto_archivado(): void
    {
        $importar = app(ImportarHistorial::class);
        foreach ([[$this->fila(), $this->fila(['valor' => 30])], [$this->fila(), $this->fila(['localidad' => 'ausente'])]] as $filas) {
            try {
                $importar->contenido($this->documento($filas), $this->actor->id);
                $this->fail('Debió rechazar el lote completo.');
            } catch (ErrorConsulta|ValidationException) {
                $this->assertDatabaseCount('lecturas_ambientales', 0);
            }
        }
        PuntoAmbiental::where('codigo', 'campus')->update(['activo' => false]);
        $this->expectException(ErrorConsulta::class);
        $importar->contenido($this->documento(), $this->actor->id);
    }

    public function test_no_sobrescribe_un_conflicto_persistido_incluso_al_comprobar(): void
    {
        $importar = app(ImportarHistorial::class);
        $importar->contenido($this->documento(), $this->actor->id);
        foreach ([false, true] as $comprobar) {
            try {
                $importar->contenido($this->documento([$this->fila(['metrica' => 'humedad', 'unidad' => '%', 'valor' => 60]), $this->fila(['valor' => 26])]), $this->actor->id, $comprobar);
                $this->fail('Debió conservar el valor anterior.');
            } catch (ErrorConsulta $e) {
                $this->assertSame(409, $e->estado);
                $this->assertDatabaseCount('lecturas_ambientales', 1);
                $this->assertSame(25.5, LecturaAmbiental::sole()->valor);
            }
        }
    }

    public function test_contrato_rechaza_datos_privados_faltantes_unidades_y_fechas_invalidas(): void
    {
        $invalidos = [['localidad_id' => 1], ['cuenta' => 'ajena'], ['unidad' => 'K'], ['metrica' => 'aqi', 'unidad' => 'european_aqi'], ['valor' => null], ['valor' => 61], ['fuente' => 'https://privado.test/clave'], ['tiempo' => '2026-02-30T12:00:00Z'], ['tiempo' => '2026-10-27 12:00:00'], ['tiempo' => now()->addDay()->toIso8601ZuluString()]];
        foreach ($invalidos as $cambio) {
            try {
                app(ImportarHistorial::class)->contenido($this->documento([$this->fila(), $this->fila($cambio)]), $this->actor->id);
                $this->fail('Debió rechazar el documento inválido.');
            } catch (ErrorConsulta|ValidationException) {
                $this->assertDatabaseCount('lecturas_ambientales', 0);
            }
        }
        $fila = $this->fila();
        unset($fila['origen']);
        $this->expectException(ValidationException::class);
        app(ImportarHistorial::class)->contenido($this->documento([$fila]), $this->actor->id);
    }

    public function test_archivo_ausente_excesivo_json_roto_y_version_desconocida_no_importan(): void
    {
        foreach (['', '{', '{"formato":"v2","lecturas":[]}', $this->documento([]), str_repeat(' ', 2 * 1024 * 1024 + 1)] as $contenido) {
            try {
                app(ImportarHistorial::class)->ejecutar($this->archivo($contenido), $this->actor->id);
                $this->fail('No debía importar.');
            } catch (ErrorConsulta|ValidationException) {
                $this->assertDatabaseCount('lecturas_ambientales', 0);
            }
        }
        $this->expectException(ErrorConsulta::class);
        app(ImportarHistorial::class)->ejecutar($this->carpeta.'/ausente.json', $this->actor->id);
    }

    public function test_limite_de_1000_no_permite_guardado_parcial(): void
    {
        $this->expectException(ValidationException::class);
        app(ImportarHistorial::class)->contenido($this->documento(array_fill(0, 1001, $this->fila())), $this->actor->id);
    }

    public function test_consola_exige_actor_autorizado_y_confirmacion_sin_crear_cuentas(): void
    {
        $archivo = $this->archivo($this->documento());
        $cuentas = User::count();
        $this->artisan('geosur:importar-historial', ['archivo' => $archivo])->assertExitCode(2);
        $this->artisan('geosur:importar-historial', ['archivo' => $archivo, '--actor' => $this->actor->id])->assertExitCode(2);
        foreach ([User::factory()->create(), User::factory()->create(['rol' => RolCuenta::Administrador, 'activo' => false])] as $usuario) {
            $this->artisan('geosur:importar-historial', ['archivo' => $archivo, '--actor' => $usuario->id, '--confirmar-saneado' => true])->assertExitCode(2);
        }
        $this->assertDatabaseCount('lecturas_ambientales', 0);
        $this->artisan('geosur:importar-historial', ['archivo' => $archivo, '--actor' => $this->actor->id, '--confirmar-saneado' => true])->assertSuccessful();
        $this->assertDatabaseCount('users', $cuentas + 2);
    }

    public function test_worker_se_arranca_explicitamente_repite_y_conserva_archivos(): void
    {
        $archivo = $this->archivo($this->documento());
        $this->artisan('geosur:datos-worker', ['directorio' => $this->carpeta, '--actor' => $this->actor->id])->assertExitCode(2);
        $this->artisan('geosur:datos-worker', ['directorio' => $this->carpeta, '--actor' => $this->actor->id, '--ejecutar' => true, '--daemon' => true, '--intervalo' => 1, '--ciclos' => 2])->assertSuccessful();
        $this->assertFileExists($archivo);
        $this->assertDatabaseCount('lecturas_ambientales', 1);
        $this->app->instance('env', 'production');
        $this->artisan('geosur:datos-worker', ['directorio' => $this->carpeta, '--actor' => $this->actor->id, '--ejecutar' => true])->assertExitCode(2);
    }

    public function test_worker_respeta_bloqueo_y_falla_sin_borrar_el_archivo_invalido(): void
    {
        $cerrojo = fopen($this->carpeta.'/.geosur-worker.lock', 'c');
        flock($cerrojo, LOCK_EX);
        try {
            app(ProcesarBandejaDatos::class)->ejecutar($this->carpeta, $this->actor->id);
            $this->fail('Debió respetar el cerrojo.');
        } catch (ErrorConsulta $e) {
            $this->assertSame('BANDEJA_OCUPADA', $e->codigo);
        } finally {
            flock($cerrojo, LOCK_UN);
            fclose($cerrojo);
        }
        $archivo = $this->archivo('{');
        $this->artisan('geosur:datos-worker', ['directorio' => $this->carpeta, '--actor' => $this->actor->id, '--ejecutar' => true])->assertExitCode(2);
        $this->assertFileExists($archivo);
        $this->assertDatabaseCount('lecturas_ambientales', 0);
    }

    public function test_permisos_se_vuelven_a_leer_entre_mensajes_y_ciclos(): void
    {
        $ingestion = app(IngerirMensajeMqtt::class);
        $worker = app(ProcesarBandejaDatos::class);
        $ingestion->ejecutar('geosur/ejemplo/temperatura', '{"valor":20,"instante":"2026-10-27T12:00:00Z"}', $this->actor->id);
        $this->assertSame(0, $worker->ejecutar($this->carpeta, $this->actor->id)['archivos']);
        $this->actor->forceFill(['rol' => RolCuenta::Visualizador])->save();
        try {
            $worker->ejecutar($this->carpeta, $this->actor->id);
            $this->fail('El siguiente ciclo debe releer los permisos.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('lecturas_ambientales', 1);
        }
        $this->expectException(AuthorizationException::class);
        $ingestion->ejecutar('geosur/ejemplo/temperatura', '{"valor":20,"instante":"2026-10-27T13:00:00Z"}', $this->actor->id);
    }

    public function test_reproduccion_mqtt_no_abre_conexion_y_siempre_es_simulada(): void
    {
        $archivo = $this->archivo(json_encode(['formato' => 'geosur.mqtt.v1', 'mensajes' => [['tema' => 'geosur/ejemplo/temperatura', 'contenido' => ['valor' => 0, 'instante' => '2026-10-27T08:00:00-04:00']]]], JSON_THROW_ON_ERROR));
        $this->artisan('geosur:mqtt-reproducir', ['archivo' => $archivo, '--actor' => $this->actor->id])->assertExitCode(2);
        foreach ([1, 2] as $vez) {
            $this->artisan('geosur:mqtt-reproducir', ['archivo' => $archivo, '--actor' => $this->actor->id, '--ejecutar' => true])->assertSuccessful();
        }
        $this->assertDatabaseCount('lecturas_ambientales', 1);
        $this->assertDatabaseHas('lecturas_ambientales', ['valor' => 0, 'origen' => 'simulado', 'fuente' => 'mqtt-ejemplo-local', 'instante' => '2026-10-27 12:00:00']);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_mqtt_rechaza_numeros_crudos_campos_del_destino_temas_ajenos_y_fechas_faltantes(): void
    {
        foreach ([['otro', '{"valor":20,"instante":"2026-10-27T12:00:00Z"}'], ['geosur/ejemplo/temperatura', '12gas'], ['geosur/ejemplo/temperatura', '{"valor":20}'], ['geosur/ejemplo/temperatura', '{"valor":20,"instante":"2026-10-27T12:00:00Z","punto":"otro"}'], ['geosur/ejemplo/temperatura', str_repeat('0', 4097)]] as [$tema, $contenido]) {
            try {
                app(IngerirMensajeMqtt::class)->ejecutar($tema, $contenido, $this->actor->id);
                $this->fail('Debió rechazar el mensaje.');
            } catch (ErrorConsulta|ValidationException) {
                $this->assertDatabaseCount('lecturas_ambientales', 0);
            }
        }
    }

    public function test_mqtt_configuracion_no_permite_credenciales_en_url_comodines_o_tcp_remoto(): void
    {
        $this->artisan('geosur:mqtt-recibir')->assertExitCode(2);
        $this->artisan('geosur:mqtt-recibir', ['--actor' => $this->actor->id, '--conectar' => true])->assertExitCode(2);
        config(['datos.mqtt.habilitado' => true]);
        foreach (['mqtt://remoto.test:1883', 'mqtts://usuario:secreto@remoto.test:8883', 'https://remoto.test:8883', 'mqtts://remoto.test:8883/ruta'] as $url) {
            config(['datos.mqtt.url' => $url]);
            try {
                app(ConfiguracionMqtt::class)->conexion();
                $this->fail('Debió rechazar la dirección.');
            } catch (ErrorConsulta $e) {
                $this->assertStringNotContainsString('secreto', $e->getMessage());
            }
        }
        config(['datos.mqtt.temas' => ['geosur/#' => []]]);
        $this->expectException(ErrorConsulta::class);
        app(ConfiguracionMqtt::class)->temas();
    }

    public function test_csv_por_consola_exige_permiso_y_deja_trazabilidad(): void
    {
        $archivo = $this->archivo("punto,metrica,valor,instante,origen,fuente\ncampus,temperatura,20,2026-10-27T12:00:00Z,simulado,ejemplo-local\n", 'lecturas.csv');
        $this->artisan('geosur:importar-lecturas', ['archivo' => $archivo])->assertExitCode(2);
        $this->artisan('geosur:importar-lecturas', ['archivo' => $archivo, '--actor' => $this->actor->id])->assertSuccessful();
        $this->assertDatabaseHas('lecturas_ambientales', ['registrado_por' => $this->actor->id]);
    }
}
