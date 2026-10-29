<?php

namespace Tests\Feature;

use App\Models\PuntoAmbiental;
use App\Models\User;
use App\Services\Territorios\RegionesEnvirosense;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegionesEnvirosenseTest extends TestCase
{
    use RefreshDatabase;

    public function test_recupera_las_241_geometrias_sin_simplificar_ni_ejecutar_el_origen(): void
    {
        $regiones = app(RegionesEnvirosense::class);
        $this->assertCount(241, $regiones->listado());
        $this->assertCount(13, array_unique(array_column($regiones->listado(), 'pais')));
        foreach ($regiones->listado() as $region) {
            $limite = $regiones->limite($region['codigo']);
            $this->assertSame($region['pais'], $limite['properties']['pais']);
            $this->assertSame($region['nombre'], $limite['properties']['nombre']);
            $this->assertContains($limite['geometry']['type'], ['Polygon', 'MultiPolygon']);
        }
    }

    public function test_region_filtra_puntos_por_poligono_y_conserva_la_procedencia(): void
    {
        foreach ([['santa-cruz', -17.78, -63.18], ['lapaz', -16.5, -68.15]] as [$codigo, $lat, $lon]) {
            PuntoAmbiental::create(['codigo' => $codigo, 'nombre' => $codigo, 'pais' => 'BO', 'latitud' => $lat, 'longitud' => $lon, 'tipo' => 'referencia']);
        }
        $region = collect(app(RegionesEnvirosense::class)->listado())->first(fn ($r) => $r['pais'] === 'BO' && $r['nombre'] === 'Santa Cruz');
        $this->actingAs(User::factory()->create())->getJson('/mapa/puntos?pais=BO&region='.$region['codigo'])
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('datos.0.codigo', 'santa-cruz')
            ->assertJsonPath('limite.properties.nombre', 'Santa Cruz')->assertJsonPath('limite.geometry.type', 'Polygon');
        $this->getJson('/mapa/puntos?pais=AR&region='.$region['codigo'])->assertUnprocessable();
        $this->getJson('/mapa/puntos?region='.$region['codigo'])->assertUnprocessable();
        $this->getJson('/mapa/puntos?pais=BO&region=../secret')->assertUnprocessable();
    }

    public function test_region_sin_puntos_conserva_su_geometria_sin_inventar_lecturas(): void
    {
        $region = collect(app(RegionesEnvirosense::class)->listado())->firstWhere('pais', 'UY');
        $this->actingAs(User::factory()->create())->getJson('/mapa/puntos?pais=UY&region='.$region['codigo'])
            ->assertOk()->assertJsonPath('total', 0)->assertJsonPath('datos', [])->assertJsonPath('limite.properties.codigo', $region['codigo']);
        $this->assertDatabaseCount('lecturas_ambientales', 0);
    }
}
