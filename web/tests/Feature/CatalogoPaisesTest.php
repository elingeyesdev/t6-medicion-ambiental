<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CatalogoPaisesTest extends TestCase
{
    public function test_recupera_el_catalogo_original_sin_anunciar_cobertura_continental(): void
    {
        Http::preventStrayRequests();
        $respuesta = $this->getJson('/api/v1/paises')->assertOk()->assertJsonCount(13, 'datos')
            ->assertJsonPath('consultasDisponibles.0.territorio', 'BO07');
        $datos = collect($respuesta->json('datos'));
        $this->assertCount(12, $datos->where('tipo', 'pais'));
        $this->assertSame('territorio', $datos->firstWhere('codigo', 'GF')['tipo']);
        $this->assertCount(13, $datos->unique('codigo'));
        foreach ($respuesta->json('fuentes') as $fuente) {
            $this->assertSame($fuente['sha256'], hash_file('sha256', base_path('../'.$fuente['archivo'])));
        }
        Http::assertNothingSent();
    }

    public function test_filtra_por_codigo_y_no_confunde_el_catalogo_con_una_consulta_de_mediciones(): void
    {
        Http::preventStrayRequests();
        $this->getJson('/api/v1/paises?codigo=BR')->assertOk()->assertJsonCount(1, 'datos')
            ->assertJsonPath('datos.0.nombre', 'Brasil')->assertJsonMissingPath('resumen.totalRegistros');
        $this->getJson('/api/v1/paises?codigo=XX')->assertNotFound();
        $this->getJson('/api/v1/paises?municipio=BO070101')->assertUnprocessable();
        $this->getJson('/api/v1/paises?codigo[]=BR')->assertUnprocessable();
        Http::assertNothingSent();
    }
}
