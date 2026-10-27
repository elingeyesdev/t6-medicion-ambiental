<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CuentaDemoTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_demo_se_crea_con_hash_y_puede_usar_el_acceso_normal(): void
    {
        $this->artisan('geocruz:crear-demo')
            ->expectsQuestion('Contraseña para la cuenta demo', 'Ejemplo.Prueba-42!')
            ->assertSuccessful();
        $usuario = User::where('email', 'demo@geocruz.test')->sole();
        $this->assertSame('Demo GeoCruz', $usuario->name);
        $this->assertTrue(Hash::check('Ejemplo.Prueba-42!', $usuario->password));
        $this->post('/login', ['email' => $usuario->email, 'password' => 'Ejemplo.Prueba-42!'])
            ->assertRedirect('/mapa');
        $this->assertAuthenticatedAs($usuario);
    }

    public function test_no_se_sobrescribe_una_cuenta_demo_existente(): void
    {
        $usuario = User::factory()->create(['email' => 'demo@geocruz.test']);
        $hash = $usuario->password;
        $this->artisan('geocruz:crear-demo')->assertFailed();
        $this->assertSame($hash, $usuario->fresh()->password);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_no_se_crea_una_demo_con_clave_debil(): void
    {
        $this->artisan('geocruz:crear-demo')
            ->expectsQuestion('Contraseña para la cuenta demo', 'corta')
            ->assertExitCode(2);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_el_comando_rechaza_entornos_de_produccion(): void
    {
        $this->app->instance('env', 'production');
        $this->artisan('geocruz:crear-demo')->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }
}
