<?php

declare(strict_types=1);

namespace Tests\Feature\Forestal;

use App\Services\Api\ErrorConsulta;
use App\Services\Forestal\RepositorioRevisionForestal;
use PHPUnit\Framework\TestCase;

/** Datos sintéticos controlados: las áreas ficticias NO son un producto aprobado. */
final class RepositorioRevisionForestalTest extends TestCase
{
    private string $directorio;

    protected function setUp(): void
    {
        $this->directorio = sys_get_temp_dir().'/forestal148-'.bin2hex(random_bytes(12));
        mkdir($this->directorio, 0700);
    }

    protected function tearDown(): void
    {
        $this->borrar($this->directorio);
    }

    private function borrar(string $ruta): void
    {
        if (is_link($ruta) || is_file($ruta)) {
            unlink($ruta);
        } elseif (is_dir($ruta)) {
            foreach (scandir($ruta) as $nombre) {
                if ($nombre !== '.' && $nombre !== '..') {
                    $this->borrar($ruta.'/'.$nombre);
                }
            }
            rmdir($ruta);
        }
    }

    private function sintetico(): array
    {
        $r = [
            'estado' => 'recorte_tecnico_no_validado', 'integrado_en_api' => false,
            'criterio' => ['base' => 2000, 'operador' => '>=', 'porcentaje' => 30, 'adoptado' => false],
            'consulta' => ['desde' => 2001, 'hasta' => 2025, 'extremos_incluidos' => true],
            'ventana_pixeles' => ['columna' => 25200, 'fila' => 28000, 'ancho' => 2, 'alto' => 2],
            'bbox' => [-63.7, -17.0005, -63.6995, -17.0],
            'conteos_pixeles' => ['sin_dato_base' => 0, 'agua_base' => 0, 'tierra_base' => 4,
                'arbolado_base' => 4, 'perdida_periodo' => 3],
            'serie' => [], 'total_periodo_ha' => 0.6, 'cobertura_observaciones_fraccion' => null,
            'cobertura_desconocida' => ['nota' => 'Sin observación anual acreditada', 'fraccion' => null],
            'limites' => ['SINTÉTICO CONTROLADO. Sin equivalencia municipal ni producto aprobado.'],
            'fuente' => ['producto' => 'Hansen GFC', 'version' => 'GFC-2025-v1.13', 'tesela' => '10S_070W',
                'captura_sha256' => str_repeat('a', 64), 'etag' => ['lossyear' => 'sintetico'],
                'cuadricula' => ['epsg' => 4326, 'pixel' => 'area', 'oeste' => -70, 'norte' => -10,
                    'paso_grados' => 0.00025, 'ancho' => 40000, 'alto' => 40000]],
        ];
        foreach (range(2001, 2025) as $anio) {
            $r['serie'][] = ['anio' => $anio, 'perdida_ha' => match ($anio) {
                2001 => 0.1, 2024 => 0.2, 2025 => 0.3, default => 0.0
            }];
        }
        $c = ['type' => 'FeatureCollection', 'features' => [
            $this->celda(28000, 25200, 2001, 0.1),
            $this->celda(28000, 25201, 2024, 0.2),
            $this->celda(28001, 25200, 2025, 0.3),
        ]];

        return [$r, $c];
    }

    private function celda(int $fila, int $columna, int $anio, float $area): array
    {
        $o = -70 + $columna * 0.00025;
        $n = -10 - $fila * 0.00025;
        $e = $o + 0.00025;
        $s = $n - 0.00025;

        return ['type' => 'Feature', 'id' => $fila.'-'.$columna,
            'properties' => ['anio' => $anio, 'area_ha' => $area],
            'geometry' => ['type' => 'Polygon', 'coordinates' => [[[$o, $n], [$o, $s], [$e, $s], [$e, $n], [$o, $n]]]]];
    }

    private function json(mixed $valor): string
    {
        return json_encode($valor, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE)."\n";
    }

