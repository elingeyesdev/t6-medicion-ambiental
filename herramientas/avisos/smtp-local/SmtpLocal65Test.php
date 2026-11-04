<?php

use App\Models\AvisoAmbiental;
use App\Models\EnvioAviso;
use App\Models\PuntoAmbiental;
use App\Models\SuscripcionCanalAviso;
use App\Models\User;
use App\Services\Datos\RegistrarLecturas;
use App\Services\Notificaciones\CanalesAvisos;
use App\Services\Notificaciones\EncolarAvisos;
use App\Services\Notificaciones\ProcesarOutboxAvisos;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Tests\TestCase;

final class SmtpLocal65Test extends TestCase
{
    private array $initial;

    private array $observations = [];

    public function createApplication()
    {
        $private = realpath((string) getenv('SMTP65_PRIVATE'));
        $allowed = realpath(dirname(__DIR__, 3).'/tmp/smtp-local65');
        if (! $private || ! $allowed || ! str_starts_with(strtolower(str_replace('\\', '/', $private)), strtolower(str_replace('\\', '/', $allowed)).'/')) {
            throw new RuntimeException('Use run.mjs: private environment must be inside this worktree.');
        }
        foreach (['exec', 'shell_exec', 'system', 'passthru', 'proc_open', 'popen'] as $function) {
            if (function_exists($function)) {
                throw new RuntimeException('PHP process functions must be disabled before booting Laravel.');
            }
        }
        if (getenv('COLUMNS') !== '100' || getenv('LINES') !== '40') {
            throw new RuntimeException('Explicit terminal dimensions are required before booting Laravel.');
        }
        $app = require dirname(__DIR__, 3).'/web/bootstrap/app.php';
        // Never load web/.env or any environment/database from another checkout.
        $app->useEnvironmentPath($private)->loadEnvironmentFrom('isolated.env');
        $app->useStoragePath($private.'/storage');
        $this->traitsUsedByTest = array_flip(class_uses_recursive(self::class));
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['exec', 'shell_exec', 'system', 'passthru', 'proc_open', 'popen'] as $function) {
            $this->assertFalse(function_exists($function), $function.' must be disabled in the actual PHP child.');
        }
        $this->assertSame('100', getenv('COLUMNS'));
        $this->assertSame('40', getenv('LINES'));
        $this->freezeTime();
        $this->initial = $this->snapshot();
        $this->observations = [];
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('sqlite', DB::getDriverName());
        $this->assertSame(realpath(getenv('SMTP65_PRIVATE').'/outbox.sqlite'), realpath(DB::connection()->getDatabaseName()));
        // Rebuild only the disposable database; avoid unrelated down() migrations.
        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
        $this->assertSame('smtp', config('avisos.email.mailer'));
        $this->assertSame('smtp', config('mail.mailers.smtp.transport'));
        $this->assertSame('127.0.0.1', config('mail.mailers.smtp.host'));
        $this->assertSame($this->initial['port'], (int) config('mail.mailers.smtp.port'));
        $this->assertFalse((bool) config('avisos.telegram.habilitado'));
        $this->assertFalse((bool) config('avisos.whatsapp.habilitado'));
        $this->assertFalse((bool) config('avisos.push.habilitado'));
        $this->assertInstanceOf(EsmtpTransport::class, Mail::mailer('smtp')->getSymfonyTransport());
        PuntoAmbiental::create(['codigo' => 'smtp65', 'nombre' => 'Punto sintético SMTP65', 'pais' => 'BO',
            'latitud' => -17.8, 'longitud' => -63.2, 'tipo' => 'manual']);
    }

    protected function tearDown(): void
    {
        try {
            if (isset($this->app, $this->initial)) {
                Http::assertNothingSent();
                $outbox = EnvioAviso::orderBy('id')->get()->map(fn ($e) => $e->only(['canal', 'estado', 'intentos', 'error_codigo']))->all();
                file_put_contents(getenv('SMTP65_PRIVATE').'/cases/'.$this->name().'.json', json_encode([
                    'case' => $this->name(), 'observations' => $this->observations, 'outbox' => $outbox,
                    'messages_before' => count($this->initial['messages']), 'messages_after' => count($this->snapshot()['messages']),
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
                Mail::purge('smtp');
            }
        } finally {
            parent::tearDown();
        }
    }

    private function snapshot(): array
    {
        return json_decode(file_get_contents(getenv('SMTP65_SNAPSHOT')), true, flags: JSON_THROW_ON_ERROR);
    }

    private function messages(): array
    {
        return array_slice($this->snapshot()['messages'], count($this->initial['messages']));
    }

    private function events(string $type): array
    {
        return array_values(array_filter(array_slice($this->snapshot()['events'], count($this->initial['events'])), fn ($e) => $e['type'] === $type));
    }

    private function account(string $email = 'aceptacion@example.test'): User
    {
        // All identity fields are explicit; no Faker addresses or real accounts.
        $user = User::factory()->create(['name' => 'Cuenta sintética SMTP65', 'email' => $email]);
        $user->preferencias()->create(['latitud' => -17.8, 'longitud' => -63.2]);
        $this->actingAs($user)->put('/avisos/preferencias', ['activos' => true, 'simulados' => false, 'metricas' => ['temperatura']])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('preferencias_avisos', ['usuario_id' => $user->id, 'activos' => true]);

        return $user;
    }

    private function channelOptions(bool $enable = true): array
    {
        return ['notif_email' => $enable, 'notif_telegram' => false, 'notif_whatsapp' => false, 'consentimiento' => $enable];
    }

    private function consent(User $user): void
    {
        $this->actingAs($user)->put('/avisos/canales', $this->channelOptions())->assertRedirect()->assertSessionHasNoErrors();
        $subscription = SuscripcionCanalAviso::where('usuario_id', $user->id)->where('canal', 'email')->firstOrFail();
        $this->assertTrue($subscription->activo);
        $this->assertNotNull($subscription->consentido_el);
        $this->assertSame(app(CanalesAvisos::class)->huella($user->email), $subscription->huella);
    }

    private function alert(): void
    {
        app(RegistrarLecturas::class)->ejecutar([['punto' => 'smtp65', 'metrica' => 'temperatura', 'valor' => 45,
            'instante' => now()->subMinute()->toIso8601ZuluString(), 'origen' => 'observado', 'fuente' => 'smtp65-sintetico']]);
        $this->assertDatabaseCount('avisos_ambientales', 1);
        $this->assertCount(0, $this->messages(), 'Enqueue must never send SMTP.');
    }

    private function process(): int
    {
        return app(ProcesarOutboxAvisos::class)->ejecutar();
    }

    public function test_sin_consentimiento_web_y_flags_antiguos_no_envian(): void
    {
        $user = $this->account('sinconsentimiento@example.test');
        $user->preferencias()->update(['notif_email' => true]);
        $data = $this->channelOptions();
        unset($data['consentimiento']);
        $this->put('/avisos/canales', $data)->assertSessionHasErrors('consentimiento');
        $this->assertDatabaseCount('suscripciones_canal_avisos', 0);
        $this->alert();
        $this->assertDatabaseCount('envios_avisos', 0);
        $this->assertSame(0, $this->process());
        $this->assertCount(0, $this->events('connect'));
        $this->observations = ['web_rejected_missing_consent' => true, 'legacy_flag_did_not_authorize' => true];
    }

    public function test_smtp_real_aceptado_cli_reclamo_y_no_duplicado(): void
    {
        $user = $this->account();
        $this->consent($user);
        $this->alert();
        $this->assertDatabaseHas('envios_avisos', ['canal' => 'email', 'estado' => 'pendiente', 'intentos' => 0]);
        app(EncolarAvisos::class)->ejecutar(AvisoAmbiental::firstOrFail(), $user->fresh());
        $this->assertDatabaseCount('envios_avisos', 1);
        $this->artisan('avisos:procesar')->assertFailed();
        config(['avisos.procesar' => false]);
        $this->artisan('avisos:procesar', ['--procesar' => true])->assertFailed();
        config(['avisos.procesar' => true]);
        $this->artisan('avisos:procesar', ['--procesar' => true, '--limite' => 0])->assertExitCode(2);
        $this->assertCount(0, $this->events('connect'));
        $sent = [];
        Event::listen(MessageSending::class, function () {
            $this->assertSame(0, DB::transactionLevel(), 'SMTP must be outside the SQL transaction.');
            $this->assertDatabaseHas('envios_avisos', ['estado' => 'en_proceso', 'intentos' => 1]);
            $this->assertSame(0, $this->process(), 'A second processor cannot claim this record.');
        });
        Event::listen(MessageSent::class, function ($event) use (&$sent) {
            $sent[] = ['to' => $event->message->getTo()[0]->getAddress(), 'subject' => $event->message->getSubject(),
                'text' => $event->message->getTextBody(), 'debug' => $event->sent->getDebug()];
        });
        $this->artisan('avisos:procesar', ['--procesar' => true, '--limite' => 1])->assertSuccessful();
        $this->assertDatabaseHas('envios_avisos', ['estado' => 'aceptado', 'intentos' => 1, 'error_codigo' => null]);
        $this->assertCount(1, $sent);
        $this->assertSame('aceptacion@example.test', $sent[0]['to']);
        $this->assertSame('GeoSur · Observación ambiental', $sent[0]['subject']);
        $this->assertStringContainsString('Punto sintético SMTP65', $sent[0]['text']);
        $this->assertStringContainsString('45', $sent[0]['text']);
        $this->assertStringContainsString('Hora de Bolivia', $sent[0]['text']);
        $this->assertStringContainsString('250 SMTP65_LOCAL_ACCEPTED', $sent[0]['debug']);
        $this->assertCount(1, $this->messages());
        $message = $this->messages()[0];
        $this->assertSame(['aceptacion@example.test'], $message['to']);
        $this->assertSame('avisos@example.test', $message['from']);
        $this->assertSame(250, $message['finalReply']);
        $this->assertStringContainsString('To: aceptacion@example.test', $message['data']);
        $this->assertStringContainsString('Subject:', $message['data']);
        $this->assertStringContainsString('Message-ID:', $message['data']);
        $this->assertSame(0, $this->process());
        app(EncolarAvisos::class)->ejecutar(AvisoAmbiental::firstOrFail(), $user->fresh());
        $this->assertSame(0, $this->process());
        $this->assertDatabaseCount('envios_avisos', 1);
        $this->assertCount(1, $this->messages());
        $this->get('/avisos')->assertOk()->assertSee('Aceptado por el proveedor')->assertDontSee('Registrado en correo local');
        $this->getJson('/avisos/novedades')->assertOk()->assertSee('Aceptado por el proveedor');
        $this->observations = ['sql_transaction_during_smtp' => 0, 'second_processor_claims' => 0,
            'smtp_messages' => 1, 'web_status' => 'Aceptado por el proveedor', 'message_sent_event_real' => $sent];
    }

    public function test_baja_web_cancela_pendiente_y_no_abre_smtp(): void
    {
        $user = $this->account('baja@example.test');
        $this->consent($user);
        $this->alert();
        $this->put('/avisos/canales', $this->channelOptions(false))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('envios_avisos', ['estado' => 'cancelado', 'intentos' => 0, 'error_codigo' => 'CONSENTIMIENTO_CAMBIADO']);
        $this->assertDatabaseHas('suscripciones_canal_avisos', ['usuario_id' => $user->id, 'canal' => 'email', 'activo' => false, 'consentido_el' => null]);
        $this->assertSame(0, $this->process());
        $this->get('/avisos')->assertOk()->assertSee('Cancelado');
        $this->assertCount(0, $this->events('connect'));
        $this->observations = ['web_unsubscribe' => true, 'smtp_connections' => 0];
    }

    public function test_cambio_de_correo_revalida_y_no_redirige_pendientes(): void
    {
        $user = $this->account('anterior@example.test');
        $this->consent($user);
        $this->alert();
        User::whereKey($user->id)->update(['email' => 'nuevo@example.test']);
        $this->assertSame(1, $this->process());
        $this->assertDatabaseHas('envios_avisos', ['estado' => 'cancelado', 'error_codigo' => 'PREFERENCIAS_O_VIGENCIA']);
        $this->assertSame(0, $this->process());
        $this->assertCount(0, $this->events('connect'));
        $this->observations = ['pending_not_redirected' => true, 'smtp_connections' => 0];
    }

    public function test_rechazo_smtp_550_sin_data_fallido_sin_reenvio(): void
    {
        $user = $this->account('rechazo@example.test');
        $this->consent($user);
        $this->alert();
        $this->assertSame(1, $this->process());
        $this->assertDatabaseHas('envios_avisos', ['estado' => 'fallido', 'intentos' => 1, 'error_codigo' => 'PROVEEDOR_RECHAZO']);
        $this->assertCount(1, $this->events('rcpt'));
        $this->assertSame(550, $this->events('rcpt')[0]['reply']);
        $trace = implode("\n", array_column($this->events('protocol'), 'text'));
        $this->assertStringContainsString('S: 550 SMTP65_RECHAZO_SINTETICO', $trace);
        $this->assertStringNotContainsString('C: DATA', $trace);
        $this->assertCount(0, $this->messages());
        $this->assertStringNotContainsString('SMTP65_RECHAZO_SINTETICO', EnvioAviso::firstOrFail()->toJson());
        $connections = count($this->events('connect'));
        $this->travel(1)->minutes();
        $this->assertSame(0, $this->process());
        $this->assertCount($connections, $this->events('connect'));
        $this->get('/avisos')->assertOk()->assertSee('No se pudo enviar');
        $this->observations = ['explicit_smtp_rcpt_reply' => 550, 'classification' => 'fallido / PROVEEDOR_RECHAZO', 'automatic_resends' => 0];
    }

    public function test_data_real_sin_respuesta_final_timeout_no_se_repite(): void
    {
        $user = $this->account('incierto@example.test');
        $this->consent($user);
        $this->alert();
        $this->assertSame(1, $this->process());
        $this->assertDatabaseHas('envios_avisos', ['estado' => 'incierto', 'intentos' => 1, 'error_codigo' => 'TRANSPORTE_SIN_CONFIRMACION']);
        $this->assertCount(1, $this->messages());
        $this->assertNull($this->messages()[0]['finalReply']);
        $trace = implode("\n", array_column($this->events('protocol'), 'text'));
        $this->assertStringContainsString('C: DATA', $trace);
        $this->assertStringContainsString('S: 354', $trace);
        $this->assertStringNotContainsString('SMTP65_LOCAL_ACCEPTED', $trace);
        $connections = count($this->events('connect'));
        $this->travel(1)->minutes();
        $this->assertSame(0, $this->process());
        $this->assertCount($connections, $this->events('connect'));
        $this->assertCount(1, $this->messages());
        $this->get('/avisos')->assertOk()->assertSee('Sin confirmación');
        $this->observations = ['data_received' => 1, 'final_reply' => null, 'automatic_resends' => 0,
            'limit' => 'DATA received locally; acceptance or external delivery is not claimed'];
    }

    public function test_smtp_450_sigue_incierto_sin_reintento_automatico(): void
    {
        $user = $this->account('temporal@example.test');
        $this->consent($user);
        $this->alert();
        $this->assertSame(1, $this->process());
        $this->assertDatabaseHas('envios_avisos', ['estado' => 'incierto', 'intentos' => 1, 'error_codigo' => 'TRANSPORTE_SIN_CONFIRMACION']);
        $this->assertSame(450, $this->events('rcpt')[0]['reply']);
        $trace = implode("\n", array_column($this->events('protocol'), 'text'));
        $this->assertStringContainsString('S: 450 SMTP65_RECHAZO_SINTETICO', $trace);
        $this->assertStringNotContainsString('C: DATA', $trace);
        $this->assertCount(0, $this->messages());
        $this->assertStringNotContainsString('SMTP65_RECHAZO_SINTETICO', EnvioAviso::firstOrFail()->toJson());
        $connections = count($this->events('connect'));
        $this->travel(1)->minutes();
        $this->assertSame(0, $this->process());
        $this->assertCount($connections, $this->events('connect'));
        $this->observations = ['explicit_smtp_rcpt_reply' => 450, 'classification' => 'incierto', 'automatic_resends' => 0];
    }

    public function test_reinicio_rechazado_despues_de_data_aceptado_no_es_fallido(): void
    {
        $user = $this->account('reinicio@example.test');
        $this->consent($user);
        $this->alert();
        Mail::purge('smtp');
        config(['mail.mailers.smtp.restart_threshold' => 1, 'mail.mailers.smtp.restart_threshold_sleep' => 0]);
        $this->assertSame(1, $this->process());
        $this->assertDatabaseHas('envios_avisos', ['estado' => 'incierto', 'intentos' => 1, 'error_codigo' => 'TRANSPORTE_SIN_CONFIRMACION']);
        $this->assertCount(1, $this->messages());
        $this->assertSame(['reinicio@example.test'], $this->messages()[0]['to']);
        $this->assertSame(250, $this->messages()[0]['finalReply']);
        $connections = $this->events('connect');
        $this->assertCount(2, $connections);
        $this->assertSame(554, $connections[1]['reply']);
        $trace = implode("\n", array_column($this->events('protocol'), 'text'));
        $accepted = strpos($trace, 'S: 250 SMTP65_LOCAL_ACCEPTED');
        $restartRejected = strpos($trace, 'S: 554 SMTP132_REINICIO_RECHAZADO');
        $this->assertNotFalse($accepted);
        $this->assertNotFalse($restartRejected);
        $this->assertGreaterThan($accepted, $restartRejected, 'The second connection rejects only after DATA was accepted.');
        $this->assertSame(1, substr_count($trace, 'C: DATA'));
        $this->assertStringNotContainsString('SMTP132_REINICIO_RECHAZADO', EnvioAviso::firstOrFail()->toJson());
        $this->travel(1)->minutes();
        $this->assertSame(0, $this->process());
        $this->assertCount(2, $this->events('connect'));
        $this->assertCount(1, $this->messages());
        $this->get('/avisos')->assertOk()->assertSee('Sin confirmación');
        $this->observations = ['restart_threshold' => 1, 'data_accepted' => true, 'restart_connection_reply' => 554,
            'classification' => 'incierto', 'smtp_messages' => 1, 'automatic_resends' => 0];
    }
}
