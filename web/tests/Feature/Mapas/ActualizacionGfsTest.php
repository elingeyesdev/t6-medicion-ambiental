<?php

namespace Tests\Feature\Mapas;

use App\Services\Api\ErrorConsulta;
use App\Services\Mapas\Radar\CatalogoRaster;
use App\Services\Mapas\Radar\IncorporarGfs;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use UnexpectedValueException;

class ActualizacionGfsTest extends TestCase
{
    private string $raiz;

    protected function setUp(): void
    {
        parent::setUp();
        DB::shouldReceive('connection')->never();
        $this->raiz = storage_path('framework/views-pruebas/actualizar-'.bin2hex(random_bytes(8)));
        mkdir($this->raiz.'/publicado', 0700, true);
        config(['capas.directorio' => $this->raiz.'/publicado', 'capas.actualizacion.fechas_por_capa' => 2]);
    }

    protected function tearDown(): void
    {
        $base = realpath($this->raiz);
        $quitar = function ($ruta) use (&$quitar, $base): void {
            if (! str_starts_with(str_replace('\\', '/', realpath($ruta)).'/', str_replace('\\', '/', $base).'/')) {
                throw new \RuntimeException('Limpieza fuera del directorio de prueba.');
            }
            foreach (glob($ruta.'/*') as $archivo) {
                if (is_dir($archivo) && ! is_link($archivo)) {
                    $quitar($archivo);
                } else {
                    unlink($archivo);
                }
            }
            rmdir($ruta);
        };
        $quitar($this->raiz);
        parent::tearDown();
    }

    private function lote(string $ciclo, string $sufijo = '', bool $energia = false): string
    {
        $ruta = $this->raiz.'/lote-'.$ciclo.$sufijo;
        mkdir($ruta);
        $chunk = fn ($t, $s) => pack('N', strlen($s)).$t.$s.hash('crc32b', $t.$s, true);
        $png = "\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', 2, 2, 8, 6, 0, 0, 0)).
            $chunk('IDAT', gzcompress("\0\0\0\0\xff\xff\0\0\xff\0\x80\0\0\xff\0\0\0\0")).$chunk('IEND', '');
        $inicio = \DateTimeImmutable::createFromFormat('!YmdH', $ciclo);
        $cuadros = [];
        foreach ($energia ? IncorporarGfs::ENERGIA : IncorporarGfs::CAPAS as $capa) {
            $codificacion = ['temperatura' => 'temp-era5', 'humedad' => 'humedad-etl', 'presion' => 'presion-etl',
                'visibilidad' => 'vis-etl', 'nieve' => 'nieve-etl', 'lluvia_intensidad' => 'lluvia-escalones', 'lluvia' => 'lluvia-escalones', 'viento' => 'viento-uv127',
                'radiacion_solar' => 'solar-wm2', 'evaporacion_potencial' => 'evap-pot-wm2'][$capa];
            foreach ([3, 6] as $plazo) {
                $id = 'prueba-'.$ciclo.'-'.$capa.'-'.$plazo.$sufijo;
                file_put_contents($ruta.'/'.$id.'.png', $png);
                $instante = $inicio->modify('+'.$plazo.' hours')->format('Y-m-d\TH:i:s\Z');
                $desde = in_array($capa, ['lluvia', 'radiacion_solar'], true) ? 0 : $plazo;
                $cuadros[] = ['id' => $id, 'capa' => $capa, 'archivo' => $id.'.png', 'instante' => $instante, 'codificacion' => $codificacion,
                    'fuente' => 'Fixture técnico controlado', 'producto' => 'No son datos ambientales reales',
                    'unidad' => $energia ? 'W/m²' : 'fixture',
                    'intervalo' => ['ciclo' => $inicio->format('Y-m-d\TH:i:s\Z'), 'instante' => $instante, 'inicio_horas' => $desde, 'fin_horas' => $plazo,
                        'desde' => $inicio->modify('+'.$desde.' hours')->format('Y-m-d\TH:i:s\Z'), 'hasta' => $instante,
                        'tipo_paso' => $capa === 'radiacion_solar' ? 'avg' : 'instant'],
                    'rejilla' => ['proyeccion' => 'EPSG:4326', 'registro' => 'nodos', 'filas' => 'norte-sur', 'oeste' => -64, 'este' => -63, 'sur' => -18, 'norte' => -17]];
            }
        }
        file_put_contents($ruta.'/receta.json', json_encode(['version' => 1, 'cuadros' => $cuadros]));
        // Simula el manifiesto del orquestador; solo se usa en este directorio aleatorio.
        file_put_contents($ruta.'/actualizacion.json', json_encode(['version' => 1, 'ciclo' => $ciclo, 'perfil' => $energia ? 'energia' : 'ambiental', 'datos_controlados' => false]));

        return $ruta;
    }