    /** Todos los cambios semánticos se vuelven a hashear para probar las validaciones. */
    private function paquete(array $r, array $c, ?callable $cambiarManifiesto = null, ?string $resumenBytes = null, ?string $capaBytes = null): string
    {
        $resumenBytes ??= $this->json($r);
        $capaBytes ??= $this->json($c);
        $m = ['formato' => 'revision-forestal-1', 'estado' => 'revision_tecnica',
            'identidad' => ['fuente_version' => $r['fuente']['version'], 'tesela' => $r['fuente']['tesela'],
                'captura_sha256' => $r['fuente']['captura_sha256'], 'criterio' => $r['criterio'],
                'ventana_pixeles' => $r['ventana_pixeles'], 'consulta' => $r['consulta']],
            'archivos' => ['resumen.json' => hash('sha256', $resumenBytes), 'perdida.geojson' => hash('sha256', $capaBytes)]];
        if ($cambiarManifiesto !== null) {
            $cambiarManifiesto($m);
        }
        $manifiesto = $this->json($m);
        $id = hash('sha256', $manifiesto);
        $ruta = $this->directorio.'/'.$id;
        if (! is_dir($ruta)) {
            mkdir($ruta, 0700);
        }
        file_put_contents($ruta.'/manifiesto.json', $manifiesto);
        file_put_contents($ruta.'/resumen.json', $resumenBytes);
        file_put_contents($ruta.'/perdida.geojson', $capaBytes);

        return $id;
    }

    private function consultar(string $id, int $desde = 2024, int $hasta = 2025): array
    {
        return (new RepositorioRevisionForestal($this->directorio))->consultar($id, $desde, $hasta);
    }

    private function rechaza(callable $accion, string $codigo = 'PRODUCTO_INVALIDO', string $caso = ''): void
    {
        try {
            $accion();
            $this->fail('Se aceptó el caso inválido: '.$caso);
        } catch (ErrorConsulta $e) {
            $this->assertSame($codigo, $e->codigo, $caso);
            $this->assertSame($codigo === 'FILTROS_INVALIDOS' ? 422 : 503, $e->estado, $caso);
            $this->assertStringNotContainsString($this->directorio, $e->getMessage());
        }
    }

    public function test_filtra_resumen_y_capa_con_suma_decimal_y_conserva_metadatos(): void
    {
        [$r, $c] = $this->sintetico();
        $id = $this->paquete($r, $c);
        $bytes = file_get_contents($this->directorio.'/'.$id.'/resumen.json');
        $actual = $this->consultar($id);
        $esperado = $r;
        $esperado['consulta'] = ['desde' => 2024, 'hasta' => 2025, 'extremos_incluidos' => true];
        $esperado['serie'] = array_slice($r['serie'], -2);
        $esperado['total_periodo_ha'] = '0.50000000';
        $esperado['conteos_pixeles']['perdida_periodo'] = 2;
        $this->assertSame(['id', 'revision', 'resumen', 'capa'], array_keys($actual));
        $this->assertSame(['id' => $id, 'revision' => true, 'resumen' => $esperado,
            'capa' => ['type' => 'FeatureCollection', 'features' => array_slice($c['features'], 1)]], $actual);
        $this->assertNull($actual['resumen']['cobertura_observaciones_fraccion']);
        $this->assertFalse($actual['resumen']['criterio']['adoptado']);
        $this->assertSame($bytes, file_get_contents($this->directorio.'/'.$id.'/resumen.json'));
        $this->assertSame('0.60000000', $this->consultar($id, 2001, 2025)['resumen']['total_periodo_ha']);
    }

    public function test_cero_explicito_es_distinto_de_desconocido_y_no_arrastra_celdas(): void
    {
        [$r, $c] = $this->sintetico();
        $id = $this->paquete($r, $c);
        $a = $this->consultar($id, 2023, 2023);
        $this->assertSame('0.00000000', $a['resumen']['total_periodo_ha']);
        $this->assertSame([['anio' => 2023, 'perdida_ha' => 0.0]], $a['resumen']['serie']);
        $this->assertSame(0, $a['resumen']['conteos_pixeles']['perdida_periodo']);
        $this->assertSame([], $a['capa']['features']);
        $this->assertSame($r['cobertura_desconocida'], $a['resumen']['cobertura_desconocida']);
        $r['serie'][22]['perdida_ha'] = null;
        $this->rechaza(fn () => $this->consultar($this->paquete($r, $c), 2023, 2023));
    }

