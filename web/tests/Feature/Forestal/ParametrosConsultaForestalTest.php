<?php

declare(strict_types=1);

namespace Tests\Feature\Forestal;

use App\Services\Api\ErrorConsulta;
use App\Services\Forestal\DisponibilidadForestal;
use App\Services\Forestal\ParametrosConsultaForestal;
use Illuminate\Http\Request;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Pruebas del servicio en memoria: no arrancan Laravel ni despachan rutas. */
final class ParametrosConsultaForestalTest extends TestCase
{
    // Identidades ficticias de pruebas, sin equivalencia con territorios reales.
    private const TERRITORIO = 'departamento-prueba';

    private function disponibilidad(): DisponibilidadForestal
    {
        return new DisponibilidadForestal('version-prueba-2025', range(2001, 2025));
    }

    private function request(array $parametros = []): Request
    {
        return Request::create('/', 'GET', [...['territorio' => self::TERRITORIO, 'desde' => '2001', 'hasta' => '2025'], ...$parametros]);
    }

    private function exigirError(Request $request, string $codigo = 'FILTROS_INVALIDOS', int $estado = 422, ?DisponibilidadForestal $disponibilidad = null, ?array $territorios = null): void
    {
        try {
            (new ParametrosConsultaForestal)->leer($request, $disponibilidad ?? $this->disponibilidad(), $territorios ?? [self::TERRITORIO]);
            $this->fail('Se aceptó una entrada inválida.');
        } catch (ErrorConsulta $e) {
            $this->assertSame($codigo, $e->codigo);
            $this->assertSame($estado, $e->estado);
        }
    }

    public function test_intervalo_completo_inclusivo_y_cada_anio_publicado_son_validos(): void
    {
        $servicio = new ParametrosConsultaForestal;
        $disponibilidad = $this->disponibilidad();
        $this->assertSame(['territorio' => self::TERRITORIO, 'desde' => 2001, 'hasta' => 2025], $servicio->leer($this->request(), $disponibilidad, [self::TERRITORIO]));
        foreach (range(2001, 2025) as $anio) {
            $r = $servicio->leer($this->request(['desde' => (string) $anio, 'hasta' => (string) $anio]), $disponibilidad, [self::TERRITORIO]);
            $this->assertSame(['territorio' => self::TERRITORIO, 'desde' => $anio, 'hasta' => $anio], $r);
        }
        $this->assertSame(range(2001, 2025), $disponibilidad->anios);
        $this->assertSame('version-prueba-2025', $disponibilidad->version);
    }

    public function test_subperiodo_se_normaliza_sin_reducir_la_disponibilidad(): void
    {
        $disponibilidad = $this->disponibilidad();
        $r = (new ParametrosConsultaForestal)->leer($this->request(['desde' => '2024']), $disponibilidad, [self::TERRITORIO]);
        $this->assertSame(['territorio' => self::TERRITORIO, 'desde' => 2024, 'hasta' => 2025], $r);
        $this->assertSame(range(2001, 2025), $disponibilidad->anios);
    }

    public static function aniosInvalidos(): iterable
    {
        foreach (['2000', '2026', '0000', '9999', '2024-01-01', '2024-01', '2024-01-01T00:00:00Z',
            'true', 'false', '0', '-2024', '+2024', '2.024e3', '2024e0', '2024.0', '02024', '24',
            ' 2024', '2024 ', "2024\n", '２０２４', '', ' ', true, false, 2024, 2024.0, null, [], ['2024']] as $i => $valor) {
            foreach (['desde', 'hasta'] as $campo) {
                yield $campo.'-'.$i => [$campo, $valor];
            }
        }
    }

    #[DataProvider('aniosInvalidos')]
    public function test_rechaza_formatos_no_anuales_tipos_y_anios_fuera_de_version(string $campo, mixed $valor): void
    {
        // Cambiar directamente query permite probar booleanos/arrays reales sin coerción de URL.
        $request = $this->request();
        $request->query->set($campo, $valor);
        $this->exigirError($request);
    }

    public function test_rechaza_intervalo_invertido(): void
    {
        $this->exigirError($this->request(['desde' => '2025', 'hasta' => '2024']));
    }

    public static function camposObligatorios(): iterable
    {
        foreach (['territorio', 'desde', 'hasta'] as $campo) {
            yield $campo => [$campo];
        }
    }

    #[DataProvider('camposObligatorios')]
    public function test_no_completa_parametros_omitidos(string $campo): void
    {
        $request = $this->request();
        $request->query->remove($campo);
        $this->exigirError($request);
    }

    public function test_sin_disponibilidad_no_deduce_anios_de_la_fecha_actual(): void
    {
        try {
            (new ParametrosConsultaForestal)->leer($this->request(), null, [self::TERRITORIO]);
            $this->fail('Se aceptó una consulta sin disponibilidad.');
        } catch (ErrorConsulta $e) {
            $this->assertSame('PRODUCTO_NO_DISPONIBLE', $e->codigo);
            $this->assertSame(503, $e->estado);
        }
    }

