<?php

namespace Tests\Feature\Sequias;

use App\Services\Sequias\Experimental\CapturadorPrecipitacion;
use App\Services\Sequias\Experimental\ClienteOpenMeteoPropuesto;
use App\Services\Sequias\RepositorioSeriesPrecipitacion;
use App\Services\Sequias\SeriePrecipitacionInvalida;
use App\Services\Sequias\ValidadorSeriePrecipitacion;
use Carbon\Carbon;
use DateTimeImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CapturadorPrecipitacionTest extends TestCase
{
    private string $directorio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directorio = base_path('../tmp/tests-captura-'.bin2hex(random_bytes(12)));
        mkdir($this->directorio, 0700, true);
        config(['sequias.directorio_series' => $this->directorio.'/almacen']);
        app()->forgetInstance(RepositorioSeriesPrecipitacion::class);
        Cache::flush();
        $this->travelTo(Carbon::parse('2026-10-23T12:00:00Z'));
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        $ruta = realpath($this->directorio);
        $padre = realpath(base_path('../tmp'));
        if ($ruta !== false) {
            if ($padre === false || ! str_starts_with($ruta, $padre.DIRECTORY_SEPARATOR)
                || ! preg_match('/^tests-captura-[a-f0-9]{24}$/D', basename($ruta))) {
                throw new \LogicException('No se puede limpiar una ruta ajena a esta prueba.');
            }
            File::deleteDirectory($ruta);
        }
        parent::tearDown();
    }

    private function documento(): array
    {
        return [...json_decode(file_get_contents(base_path('../docs/ejemplos/sequias/serie-completa.json')), true, flags: JSON_THROW_ON_ERROR),
            'latitude' => -17.75, 'longitude' => -63.25];
    }

    private function crudo(?array $datos = null): string
    {
        return json_encode($datos ?? $this->documento(), JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR)."\n";
    }

    private function simular(string $cuerpo): void
    {
        Http::fake(fn () => Http::response($cuerpo, 200,
            ['Date' => 'Fri, 23 Oct 2026 12:00:00 GMT', 'Content-Type' => 'application/json']));
    }

    private function capturar(bool $renovar = false, string $muestra = 'piloto-controlado'): array
    {
        return app(CapturadorPrecipitacion::class)->ejecutar($muestra, '-63.1821', '-17.7833', '2024-06-01', '2024-06-30', $renovar);
    }

    private function clave(string $muestra = 'piloto-controlado'): string
    {
        $s = app(ClienteOpenMeteoPropuesto::class)->solicitud($muestra, '-63.1821', '-17.7833', '2024-06-01', '2024-06-30', new DateTimeImmutable('2026-10-23T12:00:00Z'));

        return 'sequias:captura:v1:'.$s['huella'];
    }

    private function error(string $codigo, callable $accion): void
    {
        try {
            $accion();
            $this->fail('La captura inválida se aceptó.');
        } catch (SeriePrecipitacionInvalida $e) {
            $this->assertSame($codigo, $e->codigo);
            $this->assertStringNotContainsString('secreto-controlado', $e->getMessage());
        }
    }

    public function test_descarga_parametros_fijos_bytes_exactos_y_procedencia_sin_confundir_celda_solicitada(): void
    {
        $crudo = $this->crudo();
        $this->simular($crudo);
        Http::beforeSending(function (Request $peticion, array $opciones) {
            $this->assertFalse($opciones['allow_redirects']);
            $this->assertTrue($opciones['verify'] ?? true);
            $this->assertSame(5, $opciones['connect_timeout']);
            $this->assertSame(30, $opciones['timeout']);
            $this->assertSame(10, $opciones['read_timeout']);
        });
        $r = $this->capturar();
        $this->assertSame('consulta_proveedor', $r['tipoObtencion']);
        $this->assertTrue($r['esConsultaNueva']);
        $this->assertSame('2026-10-23T18:00:00Z', $r['expiraEn']);
        $v = $r['version'];
        $this->assertSame('verificada', $v['integridad']);
        $this->assertSame(60.0, $v['informe']['meses'][0]['precipitacionAcumuladaMm']);
        $this->assertSame(hash('sha256', $crudo), $v['procedencia']['sha256']);
        $this->assertSame($crudo, file_get_contents($this->directorio.'/almacen/'.$v['identificador'].'/captura.json'));
        $this->assertSame([-63.1821, -17.7833], $v['procedencia']['coordenadaSolicitada']);
        $this->assertSame([-63.25, -17.75], $v['procedencia']['coordenadaCelda']);
        $this->assertSame('reanalisis', $v['procedencia']['naturaleza']);
        $this->assertSame('2026-10-23T12:00:00.000000Z', $v['procedencia']['obtenidoEn']);
        $this->assertSame(200, $r['httpOriginal']['estado']);
        Http::assertSentCount(1);
        Http::assertSent(function (Request $req) use ($v) {
            parse_str(parse_url($req->url(), PHP_URL_QUERY), $p);

            return $req->method() === 'GET' && $req->url() === $v['procedencia']['urlOrigen']
                && $p === ['latitude' => '-17.7833', 'longitude' => '-63.1821', 'daily' => 'precipitation_sum', 'models' => 'era5',
                    'timezone' => 'GMT', 'precipitation_unit' => 'mm', 'cell_selection' => 'nearest', 'elevation' => 'nan',
                    'start_date' => '2024-06-01', 'end_date' => '2024-06-30'];
        });
    }

    public function test_reutiliza_sin_red_conservando_fecha_y_renovar_crea_otra_version_sin_borrar_anterior(): void
    {
        $datos = $this->documento();
        $modificado = $datos;
        $modificado['daily']['precipitation_sum'][0] = 5;
        Http::fake([ClienteOpenMeteoPropuesto::URL.'*' => Http::sequence()->push($this->crudo($datos), 200)->push($this->crudo($modificado), 200)]);
        $a = $this->capturar();
        $this->travel(60)->seconds();
        $b = $this->capturar();
        $this->assertSame('cache_captura', $b['tipoObtencion']);
        $this->assertFalse($b['esConsultaNueva']);
        $this->assertSame($a['version'], $b['version']);
        Http::assertSentCount(1);
        $c = $this->capturar(true);
        $this->assertNotSame($a['version']['identificador'], $c['version']['identificador']);
        $this->assertSame(63.0, $c['version']['informe']['meses'][0]['precipitacionAcumuladaMm']);
        $this->assertSame(60.0, app(RepositorioSeriesPrecipitacion::class)->leer($a['version']['identificador'])['informe']['meses'][0]['precipitacionAcumuladaMm']);
        $this->assertSame($c['version'], $this->capturar()['version']);
        Http::assertSentCount(2);
    }

    public function test_captura_expirada_vuelve_al_proveedor_y_no_inventa_una_fecha_reciente_para_datos_viejos(): void
    {
        $this->simular($this->crudo());
        $a = $this->capturar();
        $this->travel(CapturadorPrecipitacion::VIGENCIA_SEGUNDOS + 1)->seconds();
        $b = $this->capturar();
        $this->assertSame('consulta_proveedor', $b['tipoObtencion']);
        $this->assertNotSame($a['version']['identificador'], $b['version']['identificador']);
        $this->assertNotSame($a['version']['procedencia']['obtenidoEn'], $b['version']['procedencia']['obtenidoEn']);
        Http::assertSentCount(2);
    }

    public function test_faltantes_y_null_se_guardan_como_incompletos_con_vigencia_menor_y_salida_uno(): void
    {
        $datos = $this->documento();
        unset($datos['daily']['time'][9], $datos['daily']['precipitation_sum'][9]);
        $datos['daily']['time'] = array_values($datos['daily']['time']);
        $datos['daily']['precipitation_sum'] = array_values($datos['daily']['precipitation_sum']);
        $datos['daily']['precipitation_sum'][18] = null;
        $this->simular($this->crudo($datos));
        $r = $this->capturar();
        $this->assertSame('incompleta', $r['version']['informe']['estado']);
        $this->assertNull($r['version']['informe']['meses'][0]['precipitacionAcumuladaMm']);
        $this->assertSame('2026-10-23T12:15:00Z', $r['expiraEn']);
        $this->assertSame($r['version'], $this->capturar()['version']);
        $this->assertSame(1, Artisan::call('sequias:capturar-serie', ['muestra' => 'piloto-controlado', '--longitud' => '-63.1821', '--latitud' => '-17.7833', '--desde' => '2024-06-01', '--hasta' => '2024-06-30']));
        Http::assertSentCount(1);
    }

    public static function entradasInvalidas(): iterable
    {
        yield 'muestra' => ['muestra', '../x', 'MUESTRA_INVALIDA'];
        yield 'latitud' => ['latitud', '91', 'COORDENADA_INVALIDA'];
        yield 'longitud' => ['longitud', '-181', 'COORDENADA_INVALIDA'];
        yield 'coma' => ['latitud', '-17,7', 'COORDENADA_INVALIDA'];
        yield 'lista' => ['latitud', ['-17'], 'COORDENADA_INVALIDA'];
        yield 'precision' => ['latitud', '-17.1234567', 'COORDENADA_INVALIDA'];
        yield 'exponente' => ['latitud', '1e1', 'COORDENADA_INVALIDA'];
        yield 'infinito' => ['latitud', INF, 'COORDENADA_INVALIDA'];
        yield 'dia-inicio' => ['desde', '2024-06-02', 'RANGO_NO_MENSUAL'];
        yield 'fecha-imposible' => ['hasta', '2024-06-31', 'FECHA_INVALIDA'];
        yield 'rango-invertido' => ['desde', '2025-06-01', 'RANGO_NO_MENSUAL'];
        yield 'mes-actual' => ['hasta', '2026-10-31', 'PERIODO_NO_DISPONIBLE'];
        yield 'antes-de-era5-rango-excesivo' => ['desde', '1939-12-01', 'RANGO_DEMASIADO_GRANDE'];
        yield 'maximo-trabajo' => ['desde', '1940-01-01', 'RANGO_DEMASIADO_GRANDE'];
    }

    #[DataProvider('entradasInvalidas')]
    public function test_rechaza_entrada_antes_de_red_o_almacen(string $campo, mixed $valor, string $error): void
    {
        $p = ['muestra' => 'piloto', 'longitud' => '-63.1821', 'latitud' => '-17.7833', 'desde' => '2024-06-01', 'hasta' => '2024-06-30'];
        $p[$campo] = $valor;
        $this->error($error, fn () => app(CapturadorPrecipitacion::class)->ejecutar(...$p));
        Http::assertNothingSent();
        $this->assertDirectoryDoesNotExist($this->directorio.'/almacen');
    }

    public static function respuestasInvalidas(): iterable
    {
        yield 'html' => ['html', 'RESPUESTA_PROVEEDOR_INVALIDA'];
        yield 'lista' => ['lista', 'RESPUESTA_PROVEEDOR_INVALIDA'];
        yield 'error' => ['error', 'RESPUESTA_PROVEEDOR_INVALIDA'];
        yield 'unidad' => ['unidad', 'UNIDAD_INVALIDA'];
        yield 'zona' => ['zona', 'ZONA_INVALIDA'];
        yield 'celda' => ['celda', 'RESPUESTA_PROVEEDOR_INVALIDA'];
        yield 'duplicado' => ['duplicado', 'FECHA_DUPLICADA'];
        yield 'fuera' => ['fuera', 'FECHA_FUERA_DE_RANGO'];
        yield 'negativo' => ['negativo', 'PRECIPITACION_INVALIDA'];
    }

    #[DataProvider('respuestasInvalidas')]
    public function test_no_publica_ni_cachea_respuesta_estructuralmente_invalida(string $tipo, string $error): void
    {
        $datos = $this->documento();
        switch ($tipo) {
            case 'unidad': $datos['daily_units']['precipitation_sum'] = 'inch';
                break;
            case 'zona': $datos['utc_offset_seconds'] = -14400;
                break;
            case 'celda': $datos['latitude'] = 'secreto-controlado';
                break;
            case 'duplicado': $datos['daily']['time'][1] = $datos['daily']['time'][0];
                break;
            case 'fuera': $datos['daily']['time'][0] = '2024-05-31';
                break;
            case 'negativo': $datos['daily']['precipitation_sum'][0] = -1;
                break;
        }
        $cuerpo = match ($tipo) {
            'html' => '<html>secreto-controlado</html>', 'lista' => '[]', 'error' => '{"error":true,"reason":"secreto-controlado"}', default => $this->crudo($datos),
        };
        $this->simular($cuerpo);
        $this->error($error, fn () => $this->capturar());
        Http::assertSentCount(1);
        $this->assertNull(Cache::get($this->clave()));
        $this->assertDirectoryDoesNotExist($this->directorio.'/almacen');
    }

    public function test_error_red_cuota_redireccion_y_error_http_no_reintentan_ni_exponen_cuerpo(): void
    {
        $estado = 302;
        Http::fake(function () use (&$estado) {
            return Http::response('secreto-controlado', $estado, ['Location' => 'https://example.org/privado']);
        });
        foreach ([302, 429, 500] as $estado) {
            $this->error($estado === 429 ? 'CUOTA_PROVEEDOR' : 'PROVEEDOR_NO_DISPONIBLE', fn () => $this->capturar());
            $this->assertNull(Cache::get($this->clave()));
        }
        Http::assertSentCount(3);
        $this->assertDirectoryDoesNotExist($this->directorio.'/almacen');
    }

    public function test_error_de_conexion_no_expone_detalles_y_periodo_previo_a_1940_no_llega_a_red(): void
    {
        $this->error('PERIODO_NO_DISPONIBLE', fn () => app(CapturadorPrecipitacion::class)->ejecutar('piloto', -63, -17, '1939-12-01', '1939-12-31'));
        Http::assertNothingSent();
        Http::fake([ClienteOpenMeteoPropuesto::URL.'*' => Http::failedConnection('secreto-controlado')]);
        $this->error('PROVEEDOR_NO_DISPONIBLE', fn () => $this->capturar());
        $this->assertDirectoryDoesNotExist($this->directorio.'/almacen');
    }

    public function test_lectura_mayor_a_ocho_mib_no_se_guarda_parcialmente(): void
    {
        $this->simular(str_repeat(' ', 8 * 1024 * 1024 + 1));
        $this->error('RESPUESTA_DEMASIADO_GRANDE', fn () => $this->capturar());
        $this->assertDirectoryDoesNotExist($this->directorio.'/almacen');
    }

    public function test_captura_ocupada_y_fallo_del_proveedor_liberan_bloqueo_sin_tapar_error(): void
    {
        $bloqueo = Cache::lock($this->clave().':bloqueo', 120);
        $this->assertTrue($bloqueo->get());
        $this->error('CAPTURA_OCUPADA', fn () => $this->capturar());
        Http::assertNothingSent();
        $bloqueo->release();
        Http::fake([ClienteOpenMeteoPropuesto::URL.'*' => Http::sequence()->push('secreto-controlado', 500)->push($this->crudo(), 200)]);
        $this->error('PROVEEDOR_NO_DISPONIBLE', fn () => $this->capturar());
        $this->assertTrue($this->capturar()['esConsultaNueva']);
        Http::assertSentCount(2);
    }

    public function test_cache_corrupta_no_se_sustituye_silenciosamente_y_renovar_fallido_no_entrega_dato_viejo(): void
    {
        Http::fake([ClienteOpenMeteoPropuesto::URL.'*' => Http::sequence()->push($this->crudo(), 200)->push('secreto-controlado', 500)]);
        $a = $this->capturar();
        $this->error('PROVEEDOR_NO_DISPONIBLE', fn () => $this->capturar(true));
        $this->assertSame('cache_captura', $this->capturar()['tipoObtencion']);
        file_put_contents($this->directorio.'/almacen/'.$a['version']['identificador'].'/captura.json', '{}');
        $this->error('SERIE_CORRUPTA', fn () => $this->capturar());
        Http::assertSentCount(2);
        Cache::put($this->clave(), 'alterado', 60);
        $this->error('INDICE_CAPTURA_INVALIDO', fn () => $this->capturar());
        Http::assertSentCount(2);
    }

    public function test_consola_muestra_fuente_e_integridad_sin_habilitar_api(): void
    {
        $this->simular($this->crudo());
        $p = ['muestra' => 'piloto-controlado', '--longitud' => '-63.1821', '--latitud' => '-17.7833', '--desde' => '2024-06-01', '--hasta' => '2024-06-30'];
        $this->assertSame(0, Artisan::call('sequias:capturar-serie', $p));
        $r = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('captura_candidata_pendiente_revision', $r['estado']);
        $this->assertSame('verificada', $r['version']['integridad']);
        $this->assertArrayNotHasKey('meses', $r['version']['informe']);
        $this->assertSame(2, Artisan::call('sequias:capturar-serie', [...$p, '--desde' => '']));
        $this->assertArrayHasKey('error', json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR));
        $this->getJson('/api/v1/inundaciones')->assertStatus(503)->assertJsonPath('error.codigo', 'MODULO_NO_DISPONIBLE');
        Http::assertSentCount(1);
    }

    public function test_expirada_con_proveedor_caido_no_entrega_automaticamente_el_archivo_antiguo(): void
    {
        Http::fake([ClienteOpenMeteoPropuesto::URL.'*' => Http::sequence()->push($this->crudo(), 200)->push('secreto-controlado', 503)]);
        $a = $this->capturar();
        $this->travel(CapturadorPrecipitacion::VIGENCIA_SEGUNDOS + 1)->seconds();
        $this->error('PROVEEDOR_NO_DISPONIBLE', fn () => $this->capturar());
        $this->assertNull(Cache::get($this->clave()));
        $this->assertSame(60.0, app(RepositorioSeriesPrecipitacion::class)->leer($a['version']['identificador'])['informe']['meses'][0]['precipitacionAcumuladaMm']);
        Http::assertSentCount(2);
    }

    public function test_indice_apuntando_a_otra_muestra_es_rechazado_sin_descargar(): void
    {
        $this->simular($this->crudo());
        $this->capturar();
        $this->capturar(muestra: 'otra-muestra');
        Cache::put($this->clave(), Cache::get($this->clave('otra-muestra')), 60);
        $this->error('INDICE_CAPTURA_INVALIDO', fn () => $this->capturar());
        Http::assertSentCount(2);
    }

    public function test_evidencia_real_conserva_bytes_calendario_y_distincion_descarga_reuso(): void
    {
        $raiz = base_path('../docs/evidencias/api02-fuente/');
        $nueva = json_decode(file_get_contents($raiz.'consulta-nueva.json'), true, flags: JSON_THROW_ON_ERROR);
        $reuso = json_decode(file_get_contents($raiz.'reutilizada.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertTrue($nueva['esConsultaNueva']);
        $this->assertSame('consulta_proveedor', $nueva['tipoObtencion']);
        $this->assertFalse($reuso['esConsultaNueva']);
        $this->assertSame('cache_captura', $reuso['tipoObtencion']);
        $this->assertSame($nueva['version'], $reuso['version']);
        $p = $nueva['version']['procedencia'];
        $this->assertSame($p['sha256'], hash_file('sha256', $raiz.'captura-objetivo.json'));
        $datos = json_decode(file_get_contents($raiz.'captura-objetivo.json'), true, flags: JSON_THROW_ON_ERROR);
        $informe = app(ValidadorSeriePrecipitacion::class)->validar($datos, $p['periodo']['desde'], $p['periodo']['hastaIncluido']);
        $this->assertSame(92, $informe['resumen']['diasValidos']);
        $this->assertSame(0, $informe['resumen']['mesesIncompletos']);
        unset($informe['meses']);
        $this->assertSame($nueva['version']['informe'], $informe);
        Http::assertNothingSent();
    }
}
