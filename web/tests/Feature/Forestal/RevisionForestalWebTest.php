<?php

namespace Tests\Feature\Forestal;

use App\Enums\RolCuenta;
use App\Http\Controllers\RevisionForestalController;
use App\Models\User;
use App\Services\Api\ErrorConsulta;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class RevisionForestalWebTest extends TestCase
{
    use RefreshDatabase;

    private const ID = '78a62654465d330e4aa0f241d74e237bfa65de709dee827099dc8594d4e88730';

    protected function setUp(): void
    {
        parent::setUp();
        config(['forestal.revision_local' => true]);
    }

    private function usuario(bool $activo = true): User
    {
        return User::factory()->create(['rol' => RolCuenta::Visualizador, 'activo' => $activo]);
    }

    private function urlConsulta(string $extra = ''): string
    {
        return '/revision/forestal/consulta?id='.self::ID.'&desde=2024&hasta=2025'.$extra;
    }

    /** Doble HTTP: no define, copia ni carga un repositorio forestal alternativo. */
    private function lectura(array|ErrorConsulta $respuesta): RevisionForestalController
    {
        $controller = new class($respuesta) extends RevisionForestalController
        {
            public array $llamadas = [];

            public function __construct(private array|ErrorConsulta $respuesta) {}

            protected function consultarPaquete(string $id, int $desde, int $hasta): array
            {
                $this->llamadas[] = [$id, $desde, $hasta];
                if ($this->respuesta instanceof ErrorConsulta) {
                    throw $this->respuesta;
                }

                return $this->respuesta;
            }
        };
        $this->app->instance(RevisionForestalController::class, $controller);

        return $controller;
    }

    private function respuesta(): array
    {
        return ['id' => self::ID, 'revision' => true, 'resumen' => [
            'consulta' => ['desde' => 2024, 'hasta' => 2025],
            'total_periodo_ha' => 1.9148918, 'conteos_pixeles' => ['perdida_periodo' => 26],
            'serie' => [['anio' => 2024, 'perdida_ha' => 1.84124289], ['anio' => 2025, 'perdida_ha' => 0.07364891]],
            'fuente' => ['producto' => 'Hansen GFC', 'version' => 'GFC-2025-v1.13'],
        ], 'capa' => ['type' => 'FeatureCollection', 'features' => []]];
    }

    public function test_configuracion_por_defecto_cerrada_y_directorio_privado(): void
    {
        // Evaluar los defaults sin depender de variables locales del equipo.
        $config = file_get_contents(config_path('forestal.php'));
        $this->assertStringContainsString("env('FORESTAL_REVISION_LOCAL', false)", $config);
        $this->assertStringContainsString("storage_path('app/private/forestal/revisiones')", $config);
    }

    public function test_flag_apagado_y_entornos_no_locales_devuelven_404_incluso_a_invitados(): void
    {
        foreach ([['testing', false], ['local', false], ['production', true], ['staging', true]] as [$entorno, $flag]) {
            $this->app->instance('env', $entorno);
            config(['forestal.revision_local' => $flag]);
            $this->get('/revision/forestal')->assertNotFound();
            $this->getJson($this->urlConsulta())->assertNotFound();
        }
    }

    public function test_produccion_con_flag_y_usuario_autorizado_sigue_en_404(): void
    {
        $this->actingAs($this->usuario());
        $this->app->instance('env', 'production');
        $this->get('/revision/forestal')->assertNotFound();
        $this->getJson($this->urlConsulta())->assertNotFound();
    }

    public function test_invitado_requiere_sesion_y_el_json_responde_401_aun_sin_accept(): void
    {
        $this->get('/revision/forestal?id='.self::ID)->assertRedirect(route('login'));
        $this->get($this->urlConsulta())->assertUnauthorized()->assertHeader('Content-Type', 'application/json');
    }

    public function test_cuenta_inactiva_no_accede_a_vista_ni_consulta(): void
    {
        $usuario = $this->usuario(false);
        $this->actingAs($usuario)->get('/revision/forestal')->assertRedirect(route('login'));
        $this->actingAs($usuario)->get($this->urlConsulta())->assertUnauthorized()
            ->assertJsonPath('error.codigo', 'NO_AUTENTICADO');
    }

    public function test_se_aplican_ambos_permisos_existentes_sin_atajos_de_autenticacion(): void
    {
        $this->actingAs($this->usuario());
        foreach (['mapa.ver', 'datos.consultar'] as $denegado) {
            Gate::define('mapa.ver', fn () => $denegado !== 'mapa.ver');
            Gate::define('datos.consultar', fn () => $denegado !== 'datos.consultar');
            $this->get('/revision/forestal')->assertForbidden();
            $this->getJson($this->urlConsulta())->assertForbidden();
        }
    }

    public function test_vista_con_layout_existente_y_campos_sin_datos_inventados_ni_entrada_menu(): void
    {
        $this->actingAs($this->usuario());
        $r = $this->get('/revision/forestal?id='.self::ID);
        $r->assertOk()->assertSeeText('Revisión técnica. Método pendiente de aprobación.')
            ->assertSee('vendor/tabler/tabler.min.css')->assertSee('vendor/leaflet/leaflet.js')
            ->assertSee('js/revision/forestal-inicio.js')->assertSee('configuracion-mapa')
            ->assertSee('value="'.self::ID.'"', false)->assertDontSee('total municipal');
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
        $documento = new DOMDocument;
        $documento->loadHTML($r->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($documento);
        $this->assertSame(0, $xpath->query('//aside//a[contains(@href,"revision/forestal")]')->length);
        $this->assertSame('', $xpath->evaluate('string(//input[@id="rf-desde"]/@value)'));
        $this->assertSame(1, $xpath->query('//*[@id="rf-resultados"][@hidden]')->length);
        $this->assertSame(0, $xpath->query('//*[@id="rf-serie"]/tr')->length);
        $this->get('/revision/forestal')->assertOk()->assertSeeText('No hay un paquete seleccionado.');
        $this->app->instance('env', 'local');
        $this->get('/revision/forestal?id='.self::ID.'&desde=2024&hasta=2025')->assertOk();
    }

    public function test_rechaza_sha_fechas_arrays_repetidos_y_campos_desconocidos_antes_de_leer(): void
    {
        $this->actingAs($this->usuario());
        $controller = $this->lectura($this->respuesta());
        $base = 'id='.self::ID.'&desde=2024&hasta=2025';
        $queries = [
            str_replace('desde=2024', 'desde=2024-01-01', $base),
            str_replace('hasta=2025', 'hasta=2025-02', $base),
            str_replace('desde=2024', 'desde=24', $base),
            str_replace('desde=2024', 'desde=2026', $base),
            str_replace(self::ID, '../paquete', $base),
            str_replace(self::ID, strtoupper(self::ID), $base),
            'desde=2024&hasta=2025', $base.'&%64esde=2024',
            $base.'&id[]=x', $base.'&desconocido=x',
        ];
        foreach ($queries as $query) {
            $this->get('/revision/forestal/consulta?'.$query)->assertUnprocessable()
                ->assertJsonPath('error.codigo', 'FILTROS_INVALIDOS');
        }
        $this->assertSame([], $controller->llamadas);
        $this->get('/revision/forestal?id=%3Cscript%3E')->assertUnprocessable()->assertDontSee('<script>', false);
    }

    public function test_respuesta_controlada_preserva_root_totales_conteos_fuente_y_capa(): void
    {
        $this->actingAs($this->usuario());
        $esperada = $this->respuesta();
        $controller = $this->lectura($esperada);
        config(['forestal.directorio_revisiones' => 'C:/directorio-privado-prueba']);
        $r = $this->get($this->urlConsulta());
        $r->assertOk()->assertHeader('Content-Type', 'application/json')->assertExactJson($esperada);
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
        $this->assertSame([[self::ID, 2024, 2025]], $controller->llamadas);
    }

    public function test_ventana_no_disponible_y_error_del_lector_conservan_estado_y_json(): void
    {
        $this->actingAs($this->usuario());
        $this->lectura(new ErrorConsulta('FILTROS_INVALIDOS', 'Años no disponibles.', 422, ['desde' => ['Fuera de ventana.']]));
        $this->getJson($this->urlConsulta())->assertUnprocessable()->assertJsonPath('error.campos.desde.0', 'Fuera de ventana.');
        // Nueva instancia resuelve otro controller; no conserva el controller cacheado de la ruta.
        $this->app['router']->getRoutes()->getByName('revision.forestal.consulta')->flushController();
        $this->lectura(new ErrorConsulta('REVISION_NO_DISPONIBLE', 'No hay paquete disponible.', 404));
        $this->getJson($this->urlConsulta())->assertNotFound()->assertJsonPath('error.mensaje', 'No hay paquete disponible.');
    }

    public function test_consulta_tiene_throttle_y_no_admite_post(): void
    {
        $this->actingAs($this->usuario());
        $this->lectura($this->respuesta());
        for ($i = 0; $i < 12; $i++) {
            $this->getJson($this->urlConsulta())->assertOk();
        }
        $this->getJson($this->urlConsulta())->assertTooManyRequests();
        $this->postJson('/revision/forestal/consulta')->assertMethodNotAllowed();
        $ruta = $this->app['router']->getRoutes()->getByName('revision.forestal');
        $this->assertContains('throttle:30,1,forestal-revision-vista', $ruta->middleware());
    }
}
