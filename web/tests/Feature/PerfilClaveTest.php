<?php

namespace Tests\Feature;

use App\Enums\RolCuenta;
use App\Models\User;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PerfilClaveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Route::middleware('web')->group(base_path('../docs/integracion/m3-avisos-rutas.php'));
        Route::getRoutes()->refreshNameLookups();
        app(ExceptionHandler::class)->renderable(require base_path('../docs/integracion/m3-avisos-excepciones.php'));
    }

    public function test_cambiar_clave_revoca_tokens_enlaces_y_otras_sesiones_y_conserva_la_actual(): void
    {
        $u = User::factory()->create(['rol' => 'analista']);
        $otro = User::factory()->create();
        $u->createToken('Dispositivo', ['cuenta']);
        $token = Password::createToken($u);
        $this->actingAs($u)->get('/cuenta')->assertOk()->assertSee('Cambiar contraseña');
        $this->put('/cuenta/clave', $this->datos())->assertSessionHasNoErrors()->assertSessionHas('cuenta_version', 2);
        $u->refresh();
        $this->assertTrue(Hash::check('Una-clave-nueva-2026', $u->password));
        $this->assertSame(RolCuenta::Analista, $u->rol);
        $this->assertSame(0, $u->tokens()->count());
        $this->assertFalse(Password::tokenExists($u, $token));
        $this->assertTrue(Hash::check('password', $otro->fresh()->password));
        $this->get('/cuenta')->assertOk();
        $this->actingAs($u)->withSession(['cuenta_version' => 1])->get('/cuenta')->assertRedirect('/login');
    }

    private function datos(array $cambios = []): array
    {
        return $cambios + ['current_password' => 'password', 'password' => 'Una-clave-nueva-2026', 'password_confirmation' => 'Una-clave-nueva-2026'];
    }

    public function test_clave_incorrecta_corta_no_confirmada_y_permisos_inyectados_no_cambian_cuenta(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->put('/cuenta/clave', $this->datos(['current_password' => 'otra']))->assertSessionHasErrors('current_password');
        $this->put('/cuenta/clave', $this->datos(['password' => 'corta']))->assertSessionHasErrors('password');
        $this->put('/cuenta/clave', $this->datos(['password' => str_repeat('á', 40), 'password_confirmation' => str_repeat('á', 40)]))->assertSessionHasErrors('password');
        $this->put('/cuenta/clave', $this->datos(['rol' => 'admin', 'usuario_id' => 999]))->assertSessionHasErrors(['rol', 'usuario_id']);
        $this->assertNull(session('_old_input.current_password'));
        $this->assertNull(session('_old_input.password'));
        $this->assertTrue(Hash::check('password', $u->fresh()->password));
        $this->assertSame(1, $u->fresh()->sesion_version);
        $this->assertSame(RolCuenta::Visualizador, $u->fresh()->rol);
    }

    public function test_clave_y_canales_exigen_sesion_activa_csrf_y_limiter(): void
    {
        $this->put('/cuenta/clave', $this->datos())->assertRedirect('/login');
        $u = User::factory()->create();
        $this->actingAs($u);
        for ($i = 0; $i < 5; $i++) {
            $this->put('/cuenta/clave', $this->datos(['current_password' => 'incorrecta']))->assertRedirect();
        }
        $this->put('/cuenta/clave', $this->datos())->assertStatus(429);
        $this->app->instance('env', 'local');
        $this->put('/cuenta/clave', $this->datos())->assertStatus(419);
        $this->put('/avisos/canales', [])->assertStatus(419);
    }

    public function test_fallo_de_recuperacion_no_revela_la_cuenta_ni_el_error_del_proveedor(): void
    {
        Password::shouldReceive('sendResetLink')->once()->andThrow(new \RuntimeException('token-secreto contacto-privado'));
        Log::shouldReceive('warning')->once()->with('RECUPERACION_TRANSPORTE_NO_CONFIRMADO');
        $this->postJson('/api/v1/cuenta/recuperar', ['email' => 'prueba@example.test'])->assertOk()
            ->assertJsonPath('mensaje', 'Si ese correo tiene una cuenta activa, el enlace se guardará en el registro local de correo. No llegará a tu buzón.')
            ->assertDontSee('token-secreto')->assertDontSee('contacto-privado');
    }
}
