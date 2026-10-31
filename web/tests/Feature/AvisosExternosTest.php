<?php

namespace Tests\Feature;

use App\Models\AvisoAmbiental;
use App\Models\EnvioAviso;
use App\Models\PreferenciasAvisos;
use App\Models\PuntoAmbiental;
use App\Models\SuscripcionCanalAviso;
use App\Models\User;
use App\Services\Cuentas\GuardarCanalesAvisos;
use App\Services\Datos\RegistrarLecturas;
use App\Services\Notificaciones\EncolarAvisos;
use App\Services\Notificaciones\ProcesarOutboxAvisos;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AvisosExternosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        // Se prueba exactamente el snippet entregado, sin modificar rutas compartidas.
        Route::middleware('web')->group(base_path('../docs/integracion/m3-avisos-rutas.php'));
        Route::getRoutes()->refreshNameLookups();
        app(ExceptionHandler::class)->renderable(require base_path('../docs/integracion/m3-avisos-excepciones.php'));
        config(['avisos.procesar' => true, 'avisos.email.habilitado' => true, 'avisos.email.mailer' => 'log',
            'avisos.telegram.habilitado' => true, 'avisos.telegram.token' => '123456:token_controlado',
            'avisos.whatsapp.habilitado' => true, 'avisos.whatsapp.token' => 'token_controlado',
            'avisos.whatsapp.telefono_id' => '123456', 'avisos.whatsapp.version' => 'v26.0', 'avisos.whatsapp.plantilla' => 'alerta_ambiental']);
        PuntoAmbiental::create(['codigo' => 'campus', 'nombre' => 'Campus', 'pais' => 'BO', 'latitud' => -17.8, 'longitud' => -63.2, 'tipo' => 'manual']);
    }

    private function usuario(array $canales = ['telegram'], array $cambios = []): User
    {
        $u = User::factory()->create($cambios);
        $u->preferencias()->create(['latitud' => -17.8, 'longitud' => -63.2]);
        PreferenciasAvisos::create(['usuario_id' => $u->id, 'activos' => true, 'simulados' => false, 'metricas' => ['temperatura']]);
        app(GuardarCanalesAvisos::class)->ejecutar($u, $this->opciones($canales));

        return $u;
    }

    private function opciones(array $canales = ['telegram']): array
    {
        return ['notif_email' => in_array('email', $canales), 'notif_telegram' => in_array('telegram', $canales),
            'notif_whatsapp' => in_array('whatsapp', $canales), 'telegram_destino' => '123456789',
            'whatsapp_destino' => '+59170000000', 'consentimiento' => '1'];
    }

    private function alerta(array $cambios = []): void
    {
        app(RegistrarLecturas::class)->ejecutar([$cambios + ['punto' => 'campus', 'metrica' => 'temperatura', 'valor' => 45,
            'instante' => now()->subMinute()->toIso8601ZuluString(), 'origen' => 'observado', 'fuente' => 'manual']]);
    }

    private function procesar(): int
    {
        return app(ProcesarOutboxAvisos::class)->ejecutar();
    }

    public function test_encolar_es_transaccional_no_envia_y_deduplica_por_aviso_y_canal(): void
    {
        $u = $this->usuario(['telegram', 'whatsapp']);
        $this->alerta();
        $this->assertDatabaseCount('envios_avisos', 2);
        app(EncolarAvisos::class)->ejecutar(AvisoAmbiental::firstOrFail(), $u);
        $this->assertDatabaseCount('envios_avisos', 2);
        Http::assertNothingSent();
        $this->assertStringNotContainsString('123456789', EnvioAviso::first()->toJson());
        $this->travel(3)->minutes();
        DB::beginTransaction();
        $this->alerta(['valor' => 0]);
        $this->alerta(['instante' => now()->toIso8601ZuluString()]);
        $this->assertDatabaseCount('envios_avisos', 4);
        DB::rollBack();
        $this->assertDatabaseCount('envios_avisos', 2);
    }

    public function test_flags_anteriores_sin_consentimiento_no_autorizan_mensajes(): void
    {
        $u = $this->usuario();
        SuscripcionCanalAviso::where('usuario_id', $u->id)->delete();
        $this->alerta();
        $this->assertDatabaseCount('avisos_ambientales', 1);
        $this->assertDatabaseCount('envios_avisos', 0);
        Http::assertNothingSent();
    }

    public function test_telegram_envia_el_contrato_real_sin_parsear_html_y_sin_duplicar(): void
    {
        $this->usuario();
        $this->alerta();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 12]])]);
        $this->assertSame(1, $this->procesar());
        $this->assertSame(0, $this->procesar());
        Http::assertSent(fn ($r) => $r->method() === 'POST' && $r->url() === 'https://api.telegram.org/bot123456:token_controlado/sendMessage' &&
            $r['chat_id'] === '123456789' && str_contains($r['text'], 'Campus') && str_contains($r['text'], '45') && ! isset($r['parse_mode']));
        Http::assertSentCount(1);
        $this->assertDatabaseHas('envios_avisos', ['estado' => 'aceptado', 'intentos' => 1, 'error_codigo' => null]);
    }

    public function test_whatsapp_usa_plantilla_y_bearer_sin_sesion_personal(): void
    {
        $this->usuario(['whatsapp']);
        $this->alerta(['origen' => 'observado']);
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.controlado']]])]);
        $this->procesar();
        Http::assertSent(fn ($r) => $r->url() === 'https://graph.facebook.com/v26.0/123456/messages' &&
            $r->hasHeader('Authorization', 'Bearer token_controlado') && $r['to'] === '59170000000' &&
            $r['messaging_product'] === 'whatsapp' && $r['type'] === 'template' && $r['template']['name'] === 'alerta_ambiental' &&
            count($r['template']['components'][0]['parameters']) === 4 && $r['template']['components'][0]['parameters'][0]['text'] === 'Observación');
        $this->assertDatabaseHas('envios_avisos', ['estado' => 'aceptado']);
    }

    public function test_correo_log_se_registra_localmente_y_no_se_presenta_como_recibido(): void
    {
        Event::fake([MessageSent::class]);
        $u = $this->usuario(['email']);
        $this->alerta();
        $this->procesar();
        Event::assertDispatched(MessageSent::class, fn ($e) => $e->message->getTo()[0]->getAddress() === $u->email && str_contains($e->message->getTextBody(), 'Campus'));
        $this->assertFileExists(storage_path('logs/correo-local.log'));
        $this->assertDatabaseHas('envios_avisos', ['estado' => 'registrado_local']);
        $this->actingAs($u)->get('/avisos')->assertOk()->assertSee('Registrado en correo local')->assertSee('No llegará a tu buzón');
        Http::assertNothingSent();
    }

    public function test_correo_smtp_se_prueba_con_transporte_array_controlado(): void
    {
        config(['avisos.email.mailer' => 'smtp', 'mail.mailers.smtp.transport' => 'array']);
        Event::fake([MessageSent::class]);
        $this->usuario(['email']);
        $this->alerta();
        $this->procesar();
        Event::assertDispatched(MessageSent::class);
        $this->assertDatabaseHas('envios_avisos', ['estado' => 'aceptado']);
        Http::assertNothingSent();
    }

    public function test_reintento_solo_ante_limite_explicito_y_con_tope_de_tres_intentos(): void
    {
        $this->usuario();
        $this->alerta();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'error_code' => 429], 429)]);
        $this->procesar();
        $this->assertDatabaseHas('envios_avisos', ['estado' => 'reintento', 'intentos' => 1]);
        $this->assertSame(0, $this->procesar());
        $this->travel(1)->minutes();
        $this->procesar();
        $this->travel(2)->minutes();
        $this->procesar();
        $this->assertDatabaseHas('envios_avisos', ['estado' => 'fallido', 'intentos' => 3, 'error_codigo' => 'LIMITE_INTENTOS']);
        Http::assertSentCount(3);
    }

    public function test_rechazos_errores_y_respuestas_vacias_no_se_convierten_en_exitos(): void
    {
        $this->usuario();
        $this->alerta();
        $envio = EnvioAviso::firstOrFail();
        Http::fake(['api.telegram.org/*' => Http::sequence()->push(['ok' => false], 400)
            ->push(['ok' => false, 'description' => 'token y contacto privados'], 200)->push(['description' => 'token privado'], 503)]);
        foreach (['fallido', 'incierto', 'incierto'] as $estado) {
            $envio->refresh()->update(['estado' => 'pendiente', 'disponible_el' => now()]);
            $this->procesar();
            $this->assertSame($estado, $envio->fresh()->estado);
            $this->assertStringNotContainsString('privado', $envio->fresh()->toJson());
            $this->assertSame(0, $this->procesar());
        }
    }

    public function test_timeout_y_proceso_interrumpido_quedan_inciertos_sin_reenvio_automatico(): void
    {
        $this->usuario();
        $this->alerta();
        Http::fake(['api.telegram.org/*' => Http::failedConnection('secreto-que-no-debe-persistir')]);
        $this->procesar();
        $this->assertDatabaseHas('envios_avisos', ['estado' => 'incierto', 'error_codigo' => 'CONEXION_SIN_CONFIRMACION']);
        $this->assertSame(0, $this->procesar());
        EnvioAviso::query()->update(['estado' => 'en_proceso', 'iniciado_el' => now()->subMinutes(3)]);
        $this->assertSame(0, $this->procesar());
        $this->assertDatabaseHas('envios_avisos', ['estado' => 'incierto', 'error_codigo' => 'PROCESO_INTERRUMPIDO', 'intentos' => 1]);
    }

    public function test_revalida_baja_destino_suspension_variables_simulacion_ubicacion_y_antiguedad(): void
    {
        $u = $this->usuario();
        $this->alerta();
        $envio = EnvioAviso::firstOrFail();
        foreach (['baja', 'destino', 'suspension', 'variable', 'ubicacion', 'canal', 'antiguedad', 'simulacion'] as $caso) {
            $envio->refresh()->update(['estado' => 'pendiente', 'disponible_el' => now()]);
            $u->forceFill(['activo' => $caso !== 'suspension'])->save();
            $u->preferencias()->update(['notif_telegram' => $caso !== 'baja', 'telegram_destino' => $caso === 'destino' ? '987654321' : '123456789',
                'latitud' => $caso === 'ubicacion' ? 0 : -17.8, 'longitud' => $caso === 'ubicacion' ? 0 : -63.2]);
            PreferenciasAvisos::where('usuario_id', $u->id)->update(['metricas' => $caso === 'variable' ? ['aqi'] : ['temperatura']]);
            config(['avisos.telegram.habilitado' => $caso !== 'canal']);
            $envio->aviso->alerta->update(['instante' => $caso === 'antiguedad' ? now()->subHour() : now(), 'origen' => $caso === 'simulacion' ? 'simulado' : 'observado']);
            $this->procesar();
            $this->assertSame('cancelado', $envio->fresh()->estado, $caso);
        }
        Http::assertNothingSent();
    }

    public function test_comando_y_configuracion_no_activan_envios_por_defecto(): void
    {
        $this->usuario();
        $this->alerta();
        $this->artisan('avisos:procesar')->assertFailed();
        config(['avisos.procesar' => false]);
        $this->artisan('avisos:procesar', ['--procesar' => true])->assertFailed();
        $this->assertSame(0, $this->procesar());
        config(['avisos.procesar' => true]);
        $this->artisan('avisos:procesar', ['--procesar' => true, '--limite' => 'no'])->assertExitCode(2);
        Http::assertNothingSent();
        $this->assertDatabaseHas('envios_avisos', ['estado' => 'pendiente', 'intentos' => 0]);
    }

    public function test_formulario_exige_consentimiento_destino_valido_y_prohibe_autoelevacion(): void
    {
        $u = $this->usuario([]);
        $this->actingAs($u)->put('/avisos/canales', array_diff_key($this->opciones(), ['consentimiento' => true]))->assertSessionHasErrors('consentimiento');
        foreach (['@grupo', '-123456', 'https://servidor.test'] as $destino) {
            $this->put('/avisos/canales', array_replace($this->opciones(), ['telegram_destino' => $destino]))->assertSessionHasErrors('telegram_destino');
        }
        $this->put('/avisos/canales', $this->opciones() + ['rol' => 'admin', 'usuario_id' => 999])->assertSessionHasErrors(['rol', 'usuario_id']);
        $this->put('/avisos/canales', $this->opciones())->assertSessionHasNoErrors();
        $this->alerta();
        $this->put('/avisos/canales', array_diff_key($this->opciones([]), ['consentimiento' => true]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('envios_avisos', ['estado' => 'cancelado']);
        $this->assertFalse(SuscripcionCanalAviso::where('usuario_id', $u->id)->where('canal', 'telegram')->first()->activo);
        Http::assertNothingSent();
    }

    public function test_faltantes_no_permiten_activar_canales_y_no_exponen_tokens_en_pantalla(): void
    {
        $u = $this->usuario([]);
        config(['avisos.telegram.token' => null]);
        $this->actingAs($u)->put('/avisos/canales', $this->opciones())->assertSessionHasErrors('canales');
        $this->get('/avisos')->assertOk()->assertSee('Telegram no está disponible')->assertDontSee('token_controlado');
        config(['avisos.telegram.token' => '123456:token_controlado']);
        $u->preferencias()->update(['latitud' => null, 'longitud' => null]);
        $this->put('/avisos/canales', $this->opciones())->assertSessionHasErrors('canales');
        $this->assertDatabaseCount('envios_avisos', 0);
    }

    public function test_segundo_procesador_no_reclama_un_envio_que_ya_se_esta_transportando(): void
    {
        $this->usuario();
        $this->alerta();
        Http::fake(function () {
            $this->assertDatabaseHas('envios_avisos', ['estado' => 'en_proceso', 'intentos' => 1]);
            $this->assertSame(0, $this->procesar());

            return Http::response(['ok' => true, 'result' => ['message_id' => 123]]);
        });
        $this->artisan('avisos:procesar', ['--procesar' => true, '--limite' => 1])->assertSuccessful();
        Http::assertSentCount(1);
        $this->assertDatabaseHas('envios_avisos', ['estado' => 'aceptado', 'intentos' => 1]);
    }

    public function test_whatsapp_rechazo_y_limite_respetan_la_respuesta_y_su_espera(): void
    {
        $this->usuario(['whatsapp']);
        $this->alerta();
        Http::fake(['graph.facebook.com/*' => Http::sequence()->push(['error' => ['message' => 'privado']], 429, ['Retry-After' => '300'])
            ->push(['error' => ['message' => 'privado']], 401)]);
        $this->procesar();
        $envio = EnvioAviso::firstOrFail();
        $this->assertSame('reintento', $envio->estado);
        $this->assertSame(now()->addMinutes(5)->format('Y-m-d H:i:s'), $envio->disponible_el->format('Y-m-d H:i:s'));
        $this->travel(4)->minutes();
        $this->assertSame(0, $this->procesar());
        $this->travel(1)->minutes();
        $this->procesar();
        $this->assertSame('fallido', $envio->fresh()->estado);
        $this->assertStringNotContainsString('privado', $envio->fresh()->toJson());
        Http::assertSentCount(2);
    }

    public function test_cambio_de_destino_cancela_la_outbox_anterior_sin_reprogramarla(): void
    {
        $u = $this->usuario();
        $this->alerta();
        $this->actingAs($u)->put('/avisos/canales', array_replace($this->opciones(), ['telegram_destino' => '987654321']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('envios_avisos', ['estado' => 'cancelado', 'error_codigo' => 'CONSENTIMIENTO_CAMBIADO']);
        $this->assertSame(0, $this->procesar());
        Http::assertNothingSent();
    }

    public function test_novedades_inmediatas_son_privadas_y_cambian_al_leer_o_enviar(): void
    {
        $u = $this->usuario();
        $otro = $this->usuario([], ['name' => 'Otra cuenta']);
        $this->getJson('/avisos/novedades')->assertUnauthorized();
        $this->alerta();
        $primera = $this->actingAs($u)->getJson('/avisos/novedades')->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('sinLeer', 1)->json();
        $aviso = AvisoAmbiental::where('usuario_id', $u->id)->firstOrFail();
        $this->assertStringContainsString($aviso->id, $primera['html']);
        $this->assertStringNotContainsString($otro->email, $primera['html']);
        $this->patch('/avisos/'.$aviso->id.'/leer')->assertRedirect();
        $leida = $this->getJson('/avisos/novedades')->assertJsonPath('sinLeer', 0)->json();
        $this->assertNotSame($primera['revision'], $leida['revision']);
        $this->actingAs($otro)->patch('/avisos/'.$aviso->id.'/leer')->assertNotFound();
        $this->actingAs($u)->withSession(['cuenta_version' => 0])->getJson('/avisos/novedades')->assertUnauthorized();
    }
}
