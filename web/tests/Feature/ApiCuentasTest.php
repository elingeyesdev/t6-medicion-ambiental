<?php

namespace Tests\Feature;

use App\Enums\RolCuenta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class ApiCuentasTest extends TestCase
{
    use RefreshDatabase;

    private function conToken(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }

    public function test_registro_movil_y_nombre_no_permiten_asignarse_permisos(): void
    {
        $clave = 'Una-frase-larga-2026';
        $respuesta = $this->postJson('/api/v1/cuenta/registrar', ['name' => 'Nueva cuenta', 'email' => ' NUEVA@EXAMPLE.TEST ', 'password' => $clave, 'password_confirmation' => $clave, 'rol' => 'admin', 'activo' => false])
            ->assertCreated()->assertJsonPath('usuario.rol', 'visualizador')->assertJsonPath('usuario.email', 'nueva@example.test')->assertJsonPath('usuario.activo', true);
        $token = $respuesta->json('token');
        $this->conToken($token)->putJson('/api/v1/cuenta/nombre', ['name' => 'Nuevo nombre'])->assertOk()->assertJsonPath('usuario.nombre', 'Nuevo nombre');
        $this->conToken($token)->putJson('/api/v1/cuenta/nombre', ['name' => 'Intento', 'rol' => 'admin'])->assertUnprocessable();
        $this->assertSame(RolCuenta::Visualizador, User::firstOrFail()->rol);
    }

    public function test_ingreso_devuelve_token_que_se_guarda_protegido_y_vence_en_un_dia(): void
    {
        $usuario = User::factory()->create(['email' => 'equipo@example.test']);
        $respuesta = $this->postJson('/api/v1/cuenta/ingresar', ['email' => ' EQUIPO@EXAMPLE.TEST ', 'password' => 'password'])->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')->assertJsonPath('usuario.rol', 'visualizador')->assertJsonMissingPath('usuario.password')->assertJsonMissingPath('usuario.remember_token');
        $token = $respuesta->json('token');
        $guardado = PersonalAccessToken::findToken($token);
        $this->assertNotNull($guardado);
        $this->assertNotSame($token, $guardado->token);
        $this->assertEqualsWithDelta(86400, now()->diffInSeconds($guardado->expires_at), 2);
        $this->conToken($token)->getJson('/api/v1/cuenta')->assertOk()->assertJsonPath('usuario.id', $usuario->id)->assertJsonMissingPath('token');
    }

    public function test_clave_incorrecta_cuenta_ausente_y_suspendida_dan_el_mismo_error(): void
    {
        $suspendida = User::factory()->create(['activo' => false]);
        $activa = User::factory()->create();
        foreach ([[$activa->email, 'mal'], ['noexiste@example.test', 'password'], [$suspendida->email, 'password']] as [$email, $password]) {
            $this->postJson('/api/v1/cuenta/ingresar', compact('email', 'password'))->assertUnauthorized()->assertJsonPath('error.codigo', 'CREDENCIALES_INVALIDAS');
        }
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_el_limite_de_intentos_se_comparte_entre_web_y_movil(): void
    {
        $usuario = User::factory()->create();
        for ($i = 0; $i < 3; $i++) {
            $this->post('/login', ['email' => $usuario->email, 'password' => 'mal']);
        }
        for ($i = 0; $i < 2; $i++) {
            $this->postJson('/api/v1/cuenta/ingresar', ['email' => $usuario->email, 'password' => 'mal'])->assertUnauthorized();
        }
        $this->postJson('/api/v1/cuenta/ingresar', ['email' => $usuario->email, 'password' => 'password'])->assertStatus(429);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_api_rechaza_token_ausente_invalido_vencido_sin_ability_y_cuenta_suspendida(): void
    {
        $usuario = User::factory()->create();
        $this->getJson('/api/v1/cuenta')->assertUnauthorized();
        $this->conToken('invalido')->getJson('/api/v1/cuenta')->assertUnauthorized();
        $vencido = $usuario->createToken('Vencido', ['cuenta'], now()->subSecond())->plainTextToken;
        $this->conToken($vencido)->getJson('/api/v1/cuenta')->assertUnauthorized();
        $sinPermiso = $usuario->createToken('Sin permiso', ['otro'])->plainTextToken;
        $this->conToken($sinPermiso)->getJson('/api/v1/cuenta')->assertForbidden()->assertJsonPath('error.codigo', 'ACCESO_DENEGADO');
        $token = $usuario->createToken('Activo', ['cuenta'])->plainTextToken;
        $usuario->forceFill(['activo' => false])->save();
        $this->conToken($token)->getJson('/api/v1/cuenta')->assertUnauthorized();
    }

    public function test_cookie_web_no_reemplaza_el_token_movil_y_salir_revoca_solo_ese_dispositivo(): void
    {
        $usuario = User::factory()->create();
        $this->actingAs($usuario)->getJson('/api/v1/cuenta')->assertUnauthorized();
        $uno = $usuario->createToken('Uno', ['cuenta'])->plainTextToken;
        $dos = $usuario->createToken('Dos', ['cuenta'])->plainTextToken;
        $this->conToken($uno)->postJson('/api/v1/cuenta/salir')->assertOk();
        $this->assertNull(PersonalAccessToken::findToken($uno));
        $this->conToken($uno)->getJson('/api/v1/cuenta')->assertUnauthorized();
        $this->conToken($dos)->getJson('/api/v1/cuenta')->assertOk();
    }

    public function test_solo_administrador_lista_y_cambia_cuentas_por_api_y_revoca_el_acceso_anterior(): void
    {
        $destino = User::factory()->create();
        $tokenDestino = $destino->createToken('Destino', ['cuenta'])->plainTextToken;
        $this->conToken($tokenDestino)->getJson('/api/v1/usuarios')->assertForbidden();
        $this->conToken($tokenDestino)->patchJson('/api/v1/usuarios/'.$destino->id.'/rol', ['rol' => 'admin'])->assertForbidden();
        $admin = User::factory()->create(['rol' => RolCuenta::Administrador]);
        $token = $admin->createToken('Admin', ['cuenta'])->plainTextToken;
        $this->conToken($token)->getJson('/api/v1/usuarios')->assertOk()->assertJsonCount(2, 'datos')
            ->assertJsonPath('datos.0.id', $destino->id)->assertJsonMissingPath('datos.0.password')->assertJsonMissingPath('datos.0.remember_token')
            ->assertJsonMissingPath('datos.0.sesion_version')->assertJsonMissingPath('datos.0.preferencias');
        $this->conToken($token)->patchJson('/api/v1/usuarios/'.$destino->id.'/rol', ['rol' => 'analista'])->assertOk()->assertJsonPath('usuario.rol', 'analista');
        $this->conToken($tokenDestino)->getJson('/api/v1/cuenta')->assertUnauthorized();
        $this->conToken($token)->patchJson('/api/v1/usuarios/'.$destino->id.'/estado', ['activo' => false])->assertOk()->assertJsonPath('usuario.activo', false);
        $this->conToken($token)->patchJson('/api/v1/usuarios/'.$admin->id.'/estado', ['activo' => false])->assertUnprocessable()->assertJsonPath('error.codigo', 'FILTROS_INVALIDOS');
    }

    public function test_preferencias_se_guardan_solo_en_la_cuenta_propia_y_sin_enviar_avisos(): void
    {
        Http::preventStrayRequests();
        $usuario = User::factory()->create();
        $otro = User::factory()->create();
        $token = $usuario->createToken('Cuenta', ['cuenta'])->plainTextToken;
        $datos = ['latitud' => -17.78, 'longitud' => -63.18, 'notif_email' => true, 'notif_whatsapp' => true, 'whatsapp_destino' => 'destino de prueba'];
        $this->conToken($token)->putJson('/api/v1/cuenta/preferencias', $datos)->assertOk()->assertJsonPath('preferencias.notif_whatsapp', true)->assertJsonPath('preferencias.latitud', -17.78);
        $this->assertNull($otro->preferencias);
        $this->conToken($token)->putJson('/api/v1/cuenta/preferencias', ['user_id' => $otro->id, 'rol' => 'admin', 'activo' => true])->assertUnprocessable();
        $this->conToken($token)->putJson('/api/v1/cuenta/preferencias', ['latitud' => -18, 'longitud' => -64])->assertOk()->assertJsonPath('preferencias.notif_email', true);
        $this->conToken($token)->putJson('/api/v1/cuenta/preferencias', ['latitud' => null, 'longitud' => null])->assertOk()
            ->assertJsonPath('preferencias.latitud', null)->assertJsonPath('preferencias.notif_email', false)->assertJsonPath('preferencias.notif_whatsapp', false);
        Http::assertNothingSent();
    }

    public function test_preferencias_rechazan_ubicacion_incompleta_fuera_de_rango_y_destino_ausente(): void
    {
        $usuario = User::factory()->create();
        $token = $usuario->createToken('Cuenta', ['cuenta'])->plainTextToken;
        foreach ([['latitud' => 10], ['latitud' => 91, 'longitud' => 10], ['latitud' => 10, 'longitud' => 181], ['latitud' => 10, 'longitud' => 10, 'notif_telegram' => true]] as $datos) {
            $this->conToken($token)->putJson('/api/v1/cuenta/preferencias', $datos)->assertUnprocessable()->assertJsonStructure(['error' => ['codigo', 'mensaje', 'campos']]);
        }
        $this->assertDatabaseCount('preferencias_cuenta', 0);
    }
}