    public function test_no_suma_float_y_tolera_redondeo_independiente_de_celdas_y_anios(): void
    {
        [$r, $c] = $this->sintetico();
        $r['serie'][23]['perdida_ha'] = 0.20000001;
        $r['total_periodo_ha'] = 0.60000001;
        $id = $this->paquete($r, $c);
        $this->assertSame('0.50000001', $this->consultar($id)['resumen']['total_periodo_ha']);
        $r['serie'][23]['perdida_ha'] = 0.201;
        $r['total_periodo_ha'] = 0.601;
        $this->rechaza(fn () => $this->consultar($this->paquete($r, $c)));
    }

    public function test_rechaza_identificadores_y_consultas_fuera_de_disponibilidad(): void
    {
        [$r, $c] = $this->sintetico();
        $id = $this->paquete($r, $c);
        foreach (['../'.$id, strtoupper($id), $id.'/x', $id.' ', str_repeat('a', 63), $id."\0", 'file://'.$id, 'C:\\'.$id] as $malo) {
            $this->rechaza(fn () => $this->consultar($malo), 'FILTROS_INVALIDOS');
        }
        foreach ([[2025, 2024], [2000, 2001], [2025, 2026], [0, 2025], [2001, PHP_INT_MAX]] as [$desde, $hasta]) {
            $this->rechaza(fn () => $this->consultar($id, $desde, $hasta), 'FILTROS_INVALIDOS');
        }
        $r['consulta']['desde'] = 2024;
        $r['total_periodo_ha'] = 0.5;
        array_shift($c['features']);
        $r['conteos_pixeles']['perdida_periodo'] = 2;
        $id = $this->paquete($r, $c);
        $this->assertCount(2, $this->consultar($id)['capa']['features']);
        $this->rechaza(fn () => $this->consultar($id, 2001, 2001), 'FILTROS_INVALIDOS');
    }

    public function test_verifica_los_tres_hashes_aun_en_consulta_sin_perdida(): void
    {
        foreach (['manifiesto.json', 'resumen.json', 'perdida.geojson'] as $nombre) {
            [$r, $c] = $this->sintetico();
            $id = $this->paquete($r, $c);
            file_put_contents($this->directorio.'/'.$id.'/'.$nombre, ' ', FILE_APPEND);
            $this->rechaza(fn () => $this->consultar($id, 2023, 2023), caso: $nombre);
        }
    }

    public function test_identidad_debe_coincidir_con_todos_los_campos_reales_con_tipos_estrictos(): void
    {
        $cambios = [
            'fuente_version' => fn (&$i) => $i['fuente_version'] = 'otra',
            'tesela' => fn (&$i) => $i['tesela'] = 'otra',
            'captura' => fn (&$i) => $i['captura_sha256'] = str_repeat('b', 64),
            'criterio' => fn (&$i) => $i['criterio']['porcentaje'] = 31,
            'tipo criterio' => fn (&$i) => $i['criterio']['porcentaje'] = 30.0,
            'ventana' => fn (&$i) => $i['ventana_pixeles']['alto'] = 3,
            'consulta' => fn (&$i) => $i['consulta']['desde'] = 2002,
            'objeto por lista' => fn (&$i) => $i['consulta'] = [2001, 2025, true],
        ];
        foreach ($cambios as $nombre => $cambio) {
            [$r, $c] = $this->sintetico();
            $id = $this->paquete($r, $c, function (&$m) use ($cambio) {
                $cambio($m['identidad']);
            });
            $this->rechaza(fn () => $this->consultar($id), caso: $nombre);
        }
    }

    public function test_rechaza_2026_y_otras_fuentes_incluso_con_identidad_y_hashes_recalculados(): void
    {
        $cambios = [
            'version' => fn (&$r) => $r['fuente']['version'] = 'GFC-2026-v1.14',
            'tesela' => fn (&$r) => $r['fuente']['tesela'] = '20S_070W',
            'consulta 2026' => fn (&$r) => $r['consulta']['hasta'] = 2026,
            'serie 2026' => fn (&$r) => $r['serie'][0]['anio'] = 2026,
            'producto 2026' => function (&$r) {
                $r['consulta']['hasta'] = 2026;
                $r['serie'][] = ['anio' => 2026, 'perdida_ha' => 0.0];
            },
        ];
        foreach (['ancho', 'alto', 'paso_grados', 'oeste', 'norte', 'epsg', 'pixel'] as $campo) {
            $cambios['cuadricula '.$campo] = function (&$r) use ($campo) {
                $r['fuente']['cuadricula'][$campo] = match ($campo) {
                    'paso_grados' => 0.0005, 'oeste' => -69, 'norte' => -9, 'epsg' => 3857,
                    'pixel' => 'point', default => 40001,
                };
            };
        }
        foreach ($cambios as $nombre => $cambio) {
            [$r, $c] = $this->sintetico();
            $cambio($r);
            $this->rechaza(fn () => $this->consultar($this->paquete($r, $c)), caso: $nombre);
        }
    }

