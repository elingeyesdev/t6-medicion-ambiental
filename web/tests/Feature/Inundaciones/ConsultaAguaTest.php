<?php

namespace Tests\Feature\Inundaciones;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ConsultaAguaTest extends TestCase
{
    private const URL = '/api/v1/inundaciones/consulta';

    private function ingresar(array $abilities = ['cuenta']): void
    {
        Sanctum::actingAs(User::factory()->make(['id' => 123]), $abilities);
    }

    public function test_consulta_y_catalogo_exigen_cuenta(): void
    {
        $this->getJson(self::URL)->assertUnauthorized();
        $this->getJson('/api/v1/inundaciones/catalogo')->assertUnauthorized();
    }

    public function test_token_sin_alcance_y_cuenta_inactiva_no_acceden(): void
    {
        $this->ingresar([]);
        $this->getJson(self::URL)->assertForbidden();
        Sanctum::actingAs(User::factory()->make(['id' => 124, 'activo' => false]), ['cuenta']);
        $this->getJson('/api/v1/inundaciones/catalogo')->assertUnauthorized();
    }

    public function test_catalogo_vacio_no_equivale_a_un_resultado_sin_cambios(): void
    {
        $this->ingresar();
        $this->getJson('/api/v1/inundaciones/catalogo')->assertOk()
            ->assertJsonPath('estado', 'sin_productos_validados')->assertExactJson([
                'versionContrato' => 'agua-0.1-candidata', 'estado' => 'sin_productos_validados', 'productos' => [],
            ])->assertHeader('Cache-Control', 'no-store, private');
        Http::assertNothingSent();
    }

    public function test_filtro_valido_no_publica_resultados_controlados_ni_descarga(): void
    {
        $this->ingresar();
        foreach (['BO07', 'BO0701', 'BO070101'] as $id) {
            $r = $this->getJson(self::URL.'?territorio='.$id.'&referencia=2024-09-01&comparacion=2025-03-07')
                ->assertStatus(503)->assertJsonPath('error.codigo', 'PRODUCTO_NO_DISPONIBLE')
                ->assertHeader('Cache-Control', 'no-store, private')->json();
            $this->assertArrayNotHasKey('datos', $r);
            $this->assertArrayNotHasKey('resumen', $r);
        }
        Http::assertNothingSent();
    }

    #[DataProvider('invalidos')]
    public function test_rechaza_filtros_antes_de_buscar_productos(string $query, int $status, string $codigo): void
    {
        $this->ingresar();
        $this->getJson(self::URL.$query)->assertStatus($status)->assertJsonPath('error.codigo', $codigo);
        Http::assertNothingSent();
    }

    public static function invalidos(): array
    {
        $base = '?territorio=BO07&referencia=2024-09-01&comparacion=';

        return [
            ['', 422, 'FILTROS_INVALIDOS'],
            [$base.'2024-02-30', 422, 'FILTROS_INVALIDOS'],
            [$base.'2024-9-03', 422, 'FILTROS_INVALIDOS'],
            [$base.'2099-01-01', 422, 'FILTROS_INVALIDOS'],
            [$base.'2024-09-01', 422, 'FILTROS_INCOMPATIBLES'],
            [$base.'2024-08-31', 422, 'FILTROS_INCOMPATIBLES'],
            [$base.'2025-03-07&territorio=BO070101', 422, 'FILTROS_INVALIDOS'],
            [$base.'2025-03-07&modo=demo', 422, 'FILTROS_INVALIDOS'],
            ['?territorio[]=BO07&referencia=2024-09-01&comparacion=2025-03-07', 422, 'FILTROS_INVALIDOS'],
            ['?territorio=BO0799&referencia=2024-09-01&comparacion=2025-03-07', 404, 'TERRITORIO_NO_DISPONIBLE'],
            ['?territorio=../privado&referencia=2024-09-01&comparacion=2025-03-07', 422, 'FILTROS_INVALIDOS'],
        ];
    }

    public function test_la_ruta_historica_conserva_su_503(): void
    {
        $this->getJson('/api/v1/inundaciones')->assertStatus(503)->assertJsonPath('error.codigo', 'MODULO_NO_DISPONIBLE');
    }
}
