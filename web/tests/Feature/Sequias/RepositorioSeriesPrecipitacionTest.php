<?php

namespace Tests\Feature\Sequias;

use App\Services\Sequias\ProcedenciaSeriePrecipitacion;
use App\Services\Sequias\RepositorioSeriesPrecipitacion;
use App\Services\Sequias\SeriePrecipitacionInvalida;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RepositorioSeriesPrecipitacionTest extends TestCase
{
    private string $directorio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directorio = base_path('../tmp/tests-sequias-'.bin2hex(random_bytes(12)));
        mkdir($this->directorio, 0700, true);
        config(['sequias.directorio_series' => $this->directorio.'/almacen']);
        app()->forgetInstance(RepositorioSeriesPrecipitacion::class);
    }

    protected function tearDown(): void
    {
        $ruta = realpath($this->directorio);
        $padre = realpath(base_path('../tmp'));
        if ($ruta !== false) {
            if ($padre === false || ! str_starts_with($ruta, $padre.DIRECTORY_SEPARATOR)
                || ! preg_match('/^tests-sequias-[a-f0-9]{24}$/D', basename($ruta))) {
                throw new \LogicException('No se puede limpiar una ruta ajena al temporal de prueba.');
            }
            File::deleteDirectory($ruta);
        }
        parent::tearDown();
    }

    private function archivo(string $tipo = 'completa'): string
    {
        return base_path('../docs/ejemplos/sequias/serie-'.$tipo.'.json');
    }

    private function procedencia(string $tipo = 'completa'): array
    {
        return json_decode(file_get_contents(base_path('../docs/ejemplos/sequias/procedencia-'.$tipo.'.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    private function repositorio(): RepositorioSeriesPrecipitacion
    {
        return app(RepositorioSeriesPrecipitacion::class);
    }

    private function guardarJson(string $nombre, array $datos): string
    {
        $ruta = $this->directorio.'/'.$nombre;
        file_put_contents($ruta, json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));

        return $ruta;
    }

    private function esperarError(string $codigo, callable $accion): void
    {
        try {
            $accion();
            $this->fail('La operación inválida fue aceptada.');
        } catch (SeriePrecipitacionInvalida $e) {
            $this->assertSame($codigo, $e->codigo);
        }
        Http::assertNothingSent();
    }

    public function test_publica_captura_exacta_y_reutiliza_sin_cambiar_la_fecha(): void
    {
        $r = $this->repositorio();
        $primera = $r->preparar($this->archivo(), $this->procedencia());
        $id = $primera['identificador'];
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $id);
        $this->assertFalse($primera['reutilizada']);
        $this->assertSame('verificada', $primera['integridad']);
        $this->assertSame(60.0, $primera['informe']['meses'][0]['precipitacionAcumuladaMm']);
        $ruta = $this->directorio.'/almacen/'.$id;
        $captura = file_get_contents($ruta.'/captura.json');
        $manifiesto = file_get_contents($ruta.'/manifiesto.json');
        $segunda = $r->preparar($this->archivo(), $this->procedencia());
        $this->assertTrue($segunda['reutilizada']);
        $this->assertSame($primera['preparadoEn'], $segunda['preparadoEn']);
        $this->assertSame($captura, file_get_contents($this->archivo()));
        $this->assertSame($manifiesto, file_get_contents($ruta.'/manifiesto.json'));
        $this->assertSame([], glob($this->directorio.'/almacen/.preparando-*'));
        Http::assertNothingSent();
    }

    public function test_cambiar_procedencia_crea_version_y_el_orden_de_claves_no(): void
    {
        $p = $this->procedencia();
        $r = $this->repositorio();
        $primera = $r->preparar($this->archivo(), $p);
        $ordenada = $r->preparar($this->archivo(), array_reverse($p, true));
        $this->assertSame($primera['identificador'], $ordenada['identificador']);
        $p['producto'] = 'otro-producto-controlado';
        $segunda = $r->preparar($this->archivo(), $p);
        $this->assertNotSame($primera['identificador'], $segunda['identificador']);
        $this->assertSame($primera['informe'], $r->leer($primera['identificador'])['informe']);
    }

    public function test_hash_erroneo_no_publica_ningun_archivo(): void
    {
        $p = $this->procedencia();
        $p['sha256'] = str_repeat('a', 64);
        $this->esperarError('HASH_NO_COINCIDE', fn () => $this->repositorio()->preparar($this->archivo(), $p));
        $this->assertDirectoryDoesNotExist($this->directorio.'/almacen');
    }

    public function test_datos_invalidos_no_se_publican_y_celda_declarada_se_contrasta(): void
    {
        $datos = json_decode(file_get_contents($this->archivo()), true, flags: JSON_THROW_ON_ERROR);
        $datos['latitude'] = -17.75;
        $datos['longitude'] = -63.25;
        $ruta = $this->guardarJson('con-celda.json', $datos);
        $p = $this->procedencia();
        $p['sha256'] = hash_file('sha256', $ruta);
        $buena = $this->repositorio()->preparar($ruta, $p);
        $this->assertSame([-63.25, -17.75], $buena['procedencia']['coordenadaCelda']);
        $p['coordenadaCelda'][0] = -62.25;
        $this->esperarError('CELDA_INCONSISTENTE', fn () => $this->repositorio()->preparar($ruta, $p));
        $datos['daily_units']['precipitation_sum'] = 'inch';
        $ruta = $this->guardarJson('unidad.json', $datos);
        $p = $this->procedencia();
        $p['sha256'] = hash_file('sha256', $ruta);
        $this->esperarError('UNIDAD_INVALIDA', fn () => $this->repositorio()->preparar($ruta, $p));
    }

    public function test_archivo_modificado_se_rechaza_sin_repararlo_silenciosamente(): void
    {
        $r = $this->repositorio();
        $v = $r->preparar($this->archivo(), $this->procedencia());
        $ruta = $this->directorio.'/almacen/'.$v['identificador'].'/captura.json';
        file_put_contents($ruta, "\n", FILE_APPEND);
        $this->esperarError('SERIE_CORRUPTA', fn () => $r->leer($v['identificador']));
        $this->esperarError('SERIE_CORRUPTA', fn () => $r->preparar($this->archivo(), $this->procedencia()));
        $this->assertSame(file_get_contents($this->archivo())."\n", file_get_contents($ruta));
    }

    public function test_el_informe_se_recalcula_aunque_su_checksum_haya_sido_actualizado(): void
    {
        $r = $this->repositorio();
        $v = $r->preparar($this->archivo(), $this->procedencia());
        $ruta = $this->directorio.'/almacen/'.$v['identificador'].'/manifiesto.json';
        $d = json_decode(file_get_contents($ruta), true, flags: JSON_THROW_ON_ERROR);
        $d['informe']['meses'][0]['precipitacionAcumuladaMm'] = 999.0;
        unset($d['checksumManifiesto']);
        $d['checksumManifiesto'] = hash('sha256', json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));
        file_put_contents($ruta, json_encode($d, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));
        $this->esperarError('SERIE_CORRUPTA', fn () => $r->leer($v['identificador']));
    }

    public function test_editar_instante_de_preparacion_invalida_checksum(): void
    {
        $r = $this->repositorio();
        $v = $r->preparar($this->archivo(), $this->procedencia());
        $ruta = $this->directorio.'/almacen/'.$v['identificador'].'/manifiesto.json';
        $d = json_decode(file_get_contents($ruta), true, flags: JSON_THROW_ON_ERROR);
        $d['preparadoEn'] = '2001-01-01T00:00:00.000000Z';
        file_put_contents($ruta, json_encode($d, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));
        $this->esperarError('SERIE_CORRUPTA', fn () => $r->leer($v['identificador']));
    }

    public function test_modificar_procedencia_no_puede_conservar_identificador_aunque_actualice_checksum(): void
    {
        $r = $this->repositorio();
        $v = $r->preparar($this->archivo(), $this->procedencia());
        $ruta = $this->directorio.'/almacen/'.$v['identificador'].'/manifiesto.json';
        $d = json_decode(file_get_contents($ruta), true, flags: JSON_THROW_ON_ERROR);
        $d['procedencia']['producto'] = 'declaracion-modificada';
        unset($d['checksumManifiesto']);
        $d['checksumManifiesto'] = hash('sha256', json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));
        file_put_contents($ruta, json_encode($d, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));
        $this->esperarError('SERIE_CORRUPTA', fn () => $r->leer($v['identificador']));
    }

    public function test_captura_faltante_y_directorio_incompleto_no_se_reutilizan(): void
    {
        $r = $this->repositorio();
        $v = $r->preparar($this->archivo(), $this->procedencia());
        unlink($this->directorio.'/almacen/'.$v['identificador'].'/captura.json');
        $this->esperarError('SERIE_CORRUPTA', fn () => $r->leer($v['identificador']));
        $otro = str_repeat('b', 64);
        mkdir($this->directorio.'/almacen/'.$otro);
        $this->esperarError('SERIE_CORRUPTA', fn () => $r->leer($otro));
    }

    public function test_bloqueo_ocupado_y_destino_inaccesible_dan_errores_controlados(): void
    {
        file_put_contents($this->directorio.'/almacen', 'no es un directorio');
        $this->esperarError('ALMACEN_NO_DISPONIBLE', fn () => $this->repositorio()->preparar($this->archivo(), $this->procedencia()));
        unlink($this->directorio.'/almacen');
        mkdir($this->directorio.'/almacen');
        $lock = fopen($this->directorio.'/almacen/.bloqueo', 'c+b');
        $this->assertTrue(flock($lock, LOCK_EX | LOCK_NB));
        try {
            $this->esperarError('ALMACEN_OCUPADO', fn () => $this->repositorio()->preparar($this->archivo(), $this->procedencia()));
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function test_identificador_desconocido_y_traversal_no_buscan_otras_rutas(): void
    {
        $this->esperarError('SERIE_NO_ENCONTRADA', fn () => $this->repositorio()->leer(str_repeat('a', 64)));
        foreach (['../serie', 'C:/Windows', 'https://example.invalid', str_repeat('A', 64)] as $id) {
            $this->esperarError('IDENTIFICADOR_INVALIDO', fn () => $this->repositorio()->leer($id));
        }
    }

    public function test_consola_conserva_incompletos_y_lee_resumen_o_detalle_sin_habilitar_api(): void
    {
        $argumentos = ['archivo' => $this->archivo('incompleta'),
            'procedencia' => base_path('../docs/ejemplos/sequias/procedencia-incompleta.json')];
        $this->assertSame(1, Artisan::call('sequias:preparar-serie', $argumentos));
        $v = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('incompleta', $v['informe']['estado']);
        $this->assertArrayNotHasKey('meses', $v['informe']);
        $this->assertSame(1, Artisan::call('sequias:ver-serie', ['identificador' => $v['identificador'], '--detalle' => true]));
        $detalle = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertNull($detalle['informe']['meses'][0]['precipitacionAcumuladaMm']);
        $this->assertSame(1, $detalle['informe']['resumen']['diasAusentes']);
        $this->assertSame(1, $detalle['informe']['resumen']['diasNulos']);
        $this->assertSame(2, Artisan::call('sequias:ver-serie', ['identificador' => '../no']));
        $this->assertSame('IDENTIFICADOR_INVALIDO', json_decode(Artisan::output(), true)['error']['codigo']);
        $this->getJson('/api/v1/sequias')->assertStatus(503)->assertJsonPath('error.codigo', 'MODULO_NO_DISPONIBLE');
        Http::assertNothingSent();
    }

    #[DataProvider('procedenciasInvalidas')]
    public function test_procedencia_invalida_no_crea_almacen(array $cambio): void
    {
        $p = array_replace($this->procedencia(), $cambio);
        $this->esperarError('PROCEDENCIA_INVALIDA', fn () => $this->repositorio()->preparar($this->archivo(), $p));
        $this->assertDirectoryDoesNotExist($this->directorio.'/almacen');
    }

    public static function procedenciasInvalidas(): array
    {
        return [
            [['tokenNoDocumentado' => 'no se guarda']],
            [['sha256' => 'incorrecto']],
            [['muestra' => '../../otra']],
            [['coordenadaCelda' => [181, 0]]],
            [['coordenadaSolicitada' => ['-63', -17]]],
            [['obtenidoEn' => '2024-07-01']],
            [['obtenidoEn' => '2024-02-30T00:00:00Z']],
            [['tipoEvidencia' => 'controlada', 'naturaleza' => 'observacion']],
            [['urlOrigen' => 'https://example.invalid/fixture-presentada-como-real']],
            [['periodo' => ['desde' => '2024-06-01', 'hastaIncluido' => '2024-06-30', 'zona' => 'America/La_Paz']]],
        ];
    }

    public function test_procedencia_externa_exige_url_https_y_preserva_el_instante_original(): void
    {
        $p = $this->procedencia();
        $p['tipoEvidencia'] = 'captura_externa';
        $p['naturaleza'] = 'reanalisis';
        $p['obtenidoEn'] = '2024-07-01T02:03:04.12Z';
        foreach ([null, 'http://example.invalid', 'https://usuario:clave@example.invalid'] as $url) {
            $p['urlOrigen'] = $url;
            $this->esperarError('PROCEDENCIA_INVALIDA', fn () => (new ProcedenciaSeriePrecipitacion)->normalizar($p));
        }
        $p['urlOrigen'] = 'https://example.invalid/captura-no-consultada';
        $normalizada = (new ProcedenciaSeriePrecipitacion)->normalizar($p);
        $this->assertSame('2024-07-01T02:03:04.120000Z', $normalizada['obtenidoEn']);
        Http::assertNothingSent();
    }
}
