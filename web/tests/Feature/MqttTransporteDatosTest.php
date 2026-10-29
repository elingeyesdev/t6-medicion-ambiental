<?php

namespace Tests\Feature;

use App\Enums\RolCuenta;
use App\Models\PuntoAmbiental;
use App\Models\User;
use App\Services\Api\ErrorConsulta;
use App\Services\Datos\Mqtt\ClienteMqtt;
use App\Services\Datos\Mqtt\ConfiguracionMqtt;
use App\Services\Datos\Mqtt\IngerirMensajeMqtt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MqttTransporteDatosTest extends TestCase
{
    use RefreshDatabase;

    private function broker(string $modo, callable $probar): array
    {
        $proceso = proc_open([PHP_BINARY, '-c', php_ini_loaded_file(), base_path('tests/Fixtures/Datos/mqtt-broker.php'), $modo], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $this->assertIsResource($proceso);
        stream_set_timeout($pipes[1], 5);
        $direccion = trim(fgets($pipes[1]));
        $this->assertMatchesRegularExpression('/^127\.0\.0\.1:[0-9]+$/', $direccion);
        try {
            $probar('mqtt://'.$direccion);
        } finally {
            $salida = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            $codigo = proc_close($proceso);
        }
        $this->assertSame('', $error);
        $this->assertSame(0, $codigo);

        return json_decode($salida, true, 16, JSON_THROW_ON_ERROR);
    }

    private function preparar(string $url): User
    {
        config(['datos.mqtt.habilitado' => true, 'datos.mqtt.url' => $url, 'datos.mqtt.temas' => [
            'geosur/ejemplo/temperatura' => ['punto' => 'campus', 'metrica' => 'temperatura', 'unidad' => '°C', 'origen' => 'simulado', 'fuente' => 'broker-prueba-local'],
        ]]);
        PuntoAmbiental::create(['codigo' => 'campus', 'nombre' => 'Contrato local', 'pais' => 'BO', 'latitud' => -17, 'longitud' => -63, 'tipo' => 'manual']);

        return User::factory()->create(['rol' => RolCuenta::Analista]);
    }

    public function test_tcp_local_negocia_qos1_guarda_antes_del_ack_y_deduplica_reenvio(): void
    {
        $r = $this->broker('normal', function ($url): void {
            $actor = $this->preparar($url);
            $this->artisan('geosur:mqtt-recibir', ['--actor' => $actor->id, '--conectar' => true, '--max-mensajes' => 2, '--segundos' => 3])->assertSuccessful();
            $this->assertDatabaseCount('lecturas_ambientales', 1);
            $this->assertDatabaseHas('lecturas_ambientales', ['registrado_por' => $actor->id, 'origen' => 'simulado', 'fuente' => 'broker-prueba-local']);
        });
        $this->assertSame([10, 10], $r['acks']);
        $this->assertTrue($r['connect']);
        $this->assertTrue($r['subscribe']);
    }

    public function test_sesion_persistente_admite_publicacion_antes_de_suback(): void
    {
        $r = $this->broker('antes-suback', function ($url): void {
            $actor = $this->preparar($url);
            $this->artisan('geosur:mqtt-recibir', ['--actor' => $actor->id, '--conectar' => true, '--segundos' => 3])->assertSuccessful();
        });
        $this->assertSame([10], $r['acks']);
        $this->assertDatabaseCount('lecturas_ambientales', 1);
    }

    public static function errores(): array
    {
        return array_map(fn ($modo) => [$modo], ['conexion-rechazada', 'suscripcion-rechazada', 'mensaje-invalido', 'tema-ajeno', 'truncado', 'excesivo', 'qos2', 'sin-suback']);
    }

    #[DataProvider('errores')]
    public function test_rechazo_o_error_no_guarda_ni_confirma(string $modo): void
    {
        $r = $this->broker($modo, function ($url) use ($modo): void {
            $actor = $this->preparar($url);
            $this->artisan('geosur:mqtt-recibir', ['--actor' => $actor->id, '--conectar' => true, '--segundos' => $modo === 'sin-suback' ? 1 : 3])->assertExitCode(2);
        });
        $this->assertSame([], $r['acks']);
        $this->assertDatabaseCount('lecturas_ambientales', 0);
    }

    public function test_fallo_de_persistencia_cierra_sin_puback(): void
    {
        $r = $this->broker('normal', function ($url): void {
            $actor = $this->preparar($url);
            PuntoAmbiental::where('codigo', 'campus')->update(['activo' => false]);
            $c = app(ConfiguracionMqtt::class);
            try {
                app(ClienteMqtt::class)->recibir($c->conexion(), array_keys($c->temas()), fn ($tema, $contenido) => app(IngerirMensajeMqtt::class)->ejecutar($tema, $contenido, $actor->id), 1, 3);
                $this->fail('Debió rechazar el punto inactivo.');
            } catch (ErrorConsulta|ValidationException) {
                $this->assertDatabaseCount('lecturas_ambientales', 0);
            }
        });
        $this->assertSame([], $r['acks']);
    }
}