    public function test_rechaza_resumen_corrupto_incluso_fuera_del_intervalo_pedido(): void
    {
        $cambios = [
            'estado aprobado' => fn (&$r) => $r['estado'] = 'validado',
            'api' => fn (&$r) => $r['integrado_en_api'] = true,
            'adoptado' => fn (&$r) => $r['criterio']['adoptado'] = true,
            'booleano' => fn (&$r) => $r['criterio']['adoptado'] = 0,
            'porcentaje texto' => fn (&$r) => $r['criterio']['porcentaje'] = '30',
            'consulta texto' => fn (&$r) => $r['consulta']['desde'] = '2001',
            'consulta no inclusiva' => fn (&$r) => $r['consulta']['extremos_incluidos'] = false,
            'consulta invertida' => fn (&$r) => $r['consulta']['desde'] = 2026,
            'criterio array' => fn (&$r) => $r['criterio'] = [],
            'serie objeto' => fn (&$r) => $r['serie'] = (object) $r['serie'],
            'serie faltante' => fn (&$r) => array_shift($r['serie']),
            'serie duplicada' => fn (&$r) => $r['serie'][0] = $r['serie'][1],
            'anio float' => fn (&$r) => $r['serie'][0]['anio'] = 2001.0,
            'area texto' => fn (&$r) => $r['serie'][0]['perdida_ha'] = '0.1',
            'area negativa' => fn (&$r) => $r['serie'][0]['perdida_ha'] = -0.1,
            'area desconocida' => fn (&$r) => $r['serie'][0]['perdida_ha'] = null,
            'area booleano' => fn (&$r) => $r['serie'][0]['perdida_ha'] = false,
            'area precision ajena' => fn (&$r) => $r['serie'][0]['perdida_ha'] = 0.100000001,
            'area precision menor a tolerancia' => fn (&$r) => $r['serie'][0]['perdida_ha'] = 0.10000000001,
            'area desmedida' => fn (&$r) => $r['serie'][0]['perdida_ha'] = 1e20,
            'total desfasado' => fn (&$r) => $r['total_periodo_ha'] = 100,
            'total texto' => fn (&$r) => $r['total_periodo_ha'] = '0.6',
            'bbox incorrecto' => fn (&$r) => $r['bbox'][0] = -60,
            'bbox objeto' => fn (&$r) => $r['bbox'] = (object) $r['bbox'],
            'ventana enorme' => fn (&$r) => $r['ventana_pixeles']['ancho'] = 100001,
            'ventana fuera fuente' => fn (&$r) => $r['ventana_pixeles']['columna'] = 40000,
            'ventana float' => fn (&$r) => $r['ventana_pixeles']['alto'] = 2.0,
            'conteos texto' => fn (&$r) => $r['conteos_pixeles']['tierra_base'] = '4',
            'conteo imposible' => fn (&$r) => $r['conteos_pixeles']['arbolado_base'] = 5,
            'suma base imposible' => fn (&$r) => $r['conteos_pixeles']['agua_base'] = 1,
            'cobertura inventada' => fn (&$r) => $r['cobertura_observaciones_fraccion'] = true,
            'limites omitidos' => fn (&$r) => $r['limites'] = [],
        ];
        foreach ($cambios as $nombre => $cambio) {
            [$r, $c] = $this->sintetico();
            $cambio($r);
            $this->rechaza(fn () => $this->consultar($this->paquete($r, $c)), caso: $nombre);
        }
    }

