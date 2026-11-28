<?php

namespace Tests\Feature;

use App\Models\PuntoAmbiental;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CapturaCatalogoTiempoTest extends TestCase
{
    use RefreshDatabase;

    private function preparar(): void
    {
        config(['datos.tiempo_habilitado' => true]);
        foreach (['norte', 'sur'] as $codigo) {
            PuntoAmbiental::create(['codigo' => $codigo, 'nombre' => $codigo, 'pais' => 'BO',
                'latitud' => -17.78, 'longitud' => -63.18, 'tipo' => 'referencia']);
        }
    }

    private function respuesta(): array
    {
        return ['utc_offset_seconds' => 0,
            'current' => ['time' => now()->subMinute()->timestamp, 'temperature_2m' => 0, 'relative_humidity_2m' => 65],
            'current_units' => ['temperature_2m' => '°C', 'relative_humidity_2m' => '%']];
    }

    public function test_captura_catalogo_es_repetible_y_conserva_cero_y_fuente(): void
    {
        $this->preparar();
        $respuesta = $this->respuesta();
        Http::fake(['api.open-meteo.com/*' => Http::response($respuesta)]);
        $this->artisan('geosur:capturar-tiempo --todos --consultar-proveedor')->assertSuccessful();
        $this->artisan('geosur:capturar-tiempo --todos --consultar-proveedor')->assertSuccessful();
        $this->assertDatabaseCount('lecturas_ambientales', 4);
        $this->assertDatabaseHas('lecturas_ambientales', ['metrica' => 'temperatura', 'valor' => 0,
            'origen' => 'estimado', 'fuente' => 'open-meteo-modelo']);
    }

    public function test_fallo_de_un_punto_permite_capturar_el_siguiente(): void
    {
        $this->preparar();
        Http::fake(['api.open-meteo.com/*' => Http::sequence()->pushStatus(503)->push($this->respuesta())]);
        $this->artisan('geosur:capturar-tiempo --todos --consultar-proveedor')->assertFailed();
        $this->assertDatabaseCount('lecturas_ambientales', 2);
    }

    public function test_sin_habilitacion_no_consulta_proveedor(): void
    {
        $this->preparar();
        config(['datos.tiempo_habilitado' => false]);
        $this->artisan('geosur:capturar-tiempo --todos --consultar-proveedor')->assertFailed();
        Http::assertNothingSent();
        $this->assertDatabaseCount('lecturas_ambientales', 0);
    }
}