    public function test_lote_completo_repetible_y_archivo_conserva_las_comparaciones_anteriores(): void
    {
        $motor = new IncorporarGfs;
        $primero = $this->lote('2026102900');
        $this->assertSame(16, $motor->ejecutar($primero)['nuevos']);
        $previo = (new CatalogoRaster)->cargar()['cuadros'][0];
        $bytes = (new CatalogoRaster)->imagen($previo['id']);
        $this->assertSame(0, $motor->ejecutar($primero)['nuevos']);
        $resultado = $motor->ejecutar($this->lote('2026102906'));
        $this->assertSame(16, $resultado['activos']);
        $this->assertSame(16, $resultado['archivados']);
        $this->assertSame($bytes, (new CatalogoRaster)->imagen($previo['id']));
        $this->assertFileExists(config('capas.directorio').'/'.$previo['archivo']);
    }

    public function test_lote_incompleto_o_conflictivo_no_cambia_la_publicacion(): void
    {
        $motor = new IncorporarGfs;
        $motor->ejecutar($this->lote('2026102900'));
        $original = file_get_contents(config('capas.directorio').'/catalogo.json');
        $incompleto = $this->lote('2026102906');
        $receta = json_decode(file_get_contents($incompleto.'/receta.json'), true);
        array_pop($receta['cuadros']);
        file_put_contents($incompleto.'/receta.json', json_encode($receta));
        $intervaloIncorrecto = $this->lote('2026102912');
        $receta = json_decode(file_get_contents($intervaloIncorrecto.'/receta.json'), true);
        $receta['cuadros'][0]['intervalo']['fin_horas'] = 6;
        file_put_contents($intervaloIncorrecto.'/receta.json', json_encode($receta));
        foreach ([$incompleto, $intervaloIncorrecto, $this->lote('2026102900', '-distinto')] as $lote) {
            try {
                $motor->ejecutar($lote);
                $this->fail('Se aceptó un lote incompleto o conflictivo.');
            } catch (UnexpectedValueException) {
                $this->assertSame($original, file_get_contents(config('capas.directorio').'/catalogo.json'));
            }
        }
    }

    public function test_un_archivo_danado_y_otro_publicador_no_sustituyen_el_catalogo(): void
    {
        $motor = new IncorporarGfs;
        $motor->ejecutar($this->lote('2026102900'));
        $original = file_get_contents(config('capas.directorio').'/catalogo.json');
        $lote = $this->lote('2026102906');
        file_put_contents(glob($lote.'/*.png')[0], 'roto');
        try {
            $motor->ejecutar($lote);
            $this->fail('Se aceptó un PNG dañado.');
        } catch (UnexpectedValueException) {
            $this->assertSame($original, file_get_contents(config('capas.directorio').'/catalogo.json'));
        }
        $bloqueo = fopen(config('capas.directorio').'/publicacion.lock', 'c+b');
        flock($bloqueo, LOCK_EX | LOCK_NB);
        try {
            $this->expectException(UnexpectedValueException::class);
            $motor->ejecutar($lote);
        } finally {
            flock($bloqueo, LOCK_UN);
            fclose($bloqueo);
        }
    }

    public function test_la_referencia_archivada_no_puede_apuntar_fuera_del_directorio(): void
    {
        $motor = new IncorporarGfs;
        $motor->ejecutar($this->lote('2026102900'));
        $previo = (new CatalogoRaster)->cargar()['cuadros'][0];
        $motor->ejecutar($this->lote('2026102906'));
        $previo['archivo'] = '../fuera.png';
        file_put_contents(config('capas.directorio').'/archivo/cuadros/'.$previo['id'].'.json', json_encode($previo));
        $this->expectException(ErrorConsulta::class);
        (new CatalogoRaster)->imagen($previo['id']);
    }

