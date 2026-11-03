<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AccesoTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_inicio_conserva_marca_acceso_y_modulos_sin_cifras_ficticias(): void
    {
        $this->get('/')->assertOk()->assertSee('GeoSur')->assertSee('Iniciar Sesión')
            ->assertSee('Variables ambientales que consultamos')->assertSee('href="#plataforma"', false)
            ->assertDontSeeText('Módulos')->assertDontSeeText('EnviroSense')
            ->assertSee(route('register'), false)->assertSee('Explorar el Mapa')
            ->assertSee('css/envirosense/fuentes.css')->assertDontSee('45K+')
            ->assertDontSee('en preparación');
        $this->get('/login')->assertOk()->assertSee('name="_token"', false);
        $this->get('/register')->assertOk()->assertSee('autocomplete="new-password"', false);
        Http::assertNothingSent();
    }

    public function test_el_mapa_y_los_modulos_requieren_sesion_y_conservan_el_destino(): void
    {
        foreach (['/mapa', '/modulos/focos-calor', '/modulos/sequias?mesFinal=2024-09'] as $ruta) {
            $this->get($ruta)->assertRedirect(route('login'));
            $this->assertSame(url($ruta), session('url.intended'));
        }
    }

    public function test_ingresar_regenera_sesion_y_vuelve_al_modulo_solicitado(): void
    {
        $usuario = User::factory()->create(['email' => 'equipo@example.test']);
        $this->get('/modulos/sequias?mesFinal=2024-09');
        $sesionAnterior = session()->getId();
        $this->post('/login', ['email' => ' EQUIPO@EXAMPLE.TEST ', 'password' => 'password'])
            ->assertRedirect(url('/modulos/sequias?mesFinal=2024-09'));
        $this->assertAuthenticatedAs($usuario);
        $this->assertNotSame($sesionAnterior, session()->getId());
    }

    public function test_el_error_de_credenciales_es_generico_y_no_guarda_la_contrasena(): void
    {
        User::factory()->create(['email' => 'equipo@example.test']);
        foreach (['equipo@example.test', 'noexiste@example.test'] as $correo) {
            $this->from('/login')->post('/login', ['email' => $correo, 'password' => 'equivocada'])
                ->assertRedirect('/login')->assertSessionHasErrors(['email' => 'El correo o la contraseña no coinciden.']);
            $this->assertGuest();
            $this->assertNull(session('_old_input.password'));
        }
    }

    public function test_se_limita_el_intento_repetido_aunque_las_credenciales_luego_sean_correctas(): void
    {
        User::factory()->create(['email' => 'equipo@example.test']);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'equipo@example.test', 'password' => 'incorrecta']);
        }
        $this->post('/login', ['email' => 'equipo@example.test', 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'Espera un minuto y vuelve a intentarlo.']);
        $this->assertGuest();
        $this->travel(61)->seconds();
        $this->post('/login', ['email' => 'equipo@example.test', 'password' => 'password'])->assertRedirect('/mapa');
        $this->assertAuthenticated();
    }

    public function test_el_registro_normaliza_el_correo_y_guarda_solo_la_clave_protegida(): void
    {
        $password = 'Una-frase-larga-2026';
        $this->post('/register', ['name' => 'Prueba', 'email' => ' EQUIPO@EXAMPLE.TEST ', 'password' => $password, 'password_confirmation' => $password, 'is_admin' => true])
            ->assertRedirect('/mapa');
        $usuario = User::firstOrFail();
        $this->assertSame('equipo@example.test', $usuario->email);
        $this->assertNotSame($password, $usuario->password);
        $this->assertTrue(Hash::check($password, $usuario->password));
        $this->assertArrayNotHasKey('password', $usuario->toArray());
        $this->assertArrayNotHasKey('is_admin', $usuario->getAttributes());
        $this->assertAuthenticatedAs($usuario);
        $this->assertNull(session('_old_input.password'));
    }

    public function test_el_registro_rechaza_correo_repetido_clave_corta_y_confirmacion_distinta(): void
    {
        User::factory()->create(['email' => 'equipo@example.test']);
        $this->post('/register', ['name' => 'Prueba', 'email' => 'EQUIPO@example.test', 'password' => 'corta', 'password_confirmation' => 'otra'])
            ->assertSessionHasErrors(['email', 'password']);
        $this->assertDatabaseCount('users', 1);
        $this->assertGuest();
    }

    public function test_registro_guarda_ubicacion_opcional_y_consentimiento_sin_enviar_avisos(): void
    {
        config(['avisos.email.habilitado' => true, 'avisos.email.mailer' => 'log']);
        $clave = 'Una-frase-larga-2026';
        $this->post('/register', ['name' => 'Prueba', 'email' => 'ubicacion@example.test', 'password' => $clave,
            'password_confirmation' => $clave, 'latitud' => -17.78, 'longitud' => -63.18, 'notif_email' => true, 'consentimiento' => true])
            ->assertRedirect('/mapa');
        $usuario = User::firstOrFail();
        $this->assertSame('visualizador', $usuario->rol->value);
        $this->assertSame(-17.78, $usuario->preferencias->latitud);
        $this->assertTrue($usuario->preferencias->notif_email);
        $this->assertDatabaseHas('suscripciones_canal_avisos', ['usuario_id' => $usuario->id, 'canal' => 'email', 'activo' => true]);
        $this->assertDatabaseCount('envios_avisos', 0);
        Http::assertNothingSent();
    }

    public function test_un_registro_con_avisos_invalidos_no_deja_una_cuenta_creada_a_medias(): void
    {
        $clave = 'Una-frase-larga-2026';
        $base = ['name' => 'Prueba', 'email' => 'sin-consentir@example.test', 'password' => $clave, 'password_confirmation' => $clave];
        foreach ([['latitud' => -17], ['latitud' => 200, 'longitud' => 10], ['notif_email' => true],
            ['latitud' => -17, 'longitud' => -63, 'notif_email' => true],
            ['latitud' => -17, 'longitud' => -63, 'notif_telegram' => true, 'consentimiento' => true, 'telegram_destino' => '12345'],
        ] as $extras) {
            $this->from('/register')->post('/register', $base + $extras)->assertRedirect('/register')->assertSessionHasErrors();
            $this->assertGuest();
            $this->assertDatabaseCount('users', 0);
            $this->assertDatabaseCount('preferencias_cuenta', 0);
            $this->assertDatabaseCount('suscripciones_canal_avisos', 0);
        }
        Http::assertNothingSent();
    }

    public function test_la_clave_unicode_demasiado_larga_y_los_tipos_invalidos_se_rechazan_sin_error_interno(): void
    {
        $clave = str_repeat('á', 40);
        $this->post('/register', ['name' => 'Prueba', 'email' => 'unicode@example.test', 'password' => $clave, 'password_confirmation' => $clave])
            ->assertSessionHasErrors('password');
        $this->post('/register', ['name' => [], 'email' => [], 'password' => [], 'password_confirmation' => []])
            ->assertSessionHasErrors(['name', 'email', 'password']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_salir_cierra_la_sesion_y_las_paginas_de_acceso_no_se_reabren_estando_dentro(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get('/login')->assertRedirect('/mapa');
        $this->get('/register')->assertRedirect('/mapa');
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
        $this->get('/mapa')->assertRedirect('/login');
        $this->get('/logout')->assertMethodNotAllowed();
    }

    public function test_los_formularios_rechazan_peticiones_sin_csrf_y_aceptan_el_token_de_sesion(): void
    {
        User::factory()->create(['email' => 'equipo@example.test']);
        // El middleware omite CSRF durante PHPUnit: activar aquí su comprobación real.
        $this->app->instance('env', 'local');
        $this->get('/login')->assertOk();
        $token = session()->token();
        $credenciales = ['email' => 'equipo@example.test', 'password' => 'password'];
        $this->post('/login', $credenciales)->assertStatus(419);
        $this->assertGuest();
        $this->post('/login', [...$credenciales, '_token' => $token])->assertRedirect('/mapa');
        $this->assertAuthenticated();
        $this->post('/logout')->assertStatus(419);
        $this->assertAuthenticated();
    }
}
