<?php

namespace Tests\Feature\Sequias;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ValidarSerieCommandTest extends TestCase
{
    private array $archivos = [];

    protected function tearDown(): void
    {
        foreach ($this->archivos as $archivo) {
            if (is_file($archivo)) {
                unlink($archivo);
            }
        }
        parent::tearDown();
    }

    private function archivo(string $contenido): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'geocruz-serie-');
        $this->assertNotFalse($ruta);
        file_put_contents($ruta, $contenido);
        $this->archivos[] = $ruta;

        return $ruta;
    }

    private function ejecutar(string $archivo, array $opciones = ['--desde' => '2024-02-01', '--hasta' => '2024-02-29']): array
    {
        $estado = Artisan::call('sequias:validar-serie', ['archivo' => $archivo, ...$opciones]);
        $salida = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        Http::assertNothingSent();

        return [$estado, $salida];
    }

    public function test_consola_informa_calidad_hash_y_no_modifica_archivo_ni_habilita_api(): void
    {
        $json = json_encode(ValidadorSeriePrecipitacionTest::serie(), JSON_THROW_ON_ERROR);
        $archivo = $this->archivo($json);
        [$estado, $r] = $this->ejecutar($archivo);
        $this->assertSame(0, $estado);
        $this->assertSame('completa', $r['estado']);
        $this->assertSame(hash('sha256', $json), $r['origen']['sha256']);
        $this->assertSame(strlen($json), $r['origen']['bytes']);
        $this->assertSame($json, file_get_contents($archivo));
        $this->getJson('/api/v1/inundaciones')->assertStatus(503)->assertJsonPath('error.codigo', 'MODULO_NO_DISPONIBLE');
        Http::assertNothingSent();
    }

    public function test_dia_faltante_devuelve_codigo_uno_e_informe_utilizable(): void
    {
        $s = ValidadorSeriePrecipitacionTest::serie();
        array_pop($s['daily']['time']);
        array_pop($s['daily']['precipitation_sum']);
        [$estado, $r] = $this->ejecutar($this->archivo(json_encode($s, JSON_THROW_ON_ERROR)));
        $this->assertSame(1, $estado);
        $this->assertSame(['2024-02-29'], $r['meses'][0]['fechasAusentes']);
        $this->assertNull($r['meses'][0]['precipitacionAcumuladaMm']);
    }

    public function test_json_invalido_error_controlado_sin_imprimir_su_contenido(): void
    {
        [$estado, $r] = $this->ejecutar($this->archivo('{"texto_privado":"No imprimir"'));
        $this->assertSame(2, $estado);
        $this->assertSame('JSON_INVALIDO', $r['error']['codigo']);
        $this->assertStringNotContainsString('No imprimir', json_encode($r));
    }

    public function test_estructura_raiz_incorrecta_no_produce_un_error_interno(): void
    {
        [$estado, $r] = $this->ejecutar($this->archivo('null'));
        $this->assertSame(2, $estado);
        $this->assertSame('ESTRUCTURA_INVALIDA', $r['error']['codigo']);
    }

    public function test_no_infiere_periodo_del_archivo(): void
    {
        [$estado, $r] = $this->ejecutar($this->archivo('{}'), []);
        $this->assertSame(2, $estado);
        $this->assertSame('PERIODO_REQUERIDO', $r['error']['codigo']);
    }

    public function test_archivo_inexistente_y_url_se_rechazan_sin_solicitudes_http(): void
    {
        foreach ([__DIR__.'/archivo-inexistente.json', 'https://archive-api.open-meteo.com/v1/archive'] as $archivo) {
            [$estado, $r] = $this->ejecutar($archivo);
            $this->assertSame(2, $estado);
            $this->assertSame('ARCHIVO_NO_DISPONIBLE', $r['error']['codigo']);
        }
    }

    public function test_limite_de_lectura_no_admite_archivos_mayores_a_ocho_mib(): void
    {
        [$estado, $r] = $this->ejecutar($this->archivo(str_repeat(' ', 8 * 1024 * 1024 + 1)));
        $this->assertSame(2, $estado);
        $this->assertSame('ARCHIVO_DEMASIADO_GRANDE', $r['error']['codigo']);
    }

    public function test_ejemplos_publicados_coinciden_con_los_informes_conservados(): void
    {
        foreach ([['completa', 'completa-controlada', 0], ['incompleta', 'incompleta-controlada', 1]] as [$ejemplo, $informe, $esperado]) {
            $archivo = base_path('../docs/ejemplos/sequias/serie-'.$ejemplo.'.json');
            [$codigo, $actual] = $this->ejecutar($archivo, ['--desde' => '2024-06-01', '--hasta' => '2024-06-30']);
            $guardado = json_decode(file_get_contents(base_path('../docs/evidencias/api02-preparacion/'.$informe.'.json')), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame($esperado, $codigo);
            $this->assertSame($guardado, $actual);
        }
    }
}