    public function test_no_se_recalcula_la_huella_para_ocultar_un_archivo_anterior_alterado(): void
    {
        $motor = new IncorporarGfs;
        $motor->ejecutar($this->lote('2026102900'));
        $original = file_get_contents(config('capas.directorio').'/catalogo.json');
        $cuadros = (new CatalogoRaster)->cargar()['cuadros'];
        // Otro PNG válido con valores de prueba distintos, no un fallo de formato.
        $ruta = config('capas.directorio').'/'.$cuadros[0]['archivo'];
        $bytes = file_get_contents($ruta);
        $posicion = strpos($bytes, 'IDAT');
        $largo = unpack('N', substr($bytes, $posicion - 4, 4))[1];
        $datos = gzuncompress(substr($bytes, $posicion + 4, $largo));
        $datos[1] = chr(100);
        $comprimido = gzcompress($datos);
        $nuevo = pack('N', strlen($comprimido)).'IDAT'.$comprimido.hash('crc32b', 'IDAT'.$comprimido, true);
        file_put_contents($ruta, substr($bytes, 0, $posicion - 4).$nuevo.substr($bytes, $posicion + $largo + 8));
        try {
            $motor->ejecutar($this->lote('2026102906'));
            $this->fail('Se aceptó la modificación de una imagen anterior.');
        } catch (UnexpectedValueException) {
            $this->assertSame($original, file_get_contents(config('capas.directorio').'/catalogo.json'));
        }
    }

    public function test_una_instalacion_nueva_no_descarga_al_ejecutar_la_orden_programada_apagada(): void
    {
        config(['capas.actualizacion.habilitada' => false]);
        $this->artisan('capas:actualizar --programada')->assertSuccessful();
        $this->artisan('capas:actualizar --programada --energia-superficie')->assertSuccessful();
        $this->artisan('capas:actualizar')->assertExitCode(2);
    }

    public function test_energia_y_ambiente_conviven_sin_sustituir_el_catalogo(): void
    {
        $motor = new IncorporarGfs;
        $motor->ejecutar($this->lote('2026102900'));
        $primero = (new CatalogoRaster)->cargar()['cuadros'][0];
        $bytes = (new CatalogoRaster)->imagen($primero['id']);
        $energia = $this->lote('2026102900', '-energia', true);
        $this->assertSame(4, $motor->ejecutar($energia)['nuevos']);
        $this->assertCount(20, (new CatalogoRaster)->cargar()['cuadros']);
        $this->assertSame($bytes, (new CatalogoRaster)->imagen($primero['id']));
        $this->assertSame(0, $motor->ejecutar($energia)['nuevos']);
    }

    public function test_energia_rechaza_unidades_periodos_o_perfiles_incorrectos_sin_cambios_parciales(): void
    {
        $motor = new IncorporarGfs;
        $motor->ejecutar($this->lote('2026102900'));
        $antes = file_get_contents(config('capas.directorio').'/catalogo.json');
        foreach (['unidad', 'instante', 'evaporacion', 'incompleto', 'perfil'] as $caso) {
            $lote = $this->lote('2026102906', '-'.$caso, true);
            $r = json_decode(file_get_contents($lote.'/receta.json'), true);
            match ($caso) {
                'unidad' => $r['cuadros'][0]['unidad'] = 'mm',
                'instante' => $r['cuadros'][0]['intervalo']['tipo_paso'] = 'instant',
                'evaporacion' => $r['cuadros'][2]['intervalo']['inicio_horas'] = 0,
                'incompleto' => array_pop($r['cuadros']),
                default => null,
            };
            file_put_contents($lote.'/receta.json', json_encode($r));
            if ($caso === 'perfil') {
                $m = json_decode(file_get_contents($lote.'/actualizacion.json'), true);
                $m['perfil'] = 'uv';
                file_put_contents($lote.'/actualizacion.json', json_encode($m));
            }
            try {
                $motor->ejecutar($lote);
                $this->fail('Se aceptó energía sin su contrato.');
            } catch (UnexpectedValueException) {
                $this->assertSame($antes, file_get_contents(config('capas.directorio').'/catalogo.json'));
            }
        }
    }
}