    public function test_usa_los_anios_de_la_version_recibida_en_cada_lectura(): void
    {
        $servicio = new ParametrosConsultaForestal;
        $request = $this->request(['desde' => '2024', 'hasta' => '2025']);
        $this->assertSame(2025, $servicio->leer($request, $this->disponibilidad(), [self::TERRITORIO])['hasta']);
        $this->exigirError($request, disponibilidad: new DisponibilidadForestal('otra-version-prueba', [2003, 2004]));
        $this->assertSame(['territorio' => self::TERRITORIO, 'desde' => 2003, 'hasta' => 2004], $servicio->leer(
            $this->request(['desde' => '2003', 'hasta' => '2004']),
            new DisponibilidadForestal('otra-version-prueba', [2004, 2003]),
            [self::TERRITORIO],
        ));
    }

    public function test_rechaza_hueco_de_disponibilidad_aunque_los_extremos_existan(): void
    {
        $this->exigirError($this->request(['hasta' => '2003']), disponibilidad: new DisponibilidadForestal('version-con-hueco-prueba', [2001, 2003]));
    }

    public static function territoriosNoPublicados(): iterable
    {
        foreach (['municipio-prueba', 'departamento-prueba/municipio', 'DEPARTAMENTO-PRUEBA', 'departamento-prueba ', ' departamento-prueba', 'BOL/8', '', ' ', [], true, false, 8] as $i => $valor) {
            yield (string) $i => [$valor, is_string($valor) && trim($valor) !== '' ? 'TERRITORIO_SIN_COBERTURA' : 'FILTROS_INVALIDOS'];
        }
    }

    #[DataProvider('territoriosNoPublicados')]
    public function test_exige_identidad_exacta_sin_herencia_departamental(mixed $valor, string $codigo): void
    {
        $request = $this->request();
        $request->query->set('territorio', $valor);
        $this->exigirError($request, $codigo, $codigo === 'TERRITORIO_SIN_COBERTURA' ? 404 : 422);
    }

    public function test_lista_vacia_no_publica_un_departamento_por_defecto(): void
    {
        $this->exigirError($this->request(), 'TERRITORIO_SIN_COBERTURA', 404, territorios: []);
    }

    public function test_la_lista_recibida_decide_sin_consultar_catalogos_ni_convertir_identidades(): void
    {
        $servicio = new ParametrosConsultaForestal;
        $request = $this->request(['territorio' => 'municipio-prueba']);
        $this->assertSame('municipio-prueba', $servicio->leer($request, $this->disponibilidad(), ['municipio-prueba'])['territorio']);
        $this->exigirError($this->request(['territorio' => '08']), 'TERRITORIO_SIN_COBERTURA', 404, territorios: ['8']);
        $this->exigirError($this->request(['territorio' => '8']), 'TERRITORIO_SIN_COBERTURA', 404, territorios: [8]);
    }

    public static function consultasAmbiguas(): iterable
    {
        foreach (['territorio=departamento-prueba&territorio=departamento-prueba',
            'territorio=departamento-prueba&territorio=municipio-prueba',
            'desde=2001&desde=2001', 'hasta=2024&hasta=2025',
            'desde=2001&%64esde=2024', 'territorio=departamento-prueba&territ%6Frio=municipio-prueba',
            'desde[]=2001', 'hasta[anio]=2025', 'territorio[]=departamento-prueba',
            'dias=7', 'mes=2024-01', 'capa=perdida', 'desconocido=x'] as $i => $consulta) {
            yield (string) $i => [$consulta];
        }
    }

    #[DataProvider('consultasAmbiguas')]
    public function test_lee_request_y_rechaza_claves_repetidas_codificadas_desconocidas_y_arrays(string $consulta): void
    {
        $request = $this->request();
        // Conservar la query original aunque PHP/Symfony ya hayan colapsado claves repetidas.
        $request->server->set('QUERY_STRING', $consulta);
        $this->exigirError($request);
    }

    public function test_lee_una_query_real_sin_despachar_endpoint(): void
    {
        $request = Request::create('/?territorio=departamento-prueba&desde=2001&hasta=2025');
        $this->assertSame(['territorio' => self::TERRITORIO, 'desde' => 2001, 'hasta' => 2025], (new ParametrosConsultaForestal)->leer($request, $this->disponibilidad(), [self::TERRITORIO]));
    }

    public function test_detecta_duplicado_en_request_real_tras_colapso_de_php(): void
    {
        $request = Request::create('/?territorio=departamento-prueba&desde=2001&desde=2024&hasta=2025');
        $this->assertSame('2024', $request->query('desde'));
        $this->exigirError($request);
    }

    public static function disponibilidadesInvalidas(): iterable
    {
        foreach ([['', [2001]], [' ', [2001]], ['v', []], ['v', [1 => 2001]],
            ['v', [2001, 2001]], ['v', ['2001']], ['v', [true]], ['v', [false]],
            ['v', [2001.0]], ['v', [-2001]], ['v', [999]], ['v', [10000]],
            ['v', [null]], ['v', [[2001]]]] as $i => $caso) {
            yield (string) $i => $caso;
        }
    }

    #[DataProvider('disponibilidadesInvalidas')]
    public function test_disponibilidad_tipado_estricto_sin_coercion_de_sus_anios(string $version, array $anios): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DisponibilidadForestal($version, $anios);
    }
}
