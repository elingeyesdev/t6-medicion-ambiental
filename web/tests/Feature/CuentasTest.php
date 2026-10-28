<?php

namespace Tests\Feature;

use App\Enums\RolCuenta;
use App\Models\User;
use App\Services\Cuentas\AdministrarCuenta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CuentasTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_registro_no_permite_elegir_rol_ni_estado(): void
    {
        $clave = 'Una-frase-larga-2026';
        $this->post('/register', ['name' => 'Nueva cuenta', 'email' => 'nueva@example.test', 'password' => $clave, 'password_confirmation' => $clave, 'rol' => 'admin', 'activo' => false])
            ->assertRedirect('/mapa');
        $usuario = User::firstOrFail();
        $this->assertSame(RolCuenta::Visualizador, $usuario->rol);
        $this->assertTrue($usuario->activo);
        $this->assertFalse(Gate::forUser($usuario)->allows('usuarios.administrar'));
    }

    public function test_los_tres_roles_conservan_los_permisos_del_original(): void
    {
        foreach (RolCuenta::cases() as $rol) {
            $usuario = User::factory()->create(['rol' => $rol]);
            $this->assertTrue(Gate::forUser($usuario)->allows('mapa.ver'));
            $this->assertTrue(Gate::forUser($usuario)->allows('reportes.ver'));
            $this->assertSame($rol !== RolCuenta::Visualizador, Gate::forUser($usuario)->allows('simulaciones.gestionar'));
            $this->assertSame($rol !== RolCuenta::Visualizador, Gate::forUser($usuario)->allows('reportes.crear'));
            $this->assertSame($rol === RolCuenta::Administrador, Gate::forUser($usuario)->allows('usuarios.administrar'));
        }
    }

    public function test_un_visualizador_o_analista_no_puede_administrar_ni_con_peticion_directa(): void
    {
        $destino = User::factory()->create();
        foreach ([RolCuenta::Visualizador, RolCuenta::Analista] as $rol) {
            $this->actingAs(User::factory()->create(['rol' => $rol]));
            $this->get('/cuenta')->assertOk()->assertSee('Mi cuenta')->assertDontSee('href="'.route('usuarios').'"', false);
            $this->get('/usuarios')->assertForbidden();
            $this->patch('/usuarios/'.$destino->id.'/rol', ['rol' => 'admin'])->assertForbidden();
            $this->patch('/usuarios/'.$destino->id.'/estado', ['activo' => false])->assertForbidden();
        }
        $this->assertTrue($destino->fresh()->activo);
        $this->assertSame(RolCuenta::Visualizador, $destino->fresh()->rol);
    }

    public function test_un_administrador_puede_buscar_y_cambiar_otra_cuenta_con_sesion_revocada(): void
    {
        $admin = User::factory()->create(['rol' => RolCuenta::Administrador]);
        $destino = User::factory()->create(['name' => 'Analista de prueba']);
        $destino->createToken('Prueba', ['cuenta']);
        $this->actingAs($admin)->get('/usuarios?buscar=Analista')->assertOk()->assertSee('Analista de prueba')->assertDontSee($admin->email);
        $this->patch('/usuarios/'.$destino->id.'/rol', ['rol' => 'analista'])->assertRedirect()->assertSessionHasNoErrors();
        $destino->refresh();
        $this->assertSame(RolCuenta::Analista, $destino->rol);
        $this->assertSame(2, $destino->sesion_version);
        $this->assertSame(0, $destino->tokens()->count());
        $this->patch('/usuarios/'.$destino->id.'/estado', ['activo' => '0'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFalse($destino->fresh()->activo);
        $this->patch('/usuarios/'.$destino->id.'/estado', ['activo' => '1'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue($destino->fresh()->activo);
    }

    public function test_no_se_puede_dejar_sin_administracion_por_un_cambio_propio(): void
    {
        $admin = User::factory()->create(['rol' => RolCuenta::Administrador]);
        $this->actingAs($admin)->patch('/usuarios/'.$admin->id.'/rol', ['rol' => 'visualizador'])->assertSessionHasErrors('cuenta');
        $this->patch('/usuarios/'.$admin->id.'/estado', ['activo' => false])->assertSessionHasErrors('cuenta');
        $this->assertSame(RolCuenta::Administrador, $admin->fresh()->rol);
        $this->assertTrue($admin->fresh()->activo);
    }

    public function test_se_rechazan_roles_y_estados_invalidos_y_un_cambio_igual_no_revoca_sesiones(): void
    {
        $admin = User::factory()->create(['rol' => RolCuenta::Administrador]);
        $destino = User::factory()->create();
        $destino->createToken('Prueba', ['cuenta']);
        $this->actingAs($admin)->patch('/usuarios/'.$destino->id.'/rol', ['rol' => 'superadmin'])->assertSessionHasErrors('rol');
        $this->patch('/usuarios/'.$destino->id.'/estado', ['activo' => 'cualquier cosa'])->assertSessionHasErrors('activo');
        app(AdministrarCuenta::class)->cambiar($admin, $destino, RolCuenta::Visualizador, true);
        $this->assertSame(1, $destino->fresh()->sesion_version);
        $this->assertSame(1, $destino->tokens()->count());
    }

    public function test_el_servicio_vuelve_a_comprobar_el_permiso_guardado_y_no_el_objeto_antiguo(): void
    {
        $actor = User::factory()->create(['rol' => RolCuenta::Administrador]);
        $destino = User::factory()->create();
        User::whereKey($actor->id)->update(['rol' => 'visualizador']);
        $this->expectException(HttpException::class);
        app(AdministrarCuenta::class)->cambiar($actor, $destino, RolCuenta::Administrador);
    }

    public function test_suspension_y_cambio_de_version_invalidan_sesiones_abiertas(): void
    {
        foreach ([['activo' => false], ['sesion_version' => 2]] as $cambio) {
            $usuario = User::factory()->create($cambio);
            $this->actingAs($usuario)->withSession(['cuenta_version' => 1])->get('/cuenta')->assertRedirect('/login');
            $this->assertGuest();
        }
    }

    public function test_una_cuenta_suspendida_no_puede_ingresar(): void
    {
        $usuario = User::factory()->create(['activo' => false]);
        $this->post('/login', ['email' => $usuario->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_el_nombre_es_editable_pero_los_permisos_y_el_correo_no(): void
    {
        $usuario = User::factory()->create();
        $this->actingAs($usuario)->put('/cuenta/nombre', ['name' => 'Nombre nuevo'])->assertSessionHasNoErrors();
        $this->assertSame('Nombre nuevo', $usuario->fresh()->name);
        $this->put('/cuenta/nombre', ['name' => 'Otro', 'rol' => 'admin', 'activo' => false, 'email' => 'otro@example.test'])->assertSessionHasErrors(['rol', 'activo', 'email']);
        $this->assertSame(RolCuenta::Visualizador, $usuario->fresh()->rol);
    }

    public function test_el_primer_administrador_requiere_confirmacion_local_y_no_reemplaza_otro(): void
    {
        $usuario = User::factory()->create();
        $this->artisan('geosur:primer-admin', ['email' => $usuario->email])->assertFailed();
        $this->assertSame(RolCuenta::Visualizador, $usuario->fresh()->rol);
        $this->artisan('geosur:primer-admin', ['email' => $usuario->email, '--confirmar' => true])->assertSuccessful();
        $this->assertSame(RolCuenta::Administrador, $usuario->fresh()->rol);
        $otro = User::factory()->create();
        $this->artisan('geosur:primer-admin', ['email' => $otro->email, '--confirmar' => true])->assertFailed();
        $this->app->instance('env', 'production');
        $this->artisan('geosur:primer-admin', ['email' => $otro->email, '--confirmar' => true])->assertFailed();
        $this->assertSame(RolCuenta::Visualizador, $otro->fresh()->rol);
    }

    public function test_la_ubicacion_se_guarda_y_se_quita_desde_el_formulario_y_exige_csrf(): void
    {
        $usuario = User::factory()->create();
        $this->actingAs($usuario)->put('/cuenta/preferencias', ['latitud' => -17.78, 'longitud' => -63.18])->assertSessionHasNoErrors();
        $this->assertSame(-17.78, $usuario->fresh()->preferencias->latitud);
        $this->put('/cuenta/preferencias', ['latitud' => '', 'longitud' => ''])->assertSessionHasNoErrors();
        $this->assertNull($usuario->fresh()->preferencias->latitud);
        $this->app->instance('env', 'local');
        $this->put('/cuenta/nombre', ['name' => 'Sin token'])->assertStatus(419);
        $this->put('/cuenta/preferencias', ['latitud' => -17, 'longitud' => -63])->assertStatus(419);
        $this->assertNotSame('Sin token', $usuario->fresh()->name);
    }
}