    public function test_rechaza_celdas_duplicadas_ajenas_y_geometrias_o_areas_invalidas(): void
    {
        $cambios = [
            'duplicado' => fn (&$c) => $c['features'][0] = $c['features'][1],
            'misma celda otro anio' => function (&$c) {
                $c['features'][0]['id'] = $c['features'][1]['id'];
            },
            'id ajeno' => fn (&$c) => $c['features'][0]['id'] = '28000-25202',
            'id ambiguo' => fn (&$c) => $c['features'][0]['id'] = '028000-25200',
            'id numero' => fn (&$c) => $c['features'][0]['id'] = 1,
            'anio ajeno' => fn (&$c) => $c['features'][0]['properties']['anio'] = 2026,
            'anio texto' => fn (&$c) => $c['features'][0]['properties']['anio'] = '2001',
            'area cero' => fn (&$c) => $c['features'][0]['properties']['area_ha'] = 0,
            'area negativa' => fn (&$c) => $c['features'][0]['properties']['area_ha'] = -1,
            'area texto' => fn (&$c) => $c['features'][0]['properties']['area_ha'] = '0.1',
            'area desfasada' => fn (&$c) => $c['features'][0]['properties']['area_ha'] = 0.9,
            'punto fuera bbox' => fn (&$c) => $c['features'][0]['geometry']['coordinates'][0][0][0] = -60,
            'punto de otra celda' => fn (&$c) => $c['features'][0]['geometry']['coordinates'][0][0][0] += 0.00025,
            'anillo abierto' => fn (&$c) => $c['features'][0]['geometry']['coordinates'][0][4][1] -= 0.00025,
            'coordenada texto' => fn (&$c) => $c['features'][0]['geometry']['coordinates'][0][0][0] = '-63.7',
            'coordenada 3d' => fn (&$c) => $c['features'][0]['geometry']['coordinates'][0][0][] = 0,
            'agujero' => fn (&$c) => $c['features'][0]['geometry']['coordinates'][] = [],
            'tipo geometria' => fn (&$c) => $c['features'][0]['geometry']['type'] = 'MultiPolygon',
            'propiedades array' => fn (&$c) => $c['features'][0]['properties'] = [],
            'capacidad municipal ajena' => fn (&$c) => $c['features'][0]['properties']['municipio'] = 'inventado',
            'features objeto' => fn (&$c) => $c['features'] = (object) $c['features'],
            'capa incompleta' => fn (&$c) => array_pop($c['features']),
        ];
        foreach ($cambios as $nombre => $cambio) {
            [$r, $c] = $this->sintetico();
            $cambio($c);
            $this->rechaza(fn () => $this->consultar($this->paquete($r, $c)), caso: $nombre);
        }
    }

    public function test_rechaza_json_malformado_raices_no_objeto_y_manifiestos_invalidos(): void
    {
        foreach (['[]', '{}', 'null', 'true', '{', '{"n":1e999}', "\xFF", str_repeat('[', 65).'0'.str_repeat(']', 65)] as $bytes) {
            [$r, $c] = $this->sintetico();
            $this->rechaza(fn () => $this->consultar($this->paquete($r, $c, resumenBytes: $bytes)));
            $this->rechaza(fn () => $this->consultar($this->paquete($r, $c, capaBytes: $bytes)));
        }
        $cambios = [
            fn (&$m) => $m['formato'] = 'otro', fn (&$m) => $m['estado'] = 'publicado',
            fn (&$m) => $m['identidad'] = [], fn (&$m) => $m['archivos'] = [],
            fn (&$m) => $m['archivos']['resumen.json'] = true,
            fn (&$m) => $m['archivos']['perdida.geojson'] = str_repeat('g', 64),
            fn (&$m) => $m['archivos']['../secreto'] = str_repeat('a', 64),
            fn (&$m) => $m['identidad']['territorio'] = 'municipio-inventado',
        ];
        foreach ($cambios as $cambio) {
            [$r, $c] = $this->sintetico();
            $this->rechaza(fn () => $this->consultar($this->paquete($r, $c, $cambio)));
        }
    }

    public function test_rechaza_archivos_ausentes_directorios_y_tamanios_excesivos(): void
    {
        foreach (['manifiesto.json' => 65536, 'resumen.json' => 2097152, 'perdida.geojson' => 33554432] as $nombre => $maximo) {
            [$r, $c] = $this->sintetico();
            $id = $this->paquete($r, $c);
            $ruta = $this->directorio.'/'.$id.'/'.$nombre;
            unlink($ruta);
            $this->rechaza(fn () => $this->consultar($id), caso: 'ausente '.$nombre);
            mkdir($ruta);
            $this->rechaza(fn () => $this->consultar($id), caso: 'directorio '.$nombre);
            rmdir($ruta);
            $archivo = fopen($ruta, 'wb');
            ftruncate($archivo, $maximo + 1);
            fclose($archivo);
            $this->rechaza(fn () => $this->consultar($id), caso: 'excesivo '.$nombre);
            file_put_contents($ruta, '');
            $this->rechaza(fn () => $this->consultar($id), caso: 'vacio '.$nombre);
        }
        $this->rechaza(fn () => $this->consultar(str_repeat('b', 64)));
    }

