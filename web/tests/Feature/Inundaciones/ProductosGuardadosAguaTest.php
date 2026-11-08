<?php

namespace Tests\Feature\Inundaciones;

use App\Models\User;
use App\Services\Inundaciones\CatalogoProductosAgua;
use App\Services\Inundaciones\ValidadorProductoAgua;
use App\Services\Territorios\CatalogoTerritorial;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductosGuardadosAguaTest extends TestCase
{
    private string $directorio;

    private const URL = '/api/v1/inundaciones/consulta?territorio=BO070101&referencia=2024-09-01&comparacion=2025-03-07';

    protected function setUp(): void
    {
        parent::setUp();
        $this->directorio = sys_get_temp_dir().'/geosur-agua112-'.bin2hex(random_bytes(12));
        mkdir($this->directorio, 0700);
        $this->app->instance(CatalogoProductosAgua::class, new CatalogoProductosAgua(
            $this->directorio, new ValidadorProductoAgua($this->app->make(CatalogoTerritorial::class)),
        ));
        Sanctum::actingAs(User::factory()->make(['id' => 123]), ['cuenta']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directorio);
        parent::tearDown();
    }

    /** Respuesta sintética del contrato: nunca se instala en el almacén operativo. */
    private function dato(string $caso = 'cambio'): array
    {
        $dato = json_decode(file_get_contents(base_path('../compartido/api/inundaciones/ejemplos/'.$caso.'.json')), true, 64, JSON_THROW_ON_ERROR);
        $dato['tipoEvidencia'] = 'resultado_validado';

        return $dato;
    }

    private function archivo(array $dato): string
    {
        $raw = json_encode($dato, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        $sha = hash('sha256', $raw);
        file_put_contents($this->directorio.'/'.$sha.'.json', $raw);

        return $sha;
    }

    private function indice(array $productos): void
    {
        file_put_contents($this->directorio.'/indice.json', json_encode([
            'version' => 'agua-publicaciones-1', 'productos' => $productos,
        ], JSON_THROW_ON_ERROR));
    }

    public function test_sirve_el_producto_exactamente_y_declara_su_huella_sin_exponer_rutas(): void
    {
        $dato = $this->dato();
        $sha = $this->archivo($dato);
        $this->indice([$sha]);
        $this->getJson(self::URL)->assertOk()->assertExactJson($dato)
            ->assertHeader('X-Producto-SHA256', $sha)->assertHeader('Cache-Control', 'no-store, private');
        $this->getJson('/api/v1/inundaciones/catalogo')->assertOk()
            ->assertJsonPath('estado', 'disponible')->assertJsonPath('productos.0.id', $sha)
            ->assertJsonMissingPath('productos.0.capa')->assertJsonMissingPath('productos.0.archivo');
        Http::assertNothingSent();
    }

    public function test_conserva_cero_y_faltantes_como_estados_diferentes(): void
    {
        foreach (['sin-cambios', 'sin-datos'] as $caso) {
            $dato = $this->dato($caso);
            $this->indice([$this->archivo($dato)]);
            $this->getJson(self::URL)->assertOk()->assertExactJson($dato)
                ->assertJsonPath('resumen.aguaNuevaKm2', $caso === 'sin-cambios' ? 0 : null);
        }
        Http::assertNothingSent();
    }

    public function test_el_orden_de_claves_json_no_cambia_la_identidad(): void
    {
        $dato = $this->dato();
        $dato['comparacion'] = ['comparacion' => '2025-03-07', 'referencia' => '2024-09-01'];
        $this->indice([$this->archivo($dato)]);
        $this->getJson(self::URL)->assertOk()->assertExactJson($dato);
    }

    public function test_no_extiende_un_recorte_municipal_al_departamento_ni_a_otras_fechas(): void
    {
        $this->indice([$this->archivo($this->dato())]);
        $this->getJson(str_replace('territorio=BO070101', 'territorio=BO07', self::URL))
            ->assertNotFound()->assertJsonPath('error.codigo', 'TERRITORIO_SIN_COBERTURA');
        $this->getJson(str_replace('2025-03-07', '2025-03-08', self::URL))
            ->assertStatus(503)->assertJsonPath('error.codigo', 'PRODUCTO_NO_DISPONIBLE');
    }

    public function test_sin_indice_no_publica_los_ejemplos_ni_hace_descargas(): void
    {
        $this->getJson('/api/v1/inundaciones/catalogo')->assertOk()->assertJsonPath('productos', []);
        $this->getJson(self::URL)->assertStatus(503)->assertJsonPath('error.codigo', 'PRODUCTO_NO_DISPONIBLE');
        Http::assertNothingSent();
    }

    public function test_no_oculta_un_catalogo_ambiguo_tras_la_primera_coincidencia(): void
    {
        $a = $this->dato();
        $b = $a;
        $b['fuente']['metodo'] = 'Otra versión sintética';
        $this->indice([$this->archivo($a), $this->archivo($b)]);
        $this->getJson(self::URL)->assertStatus(503)->assertJsonPath('error.codigo', 'PRODUCTO_INVALIDO');
        $this->getJson('/api/v1/inundaciones/catalogo')->assertStatus(503)->assertJsonPath('error.codigo', 'PRODUCTO_INVALIDO');
    }

    #[DataProvider('catalogosInvalidos')]
    public function test_rechaza_indices_invalidos_sin_convertirlos_en_catalogos_vacios(string $caso): void
    {
        $sha = $this->archivo($this->dato());
        $this->indice([$sha]);
        switch ($caso) {
            case 'duplicado': $this->indice([$sha, $sha]);
                break;
            case 'falta_archivo': unlink($this->directorio.'/'.$sha.'.json');
                break;
            case 'alterado': file_put_contents($this->directorio.'/'.$sha.'.json', ' ', FILE_APPEND);
                break;
            case 'ruta': $this->indice(['../privado']);
                break;
            case 'demasiados': $this->indice(array_fill(0, 21, $sha));
                break;
            case 'version': file_put_contents($this->directorio.'/indice.json', '{"version":"otra","productos":[]}');
                break;
            case 'clave_extra': file_put_contents($this->directorio.'/indice.json', '{"version":"agua-publicaciones-1","productos":[],"ruta":"privado"}');
                break;
            case 'json_roto': file_put_contents($this->directorio.'/indice.json', '{');
                break;
            case 'tamano_indice': file_put_contents($this->directorio.'/indice.json', str_repeat(' ', 16385));
                break;
            case 'tamano_producto': file_put_contents($this->directorio.'/'.$sha.'.json', str_repeat(' ', 2000001));
                break;
        }
        $this->getJson('/api/v1/inundaciones/catalogo')->assertStatus(503)
            ->assertJsonPath('error.codigo', 'PRODUCTO_INVALIDO')->assertJsonMissingPath('productos');
        $this->getJson(self::URL)->assertStatus(503)->assertJsonPath('error.codigo', 'PRODUCTO_INVALIDO');
        Http::assertNothingSent();
    }

    public static function catalogosInvalidos(): array
    {
        return array_map(fn ($caso) => [$caso], ['duplicado', 'falta_archivo', 'alterado', 'ruta',
            'demasiados', 'version', 'clave_extra', 'json_roto', 'tamano_indice', 'tamano_producto']);
    }

    #[DataProvider('productosInvalidos')]
    public function test_rechaza_productos_incoherentes_aunque_su_huella_coincida(string $caso): void
    {
        $dato = $this->dato();
        switch ($caso) {
            case 'prueba': $dato['tipoEvidencia'] = 'prueba_controlada';
                break;
            case 'futuro': $dato['comparacion']['comparacion'] = '2099-01-01';
                break;
            case 'fecha_nula': $dato['comparacion']['comparacion'] = "2025-03-\0";
                break;
            case 'fecha_imposible': $dato['comparacion']['comparacion'] = '2025-02-30';
                break;
            case 'falta_campo': unset($dato['fuente']['producto']);
                break;
            case 'campo_privado': $dato['archivoPrivado'] = '/tmp/escena.tif';
                break;
            case 'area': $dato['resumen']['aguaNuevaKm2'] = 5;
                break;
            case 'booleano': $dato['cobertura']['areaEstudiadaKm2'] = true;
                break;
            case 'fraccion': $dato['cobertura']['fraccionComparable'] = 1;
                break;
            case 'inundacion': $dato['resumen']['inundacionConfirmada'] = true;
                break;
            case 'anillo': $dato['capa']['features'][0]['geometry']['coordinates'][0][0] = [0, 0];
                break;
            case 'coordenada': $dato['capa']['features'][0]['geometry']['coordinates'][0][0] = [181, 0];
                break;
            case 'id_duplicado': $dato['capa']['features'][] = $dato['capa']['features'][0];
                break;
            case 'territorio': $dato['territorio']['identificador'] = 'BO0799';
                break;
        }
        $this->indice([$this->archivo($dato)]);
        $this->getJson(self::URL)->assertStatus(503)->assertJsonPath('error.codigo', 'PRODUCTO_INVALIDO');
    }

    public static function productosInvalidos(): array
    {
        return array_map(fn ($caso) => [$caso], ['prueba', 'futuro', 'fecha_nula', 'fecha_imposible',
            'falta_campo', 'campo_privado', 'area', 'booleano', 'fraccion', 'inundacion',
            'anillo', 'coordenada', 'id_duplicado', 'territorio']);
    }

    public function test_la_validacion_de_filtros_ocurre_antes_de_leer_productos(): void
    {
        file_put_contents($this->directorio.'/indice.json', '{');
        $this->getJson(self::URL.'&modo=demo')->assertStatus(422)->assertJsonPath('error.codigo', 'FILTROS_INVALIDOS');
    }

    public function test_no_sigue_enlaces_simbolicos_de_productos(): void
    {
        $sha = $this->archivo($this->dato());
        $archivo = $this->directorio.'/'.$sha.'.json';
        $fuera = $this->directorio.'-externo.json';
        rename($archivo, $fuera);
        try {
            if (! @symlink($fuera, $archivo)) {
                $this->markTestSkipped('El sistema no permite crear enlaces en esta prueba.');
            }
            $this->indice([$sha]);
            $this->getJson(self::URL)->assertStatus(503)->assertJsonPath('error.codigo', 'PRODUCTO_INVALIDO');
        } finally {
            @unlink($fuera);
        }
    }
}
