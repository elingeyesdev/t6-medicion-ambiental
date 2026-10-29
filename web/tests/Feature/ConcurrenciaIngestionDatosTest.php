<?php

namespace Tests\Feature;

use App\Enums\RolCuenta;
use App\Models\LecturaAmbiental;
use App\Models\PuntoAmbiental;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ConcurrenciaIngestionDatosTest extends TestCase
{
    private string $carpeta;

    private ?string $schemaPostgres = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->carpeta = base_path('../tmp/concurrencia-datos/'.Str::uuid());
        mkdir($this->carpeta, 0777, true);
        if (getenv('GEOSUR_PRUEBA_POSTGRES') === '1' && config('database.default') === 'pgsql') {
            $this->assertSame('geosur_m3_tests', DB::connection()->getDatabaseName());
            $this->assertSame('127.0.0.1', config('database.connections.pgsql.host'));
            $this->schemaPostgres = 'concurrencia_'.bin2hex(random_bytes(8));
            DB::statement('CREATE SCHEMA "'.$this->schemaPostgres.'"');
            config(['database.connections.pgsql.search_path' => $this->schemaPostgres]);
            $conexion = array_intersect_key(config('database.connections.pgsql'), array_flip(['host', 'port', 'database', 'username', 'search_path']));
            file_put_contents($this->carpeta.'/postgres.json', json_encode($conexion, JSON_THROW_ON_ERROR));
        } else {
            touch($this->carpeta.'/prueba.sqlite');
            config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $this->carpeta.'/prueba.sqlite', 'database.connections.sqlite.url' => null]);
        }
        DB::purge();
        Artisan::call('migrate', ['--force' => true]);
        User::factory()->create(['id' => 1, 'rol' => RolCuenta::Analista]);
        PuntoAmbiental::create(['codigo' => 'campus', 'nombre' => 'Concurrencia local', 'pais' => 'BO', 'latitud' => -17, 'longitud' => -63, 'tipo' => 'manual']);
    }

    protected function tearDown(): void
    {
        DB::disconnect();
        if ($this->schemaPostgres !== null && preg_match('/^concurrencia_[0-9a-f]{16}$/D', $this->schemaPostgres)) {
            config(['database.connections.pgsql.search_path' => 'public']);
            DB::purge();
            DB::statement('DROP SCHEMA "'.$this->schemaPostgres.'" CASCADE');
            DB::disconnect();
        }
        foreach (glob($this->carpeta.'/*') as $archivo) {
            unlink($archivo);
        }
        rmdir($this->carpeta);
        parent::tearDown();
    }

    private function fila(int $minuto = 0, array $cambios = []): array
    {
        return $cambios + ['tiempo' => sprintf('2026-10-27T12:%02d:00Z', $minuto), 'localidad' => 'campus', 'metrica' => 'temperatura', 'valor' => 20, 'unidad' => '°C', 'origen' => 'simulado', 'fuente' => 'concurrencia-local'];
    }

    private function simultaneos(array $lotes): array
    {
        $procesos = [];
        try {
            foreach ($lotes as $i => $filas) {
                file_put_contents($this->carpeta.'/lote-'.$i.'.json', json_encode(['formato' => 'geosur.historial.v1', 'lecturas' => $filas], JSON_THROW_ON_ERROR));
                $proceso = proc_open([PHP_BINARY, '-c', php_ini_loaded_file(), base_path('tests/Fixtures/Datos/registrar-proceso.php'), $this->carpeta, (string) $i], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                $this->assertIsResource($proceso);
                $procesos[] = [$proceso, $pipes];
            }
            $limite = microtime(true) + 10;
            while (count(glob($this->carpeta.'/listo-*')) !== count($lotes) && microtime(true) < $limite) {
                usleep(10000);
            }
            $this->assertCount(count($lotes), glob($this->carpeta.'/listo-*'), 'Todos los procesos deben estar listos antes de abrir la barrera.');
            file_put_contents($this->carpeta.'/iniciar', 'iniciar');
            $resultados = [];
            foreach ($procesos as [$proceso, $pipes]) {
                stream_set_timeout($pipes[1], 15);
                $salida = stream_get_contents($pipes[1]);
                $error = stream_get_contents($pipes[2]);
                $this->assertSame('', $error);
                $this->assertJson($salida, $salida);
                $resultados[] = json_decode($salida, true, 16, JSON_THROW_ON_ERROR);
            }

            return $resultados;
        } finally {
            foreach ($procesos as [$proceso, $pipes]) {
                foreach ($pipes as $pipe) {
                    fclose($pipe);
                }
                proc_close($proceso);
            }
        }
    }

    public static function modos(): array
    {
        return getenv('GEOSUR_PRUEBA_POSTGRES') === '1' ? [['POSTGRES']] : [['DELETE'], ['WAL']];
    }

    #[DataProvider('modos')]
    public function test_cuatro_procesos_con_lote_repetido_no_pierden_datos_ni_duplican(string $modo): void
    {
        // La conexión usa el PRAGMA propio del conector, exclusivamente en esta base efímera.
        if ($modo !== 'POSTGRES') {
            DB::disconnect();
            config(['database.connections.sqlite.journal_mode' => $modo]);
            DB::purge();
            DB::connection()->getPdo();
        }
        $filas = array_map(fn ($i) => $this->fila($i), range(0, 59));
        $resultados = $this->simultaneos(array_fill(0, 4, $filas));
        $this->assertSame([0, 0, 0, 0], array_column($resultados, 'codigo'));
        $this->assertDatabaseCount('lecturas_ambientales', 60);
        $this->assertSame(1, count(array_filter($resultados, fn ($r) => str_contains($r['salida'], 'Nuevas: 60.'))));
    }

    public function test_lotes_concurrentes_en_conflicto_conservan_un_ganador_y_revierten_el_otro_completo(): void
    {
        $r = $this->simultaneos([
            [$this->fila(0, ['metrica' => 'humedad', 'unidad' => '%', 'valor' => 50]), $this->fila()],
            [$this->fila(0, ['metrica' => 'ruido', 'unidad' => 'dB', 'valor' => 10]), $this->fila(0, ['valor' => 21])],
        ]);
        $codigos = array_column($r, 'codigo');
        sort($codigos);
        $this->assertSame([0, 2], $codigos);
        $this->assertDatabaseCount('lecturas_ambientales', 2);
        $temperatura = LecturaAmbiental::where('metrica', 'temperatura')->sole()->valor;
        $this->assertSame($temperatura === 20.0 ? ['humedad', 'temperatura'] : ['ruido', 'temperatura'], LecturaAmbiental::orderBy('metrica')->pluck('metrica')->all());
    }
}