    public function test_rechaza_enlaces_de_archivos_paquete_y_ancestro(): void
    {
        [$r, $c] = $this->sintetico();
        $id = $this->paquete($r, $c);
        $prueba = $this->directorio.'/prueba-enlace';
        if (! @symlink($this->directorio.'/'.$id.'/resumen.json', $prueba)) {
            $this->markTestSkipped('El sistema no permite crear enlaces simbólicos para esta prueba.');
        }
        unlink($prueba);
        foreach (['manifiesto.json', 'resumen.json', 'perdida.geojson'] as $nombre) {
            $ruta = $this->directorio.'/'.$id.'/'.$nombre;
            rename($ruta, $ruta.'.original');
            $this->assertTrue(symlink($ruta.'.original', $ruta));
            $this->rechaza(fn () => $this->consultar($id), caso: $nombre);
            unlink($ruta);
            rename($ruta.'.original', $ruta);
        }
        $original = $this->directorio.'/'.$id;
        rename($original, $original.'-original');
        $this->assertTrue(symlink($original.'-original', $original));
        $this->rechaza(fn () => $this->consultar($id), caso: 'directorio paquete');
        unlink($original);
        rename($original.'-original', $original);
        mkdir($this->directorio.'/real');
        $this->assertTrue(symlink($this->directorio.'/real', $this->directorio.'/alias'));
        $repo = new RepositorioRevisionForestal($this->directorio.'/alias/subdirectorio');
        mkdir($this->directorio.'/real/subdirectorio');
        $this->rechaza(fn () => $repo->consultar($id, 2024, 2025), caso: 'ancestro simbolico');
    }

    public function test_rechaza_claves_json_repetidas_incluso_escapadas_con_hashes_validos(): void
    {
        [$r, $c] = $this->sintetico();
        foreach (['estado', '\u0065stado'] as $clave) {
            $bytes = '{"'.$clave.'":"recorte_tecnico_no_validado",'.substr($this->json($r), 1);
            $this->rechaza(fn () => $this->consultar($this->paquete($r, $c, resumenBytes: $bytes)));
        }
        $bytes = '{"type":"FeatureCollection",'.substr($this->json($c), 1);
        $this->rechaza(fn () => $this->consultar($this->paquete($r, $c, capaBytes: $bytes)));
        foreach (['[]', 'null', '{', '{"formato":"revision-forestal-1",', '{"\u0066ormato":"revision-forestal-1",'] as $inicio) {
            $id = $this->paquete($r, $c);
            $bytes = str_ends_with($inicio, ',')
                ? $inicio.substr(file_get_contents($this->directorio.'/'.$id.'/manifiesto.json'), 1) : $inicio;
            $nuevo = hash('sha256', $bytes);
            rename($this->directorio.'/'.$id, $this->directorio.'/'.$nuevo);
            file_put_contents($this->directorio.'/'.$nuevo.'/manifiesto.json', $bytes);
            $this->rechaza(fn () => $this->consultar($nuevo));
        }
    }

    public function test_conserva_etags_variables_y_objetos_vacios_sin_acreditar_autenticidad(): void
    {
        [$r, $c] = $this->sintetico();
        $r['fuente']['etag'] = ['lossyear' => '"otra-etiqueta\\con\\barras"'];
        $r['fuente']['metadata_vacia'] = (object) [];
        $id = $this->paquete($r, $c);
        $a = $this->consultar($id);
        $this->assertSame($r['fuente']['etag'], $a['resumen']['fuente']['etag']);
        $this->assertInstanceOf(\stdClass::class, $a['resumen']['fuente']['metadata_vacia']);
        $this->assertSame('recorte_tecnico_no_validado', $a['resumen']['estado']);
        $this->assertTrue($a['revision']);
        $bytes = str_replace('"metadata_vacia":{}', '"metadata_vacia":{"infinito":1e999}', $this->json($r));
        $this->rechaza(fn () => $this->consultar($this->paquete($r, $c, resumenBytes: $bytes)));
    }
}
