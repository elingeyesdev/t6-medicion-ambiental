<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\Canales\RecuperacionCorreoLocal;
use App\Notifications\RecuperarAcceso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class RecuperacionAccesoTest extends TestCase
{
    use RefreshDatabase;

    public function test_solicitud_no_revela_si_el_correo_existe_y_solo_notifica_cuentas_activas(): void
    {
        Notification::fake();
        $activa = User::factory()->create(['email' => 'activa@example.test']);
        $suspendida = User::factory()->create(['activo' => false]);
        $mensaje = 'Si ese correo tiene una cuenta activa, el enlace se guardará en el registro local de correo. No llegará a tu buzón.';
        foreach ([' ACTIVA@EXAMPLE.TEST ', $suspendida->email, 'ausente@example.test'] as $correo) {
            $this->from('/recuperar-acceso')->post('/recuperar-acceso', ['email' => $correo])->assertRedirect('/recuperar-acceso')->assertSessionHas('estado', $mensaje);
        }
        Notification::assertSentToTimes($activa, RecuperarAcceso::class, 1);
        Notification::assertNotSentTo($suspendida, RecuperarAcceso::class);
        $this->assertDatabaseCount('password_reset_tokens', 1);
        $this->assertSame(15, config('auth.passwords.users.expire'));
    }

    public function test_el_enlace_usa_la_direccion_configurada_y_el_correo_local_no_sale_del_equipo(): void
    {
        config(['app.url' => 'http://localhost:5791']);
        $usuario = User::factory()->create();
        $mensaje = (new RecuperarAcceso('token-de-prueba'))->toMail($usuario);
        $this->assertSame('log', $mensaje->mailer);
        $this->assertStringStartsWith('http://localhost:5791/restablecer-acceso/token-de-prueba?', $mensaje->actionUrl);
        $this->assertStringContainsString(rawurlencode($usuario->email), $mensaje->actionUrl);
        $this->get('/recuperar-acceso')->assertOk()->assertSeeText('¿Olvidaste tu contraseña?')->assertSee('name="_token"', false);
        $this->get('/restablecer-acceso/token-de-prueba?email='.urlencode($usuario->email))->assertOk()->assertSeeText('Crea una nueva contraseña');
    }

    public function test_mailer_log_se_identifica_como_local_tambien_fuera_del_entorno_local(): void
    {
        $this->app->instance('env', 'production');
        config(['mail.default' => 'log']);
        $usuario = User::factory()->create();
        $aviso = new RecuperarAcceso('token-controlado');
        $this->assertSame([RecuperacionCorreoLocal::class], $aviso->via($usuario));
        $this->assertSame('log', $aviso->toMail($usuario)->mailer);
    }

    public function test_cambiar_clave_consume_el_enlace_y_revoca_tokens_y_sesiones_anteriores(): void
    {
        $usuario = User::factory()->create();
        $usuario->createToken('Un dispositivo', ['cuenta']);
        $token = Password::createToken($usuario);
        $this->assertNotSame($token, DB::table('password_reset_tokens')->value('token'));
        $datos = ['email' => $usuario->email, 'token' => $token, 'password' => 'Una-clave-nueva-2026', 'password_confirmation' => 'Una-clave-nueva-2026'];
        $this->post('/restablecer-acceso', $datos)->assertRedirect('/login')->assertSessionHasNoErrors();
        $this->assertGuest();
        $usuario->refresh();
        $this->assertTrue(Hash::check($datos['password'], $usuario->password));
        $this->assertFalse(Hash::check('password', $usuario->password));
        $this->assertSame(2, $usuario->sesion_version);
        $this->assertSame(0, $usuario->tokens()->count());
        $this->assertDatabaseCount('password_reset_tokens', 0);
        $this->post('/restablecer-acceso', $datos)->assertSessionHasErrors('email');
        $this->actingAs($usuario)->withSession(['cuenta_version' => 1])->get('/cuenta')->assertRedirect('/login');
    }

    public function test_el_enlace_vencido_incorrecto_o_de_una_cuenta_suspendida_no_cambia_la_clave(): void
    {
        foreach (['vencido', 'incorrecto', 'suspendida'] as $caso) {
            $usuario = User::factory()->create();
            $token = Password::createToken($usuario);
            if ($caso === 'vencido') {
                $this->travel(16)->minutes();
            }
            if ($caso === 'suspendida') {
                $usuario->forceFill(['activo' => false])->save();
            }
            $this->post('/restablecer-acceso', ['email' => $usuario->email, 'token' => $caso === 'incorrecto' ? 'otro' : $token, 'password' => 'Una-clave-nueva-2026', 'password_confirmation' => 'Una-clave-nueva-2026'])->assertSessionHasErrors('email');
            $this->assertTrue(Hash::check('password', $usuario->fresh()->password));
            $this->travelBack();
        }
    }

    public function test_el_restablecimiento_valida_tipos_confirmacion_y_largo_en_bytes(): void
    {
        $usuario = User::factory()->create();
        $token = Password::createToken($usuario);
        foreach ([['corta', 'corta'], ['Una-clave-nueva-2026', 'otra'], [str_repeat('á', 40), str_repeat('á', 40)], [[], []]] as [$password, $confirmation]) {
            $this->post('/restablecer-acceso', ['email' => $usuario->email, 'token' => $token, 'password' => $password, 'password_confirmation' => $confirmation])->assertSessionHasErrors('password');
            $this->assertNull(session('_old_input.password'));
            $this->assertNull(session('_old_input.password_confirmation'));
        }
        $this->assertTrue(Hash::check('password', $usuario->fresh()->password));
    }

    public function test_las_solicitudes_repetidas_se_limitan(): void
    {
        Notification::fake();
        $usuario = User::factory()->create();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/recuperar-acceso', ['email' => $usuario->email])->assertRedirect();
        }
        $this->post('/recuperar-acceso', ['email' => $usuario->email])->assertStatus(429);
        Notification::assertSentToTimes($usuario, RecuperarAcceso::class, 1);
    }

    public function test_movil_puede_pedir_y_cambiar_la_clave_con_el_mismo_enlace_sin_redireccion_html(): void
    {
        Notification::fake();
        $usuario = User::factory()->create();
        $this->postJson('/api/v1/cuenta/recuperar', ['email' => $usuario->email])->assertOk()->assertJsonStructure(['mensaje'])->assertJsonMissingPath('token');
        $token = null;
        Notification::assertSentTo($usuario, RecuperarAcceso::class, function ($aviso) use (&$token): bool {
            $token = $aviso->token;

            return true;
        });
        $datos = ['email' => $usuario->email, 'token' => $token, 'password' => 'Una-clave-nueva-2026', 'password_confirmation' => 'Una-clave-nueva-2026'];
        $this->postJson('/api/v1/cuenta/restablecer', $datos)->assertOk()->assertJsonStructure(['mensaje']);
        $this->postJson('/api/v1/cuenta/restablecer', $datos)->assertUnprocessable();
        $this->assertTrue(Hash::check($datos['password'], $usuario->fresh()->password));
    }
}
