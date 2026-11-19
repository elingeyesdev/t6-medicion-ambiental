<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class SeguimientoTerritorialTest extends TestCase
{
    use RefreshDatabase;

    public function test_guardar_evaluar_crear_tarea_y_registrar_resultado(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15T15:00:00Z'));
        config(['focos.clave' => 'abcdefghijklmnop']);
        $producto = config('focos.producto');
        Http::fake([
            '*/data_availability/*' => fn () => Http::response("data_id,min_date,max_date\n{$producto},2026-11-01,2026-11-15\n"),
            '*/area/*' => fn () => Http::response(
                "latitude,longitude,acq_date,acq_time,confidence,satellite,instrument,version,frp\n"
                ."-17.7900,-63.1800,2026-11-10,1530,h,N,VIIRS,2.0NRT,3\n"
                ."-16.3800,-60.9600,2026-11-10,1530,n,N,VIIRS,2.0NRT,4\n"
            ),
        ]);

        $dueno = User::factory()->create();
        $this->actingAs($dueno)->get(route('seguimiento.index'))->assertOk()->assertSee('Guardar una zona');
        $this->post(route('seguimiento.guardar'), [
            'nombre' => 'Parcela de prueba', 'latitud' => '-17.7833', 'longitud' => '-63.1812', 'radio_km' => '10',
        ])->assertRedirect(route('seguimiento.zona', 1));
        $this->post(route('seguimiento.tareas', 1), ['titulo' => 'Antes de evaluar'])->assertConflict();
        $this->post(route('seguimiento.evaluar', 1), ['fecha' => '2026-11-10'])->assertRedirect(route('seguimiento.zona', 1));
        $this->get(route('seguimiento.zona', 1))->assertOk()->assertSee('Revisión prioritaria');
        $evaluacion = json_decode(DB::table('zonas_seguimiento')->value('evaluacion'), true);
        $this->assertSame(1, $evaluacion['detecciones']);
        $this->assertSame(1, $evaluacion['confianzaAlta']);

        $versionAnterior = hash('sha256', DB::table('zonas_seguimiento')->value('evaluacion'));
        $this->post(route('seguimiento.evaluar', 1), ['fecha' => '2026-11-11'])->assertRedirect(route('seguimiento.zona', 1));
        $this->post(route('seguimiento.tareas', 1), [
            'titulo' => 'Verificar la entrada', 'version_evaluacion' => $versionAnterior,
        ])->assertRedirect(route('seguimiento.zona', 1))->assertSessionHasErrors('titulo');
        $this->assertDatabaseCount('tareas_seguimiento', 0);

        $actual = DB::table('zonas_seguimiento')->value('evaluacion');
        $version = hash('sha256', $actual);
        $this->get(route('seguimiento.zona', 1))->assertOk()->assertSee('value="'.$version.'"', false);
        $this->post(route('seguimiento.tareas', 1), [
            'titulo' => 'Verificar la entrada', 'version_evaluacion' => $version,
        ])->assertRedirect(route('seguimiento.zona', 1));
        $this->assertSame(json_decode($actual, true), json_decode(DB::table('tareas_seguimiento')->value('evaluacion'), true));
        $this->assertDatabaseHas('tareas_seguimiento', ['id' => 1, 'resultado_tipo' => null]);
        $this->patch(route('seguimiento.resultado', [1, 1]), [
            'resultado_tipo' => 'sin_incendio', 'resultado_nota' => 'Acceso revisado.',
        ])->assertRedirect(route('seguimiento.zona', 1));
        $this->get(route('seguimiento.zona', 1))->assertOk()->assertSee('Sin incendio en la revisión')->assertSee('Acceso revisado.');
        $this->patch(route('seguimiento.resultado', [1, 1]), [
            'resultado_tipo' => 'sin_verificar', 'resultado_nota' => 'Otro resultado.',
        ])->assertConflict();
        $this->assertDatabaseHas('tareas_seguimiento', ['id' => 1, 'resultado_nota' => 'Acceso revisado.']);

        $this->actingAs(User::factory()->create())->get(route('seguimiento.zona', 1))->assertNotFound();
        $this->patch(route('seguimiento.resultado', [1, 1]), ['resultado_tipo' => 'sin_verificar', 'resultado_nota' => 'Otra cuenta.'])->assertNotFound();
        $this->post(route('seguimiento.evaluar', 1))->assertNotFound();
        $this->get(route('seguimiento.index'))->assertOk()->assertDontSee('Parcela de prueba');
    }

    public function test_precarga_coordenadas_validas_sin_crear_la_zona(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get(route('seguimiento.index', ['latitud' => '-17.783391', 'longitud' => '-63.181258']))
            ->assertOk()->assertSee('value="-17.783391"', false)->assertSee('value="-63.181258"', false);
        $this->get(route('seguimiento.index', ['latitud' => ['-17.783391'], 'longitud' => '999']))
            ->assertOk()->assertDontSee('value="-17.783391"', false)->assertDontSee('value="999"', false);
        $this->assertDatabaseCount('zonas_seguimiento', 0);
    }

    public function test_distingue_falta_de_cobertura_y_rechaza_zonas_fuera_de_santa_cruz(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15T15:00:00Z'));
        config(['focos.clave' => 'abcdefghijklmnop']);
        $producto = config('focos.producto');
        Http::fake(['*/data_availability/*' => fn () => Http::response("data_id,min_date,max_date\n{$producto},2026-10-01,2026-10-15\n")]);

        $this->actingAs(User::factory()->create());
        $this->post(route('seguimiento.guardar'), ['nombre' => 'Fuera', 'latitud' => 0, 'longitud' => 0, 'radio_km' => 10])
            ->assertSessionHasErrors('latitud');
        $this->post(route('seguimiento.guardar'), [
            'nombre' => 'Vigilancia', 'latitud' => -17.7833, 'longitud' => -63.1812, 'radio_km' => 10,
        ])->assertRedirect(route('seguimiento.zona', 1));
        $this->post(route('seguimiento.evaluar', 1), ['fecha' => '2026-11-16'])->assertSessionHasErrors('fecha');
        $this->post(route('seguimiento.evaluar', 1))->assertRedirect(route('seguimiento.zona', 1));
        $this->get(route('seguimiento.zona', 1))->assertSee('Período sin cobertura')->assertDontSee('Detecciones cercanas');
        $evaluacion = json_decode(DB::table('zonas_seguimiento')->value('evaluacion'), true);
        $this->assertNull($evaluacion['detecciones']);
    }
}
