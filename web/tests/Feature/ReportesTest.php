<?php

namespace Tests\Feature;

use App\Models\LecturaAmbiental;
use App\Models\PuntoAmbiental;
use App\Models\User;
use App\Services\Datos\RegistrarLecturas;
use App\Services\Simulaciones\SimularEscenario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportesTest extends TestCase
{
    use RefreshDatabase;

    public function test_reportes_grandes_no_se_recortan_silenciosamente_y_pdf_conserva_varias_paginas(): void
    {
        $this->cliente();
        $punto = PuntoAmbiental::create(['codigo' => 'prueba-larga', 'nombre' => 'Santa Cruz · datos de demostración', 'pais' => 'BO', 'latitud' => -17.8, 'longitud' => -63.2, 'tipo' => 'referencia']);
        $filas = [];
        for ($i = 0; $i < 1001; $i++) {
            $filas[] = ['punto_id' => $punto->id, 'metrica' => 'temperatura', 'valor' => $i % 30, 'instante' => now()->subHours(2)->addSeconds($i)->format('Y-m-d H:i:s'),
                'hora_utc' => now()->subHours(2)->startOfHour()->format('Y-m-d H:i:s'), 'origen' => 'simulado', 'fuente' => 'demo-local', 'latitud' => -17.8, 'longitud' => -63.2];
        }
        foreach (array_chunk($filas, 100) as $bloque) {
            DB::table('lecturas_ambientales')->insert($bloque);
        }
        $this->postJson('/api/v1/reportes', ['titulo' => 'Grande', 'base' => 'mediciones', 'origen' => 'simulado'])->assertUnprocessable();
        $this->assertDatabaseCount('reportes_ambientales', 0);
        $primeras = LecturaAmbiental::where('punto_id', $punto->id)->orderBy('id')->limit(100)->pluck('id');
        LecturaAmbiental::where('punto_id', $punto->id)->whereNotIn('id', $primeras)->delete();
        $r = $this->crear(['titulo' => 'GeoSur · Revisión de datos simulados', 'origen' => 'simulado']);
        $this->assertCount(100, $r['contenido']['filas']);
        $pdf = $this->get('/reportes/'.$r['id'].'/descargar/pdf')->assertOk();
        $this->assertGreaterThan(20000, strlen($pdf->getContent()));
        if (getenv('GEOSUR_EVIDENCIA_REPORTES')) {
            file_put_contents(base_path('../tmp/reporte64-visual.pdf'), $pdf->getContent());
            file_put_contents(base_path('../tmp/reporte64-visual.xlsx'), $this->get('/reportes/'.$r['id'].'/descargar/xlsx')->getContent());
        }
    }

    private function cliente(string $rol = 'analista'): User
    {
        $u = User::factory()->create(['rol' => $rol]);
        Sanctum::actingAs($u, ['cuenta']);

        return $u;
    }

    private function lecturas(): void
    {
        PuntoAmbiental::create(['codigo' => 'campus', 'nombre' => 'Campus', 'pais' => 'BO', 'latitud' => -17.8, 'longitud' => -63.2, 'tipo' => 'manual']);
        app(RegistrarLecturas::class)->ejecutar([
            ['punto' => 'campus', 'metrica' => 'temperatura', 'valor' => 0, 'instante' => now()->subHours(2)->toIso8601ZuluString(), 'origen' => 'observado', 'fuente' => 'manual'],
            ['punto' => 'campus', 'metrica' => 'temperatura', 'valor' => 20, 'instante' => now()->subHour()->toIso8601ZuluString(), 'origen' => 'observado', 'fuente' => 'manual'],
            ['punto' => 'campus', 'metrica' => 'temperatura', 'valor' => 45, 'instante' => now()->subHour()->toIso8601ZuluString(), 'origen' => 'simulado', 'fuente' => 'demo-local'],
            ['punto' => 'campus', 'metrica' => 'humedad', 'valor' => 80, 'instante' => now()->subHour()->toIso8601ZuluString(), 'origen' => 'estimado', 'fuente' => 'modelo'],
        ]);
    }

    private function crear(array $cambios = []): array
    {
        return $this->postJson('/api/v1/reportes', $cambios + ['titulo' => 'Reporte de revisión', 'base' => 'mediciones'])->assertCreated()->json();
    }

    public function test_condiciones_conservan_cero_y_no_juntan_instantes_fuentes_ni_variables_faltantes(): void
    {
        $this->freezeTime();
        $this->cliente();
        $this->lecturas();
        $instante = now()->subHours(2)->toIso8601ZuluString();
        app(RegistrarLecturas::class)->ejecutar([
            ['punto' => 'campus', 'metrica' => 'humedad', 'valor' => 70, 'instante' => $instante, 'origen' => 'observado', 'fuente' => 'manual'],
            ['punto' => 'campus', 'metrica' => 'humedad', 'valor' => 90, 'instante' => now()->subHour()->toIso8601ZuluString(), 'origen' => 'observado', 'fuente' => 'otra'],
        ]);
        $p = $this->postJson('/api/v1/plantillas', ['nombre' => 'Frío con humedad', 'tipo' => 'ESTANDAR_LOGISTICA', 'columnas' => ['valor'], 'grafico' => true,
            'condiciones' => [['variable' => 'temperatura', 'operador' => '=', 'valor' => 0], ['variable' => 'humedad', 'operador' => '>', 'valor' => 60]]])->assertCreated()->json('id');
        $r = $this->crear(['plantilla' => $p, 'metrica' => 'temperatura']);
        $this->assertCount(1, $r['contenido']['filas']);
        $this->assertSame(0, $r['contenido']['filas'][0]['valor']);
        $this->assertSame('ESTANDAR_LOGISTICA', $r['contenido']['plantilla']['tipo']);
        $this->assertCount(2, $r['contenido']['plantilla']['condiciones']);
        $this->patchJson('/api/v1/plantillas/'.$p, ['nombre' => 'Faltante', 'columnas' => ['valor'], 'grafico' => true,
            'condiciones' => [['variable' => 'ruido', 'operador' => '=', 'valor' => 0]]])->assertOk();
        $this->assertSame([], $this->crear(['plantilla' => $p])['contenido']['filas']);
    }

    public function test_plantillas_rechazan_operadores_y_tipos_inventados(): void
    {
        $this->cliente();
        foreach ([['tipo' => 'inventado'], ['condiciones' => [['variable' => 'temperatura', 'operador' => 'eval', 'valor' => 0]]],
            ['condiciones' => [['variable' => 'temperatura', 'operador' => '>', 'valor' => 'NaN']]],
            ['condiciones' => [['variable' => 'password', 'operador' => '=', 'valor' => 0]]]] as $mala) {
            $this->postJson('/api/v1/plantillas', $mala + ['nombre' => 'Mala', 'columnas' => ['valor'], 'grafico' => true])->assertUnprocessable();
        }
    }

    public function test_cuentas_sin_permiso_no_generan_reportes(): void
    {
        $this->postJson('/api/v1/reportes', ['titulo' => 'x', 'base' => 'mediciones'])->assertUnauthorized();
        $this->cliente('visualizador');
        $this->postJson('/api/v1/reportes', ['titulo' => 'x', 'base' => 'mediciones'])->assertForbidden();
        $this->get('/reportes')->assertOk()->assertDontSee('Generar reporte');
        $this->assertDatabaseCount('reportes_ambientales', 0);
    }

    public function test_snapshot_conserva_cero_y_no_promedia_fuentes_o_variables(): void
    {
        $this->cliente();
        $this->lecturas();
        $r = $this->crear();
        $this->assertCount(3, $r['contenido']['filas']);
        $this->assertCount(2, $r['contenido']['resumen']);
        $this->assertSame(0, $r['contenido']['filas'][0]['valor']);
        $this->assertSame(10, $r['contenido']['resumen'][0]['promedio']);
        $this->assertNotContains('simulado', array_column($r['contenido']['filas'], 'origen'));
        $this->assertArrayNotHasKey('usuario_id', $r);
    }

    public function test_simulados_se_incluyen_solo_al_elegirlos(): void
    {
        $this->cliente();
        $this->lecturas();
        $r = $this->crear(['origen' => 'todos']);
        $this->assertCount(4, $r['contenido']['filas']);
        $this->assertCount(3, $r['contenido']['resumen']);
        $this->assertContains('simulado', array_column($r['contenido']['filas'], 'origen'));
    }

    public function test_reporte_guardado_no_cambia_si_despues_cambian_lecturas_o_puntos(): void
    {
        $this->cliente();
        $this->lecturas();
        $r = $this->crear();
        LecturaAmbiental::query()->update(['valor' => 99]);
        PuntoAmbiental::query()->update(['nombre' => 'Otro nombre']);
        $this->getJson('/api/v1/reportes/'.$r['id'])->assertOk()->assertJsonPath('contenido', $r['contenido']);
    }

    public function test_propiedad_de_reportes_plantillas_y_descargas_se_valida_en_servidor(): void
    {
        $this->cliente();
        $r = $this->crear();
        $p = $this->postJson('/api/v1/plantillas', ['nombre' => 'Privada', 'columnas' => ['valor'], 'grafico' => true])->assertCreated()->json('id');
        $this->cliente();
        $this->getJson('/api/v1/reportes/'.$r['id'])->assertNotFound();
        $this->get('/reportes/'.$r['id'].'/descargar/pdf')->assertNotFound();
        $this->get('/reportes/'.$r['id'].'/descargar/xlsx')->assertNotFound();
        $this->patchJson('/api/v1/plantillas/'.$p, ['nombre' => 'Ajena', 'columnas' => ['valor'], 'grafico' => true])->assertNotFound();
        $this->deleteJson('/api/v1/plantillas/'.$p)->assertNotFound();
        $this->postJson('/api/v1/reportes', ['titulo' => 'Ajena', 'base' => 'mediciones', 'plantilla' => $p])->assertNotFound();
    }

    public function test_plantillas_se_editan_archivan_y_restauran_sin_alterar_reportes_anteriores(): void
    {
        $this->cliente();
        $this->lecturas();
        $id = $this->postJson('/api/v1/plantillas', ['nombre' => 'Corta', 'columnas' => ['fecha', 'valor'], 'grafico' => true])->assertCreated()->json('id');
        $r = $this->crear(['plantilla' => $id]);
        $this->assertSame(['fecha', 'valor'], $r['contenido']['columnas']);
        $this->patchJson('/api/v1/plantillas/'.$id, ['nombre' => 'Larga', 'columnas' => ['punto', 'valor', 'origen'], 'grafico' => false])->assertOk();
        $this->getJson('/api/v1/reportes/'.$r['id'])->assertJsonPath('contenido.columnas', ['fecha', 'valor']);
        $this->deleteJson('/api/v1/plantillas/'.$id)->assertOk()->assertJsonPath('archivada', true);
        $this->postJson('/api/v1/reportes', ['titulo' => 'x', 'base' => 'mediciones', 'plantilla' => $id])->assertNotFound();
        $this->deleteJson('/api/v1/plantillas/'.$id, ['restaurar' => true])->assertOk()->assertJsonPath('archivada', false);
    }

    public function test_columnas_arbitrarias_futuro_fechas_invalidas_y_datos_del_cliente_se_rechazan(): void
    {
        $this->cliente();
        $this->postJson('/api/v1/plantillas', ['nombre' => 'Mala', 'columnas' => ['password'], 'grafico' => true])->assertUnprocessable();
        $this->postJson('/api/v1/plantillas', ['nombre' => 'Repetida', 'columnas' => ['valor', 'valor'], 'grafico' => true])->assertUnprocessable();
        foreach ([['hasta' => now()->addDay()->toIso8601ZuluString()], ['desde' => '2026-02-30T00:00:00Z'], ['datos' => [['valor' => 999]]], ['usuario_id' => 33], ['metrica' => 'falsa']] as $mala) {
            $this->postJson('/api/v1/reportes', $mala + ['titulo' => 'Malo', 'base' => 'mediciones'])->assertUnprocessable();
        }
        $this->assertDatabaseCount('reportes_ambientales', 0);
    }

    public function test_simulacion_se_exporta_separada_y_solo_si_pertenece_a_la_cuenta(): void
    {
        $u = $this->cliente();
        $s = app(SimularEscenario::class)->crear(['nombre' => 'Caso de prueba', 'metrica' => 'temperatura', 'inicio' => now()->toIso8601ZuluString(), 'dias' => 1, 'intervalo' => 360, 'semilla' => 'reporte',
            'zonas' => [['punto' => 'santacruz', 'escenario' => 'calor', 'nivel' => 'medio']]], $u->id);
        $r = $this->crear(['base' => 'simulacion', 'simulacion' => $s->id]);
        $this->assertCount(5, $r['contenido']['filas']);
        $this->assertSame(['simulado'], array_unique(array_column($r['contenido']['filas'], 'origen')));
        $this->assertDatabaseCount('lecturas_ambientales', 0);
        $this->cliente();
        $this->postJson('/api/v1/reportes', ['titulo' => 'Ajena', 'base' => 'simulacion', 'simulacion' => $s->id])->assertNotFound();
    }

    public function test_pdf_y_excel_son_archivos_reales_y_el_texto_no_se_interpreta_como_codigo(): void
    {
        $this->cliente();
        $this->lecturas();
        PuntoAmbiental::query()->update(['nombre' => '<f>1+1</f>']);
        $r = $this->crear(['titulo' => 'Revisión: sequías y agua']);
        $pdf = $this->get('/reportes/'.$r['id'].'/descargar/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $xls = $this->get('/reportes/'.$r['id'].'/descargar/xlsx')->assertOk();
        $this->assertStringStartsWith('PK', $xls->getContent());
        $this->assertStringContainsString('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $xls->headers->get('Content-Type'));
        $html = $this->get('/reportes/'.$r['id'])->assertOk();
        $html->assertSee('&lt;f&gt;1+1&lt;/f&gt;', false)->assertDontSee('<f>1+1</f>', false);
        if (getenv('GEOSUR_EVIDENCIA_REPORTES')) {
            file_put_contents(base_path('../tmp/reporte64-prueba.pdf'), $pdf->getContent());
            file_put_contents(base_path('../tmp/reporte64-prueba.xlsx'), $xls->getContent());
        }
    }

    public function test_reporte_sin_lecturas_no_inventa_datos_y_se_puede_descargar(): void
    {
        $this->cliente();
        $r = $this->crear();
        $this->assertSame([], $r['contenido']['filas']);
        $this->get('/reportes/'.$r['id'])->assertOk()->assertSee('No hay lecturas');
        $this->get('/reportes/'.$r['id'].'/descargar/pdf')->assertOk();
    }

    public function test_pantallas_de_plantillas_y_formulario_mantienen_la_base_del_panel(): void
    {
        $this->actingAs(User::factory()->create(['rol' => 'analista']));
        $this->get('/reportes')->assertOk()->assertSee('Generar reporte')->assertSee('Mis plantillas')->assertDontSee('en preparación');
        $this->get('/plantillas')->assertOk()->assertSee('Crear plantilla')->assertSee('Columnas del reporte');
    }
}
