<?php

namespace Tests\Feature;

use App\Models\DestinoPushAviso;
use App\Models\PreferenciasAvisos;
use App\Models\PuntoAmbiental;
use App\Models\SuscripcionCanalAviso;
use App\Models\User;
use App\Services\Datos\RegistrarLecturas;
use App\Services\Notificaciones\AccesoFirebaseAvisos;
use App\Services\Notificaciones\ProcesarOutboxAvisos;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PushWebAvisosTest extends TestCase
{
    use RefreshDatabase;

    private static string $privada = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        Route::middleware('web')->group(base_path('../docs/integracion/m3-avisos-rutas.php'));
        Route::getRoutes()->refreshNameLookups();
        app(ExceptionHandler::class)->renderable(require base_path('../docs/integracion/m3-avisos-excepciones.php'));
        if (self::$privada === '') {
            $opciones = ['config' => base_path('tests/Fixtures/avisos-openssl.cnf'), 'private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
            openssl_pkey_export(openssl_pkey_new($opciones), self::$privada, null, $opciones);
        }
        config(['avisos.procesar' => true, 'avisos.push' => ['habilitado' => true, 'proyecto' => 'proyecto-controlado',
            'cliente_email' => 'servicio@example.test', 'clave_privada' => self::$privada, 'api_key_publica' => 'publica-controlada',
            'app_id' => 'app-controlada', 'sender_id' => '1234', 'vapid_publica' => 'vapid-controlada']]);
    }

    private function usuario(): User
    {
        $u = User::factory()->create();
        PreferenciasAvisos::create(['usuario_id' => $u->id, 'activos' => true, 'simulados' => false, 'metricas' => ['temperatura']]);

        return $u;
    }

    private function suscribir(User $u, string $token = 'token-fcm-controlado-12345'): void
    {
        $this->actingAs($u)->putJson('/avisos/push', ['activo' => true, 'token' => $token, 'consentimiento' => true])->assertOk();
    }

    private function alerta(): void
    {
        PuntoAmbiental::create(['codigo' => 'campus', 'nombre' => 'Campus', 'pais' => 'BO', 'latitud' => -17.8, 'longitud' => -63.2, 'tipo' => 'manual']);
        app(RegistrarLecturas::class)->ejecutar([['punto' => 'campus', 'metrica' => 'temperatura', 'valor' => 45,
            'instante' => now()->subMinute()->toIso8601ZuluString(), 'origen' => 'observado', 'fuente' => 'manual']]);
    }

    public function test_alta_exige_sesion_permiso_y_consentimiento_y_cifra_el_token(): void
    {
        $this->putJson('/avisos/push', [])->assertUnauthorized();
        $u = $this->usuario();
        $this->actingAs($u)->putJson('/avisos/push', ['activo' => true, 'token' => 'token-fcm-controlado-12345'])->assertUnprocessable();
        $this->putJson('/avisos/push', ['activo' => true, 'token' => 'corto', 'consentimiento' => true])->assertUnprocessable();
        $this->suscribir($u);
        $this->assertSame('token-fcm-controlado-12345', DestinoPushAviso::firstOrFail()->token);
        $this->assertNotSame('token-fcm-controlado-12345', DB::table('destinos_push_avisos')->value('token'));
        $this->assertStringNotContainsString('token-fcm', DestinoPushAviso::first()->toJson());
        Http::assertNothingSent();
    }

    public function test_fcm_http_v1_autoriza_con_jwt_firmado_y_no_repite_el_mensaje(): void
    {
        $u = $this->usuario();
        $this->suscribir($u);
        $this->alerta();
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['access_token' => 'oauth-controlado', 'expires_in' => 3600]),
            'fcm.googleapis.com/*' => Http::response(['name' => 'projects/proyecto-controlado/messages/123'])]);
        $this->assertSame(1, app(ProcesarOutboxAvisos::class)->ejecutar());
        Http::assertSent(function ($r): bool {
            if ($r->url() !== 'https://oauth2.googleapis.com/token') {
                return false;
            }
            [$cabecera, $cuerpo, $firma] = explode('.', $r['assertion']);
            $payload = json_decode(base64_decode(strtr($cuerpo, '-_', '+/')), true);
            $publica = openssl_pkey_get_details(openssl_pkey_get_private(self::$privada))['key'];

            return $r['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer' &&
                $payload['scope'] === 'https://www.googleapis.com/auth/firebase.messaging' && $payload['iss'] === 'servicio@example.test' &&
                openssl_verify($cabecera.'.'.$cuerpo, base64_decode(strtr($firma, '-_', '+/')), $publica, OPENSSL_ALGO_SHA256) === 1;
        });
        Http::assertSent(fn ($r) => str_contains($r->url(), 'fcm.googleapis.com/v1/projects/proyecto-controlado/messages:send') &&
            $r->hasHeader('Authorization', 'Bearer oauth-controlado') && $r['message']['token'] === 'token-fcm-controlado-12345' &&
            $r['message']['notification']['title'] === 'GeoSur · Observación' && $r['message']['webpush']['headers']['TTL'] === '840');
        $this->assertDatabaseHas('envios_avisos', ['canal' => 'push', 'estado' => 'aceptado']);
        $this->assertSame(0, app(ProcesarOutboxAvisos::class)->ejecutar());
        $this->assertSame('oauth-controlado', app(AccesoFirebaseAvisos::class)->token());
        Http::assertSentCount(2);
    }

    public function test_baja_y_cambio_de_cuenta_en_navegador_compartido_revocan_la_suscripcion_anterior(): void
    {
        $u = $this->usuario();
        $otro = $this->usuario();
        $this->suscribir($u);
        $this->alerta();
        $this->suscribir($otro);
        $this->assertDatabaseMissing('destinos_push_avisos', ['usuario_id' => $u->id]);
        $this->assertDatabaseHas('envios_avisos', ['estado' => 'cancelado']);
        $this->putJson('/avisos/push', ['activo' => false])->assertOk();
        $this->assertDatabaseCount('destinos_push_avisos', 0);
        $this->assertSame(0, SuscripcionCanalAviso::where('canal', 'push')->where('activo', true)->count());
        Http::assertNothingSent();
    }

    public function test_fallo_o_credencial_invalida_no_se_convierte_en_conexion_exitosa(): void
    {
        $u = $this->usuario();
        $this->suscribir($u);
        $this->alerta();
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 401)]);
        app(ProcesarOutboxAvisos::class)->ejecutar();
        $this->assertDatabaseHas('envios_avisos', ['estado' => 'fallido', 'error_codigo' => 'PUSH_AUTORIZACION']);
        Http::assertSentCount(1);
        config(['avisos.push.clave_privada' => 'sin-clave-valida']);
        $this->assertNull(app(AccesoFirebaseAvisos::class)->token());
        Http::assertSentCount(1);
    }

    public function test_worker_y_pagina_solo_exponen_configuracion_publica_y_faltantes_no_activan_el_proveedor(): void
    {
        $u = $this->usuario();
        $this->actingAs($u)->get('/avisos/servicio-push.js')->assertOk()->assertHeader('Service-Worker-Allowed', '/avisos')
            ->assertSee('13.0.0/firebase-messaging-compat.js')->assertDontSee('PRIVATE KEY')->assertDontSee('servicio@example.test');
        $this->get('/avisos')->assertOk()->assertSee('Activar en este navegador')->assertDontSee('PRIVATE KEY');
        config(['avisos.push.habilitado' => false]);
        $this->get('/avisos/servicio-push.js')->assertNotFound();
        $this->putJson('/avisos/push', ['activo' => true, 'token' => 'token-fcm-controlado-12345', 'consentimiento' => true])->assertUnprocessable();
        Http::assertNothingSent();
    }
}
