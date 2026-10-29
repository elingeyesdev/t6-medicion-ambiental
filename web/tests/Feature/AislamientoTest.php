<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AislamientoTest extends TestCase
{
    public function test_la_base_de_pruebas_admite_escritura_sin_tocar_la_demo(): void
    {
        $this->assertSame('testing', app()->environment());
        if (config('database.default') === 'pgsql') {
            $this->assertSame('1', getenv('GEOSUR_PRUEBA_POSTGRES'));
            $this->assertSame('127.0.0.1', config('database.connections.pgsql.host'));
            $this->assertSame('geosur_m3_tests', DB::connection()->getDatabaseName());
            $this->assertEmpty(config('database.connections.pgsql.url'));
        } else {
            $this->assertSame('sqlite', config('database.default'));
            $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        }

        Schema::create('verificacion_base', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
        });

        DB::table('verificacion_base')->insert(['nombre' => 'Prueba aislada']);
        $this->assertDatabaseHas('verificacion_base', ['nombre' => 'Prueba aislada']);
        Schema::drop('verificacion_base');

        Http::assertNothingSent();
    }

    public function test_las_pruebas_bloquean_solicitudes_externas_no_simuladas(): void
    {
        $this->expectException(StrayRequestException::class);

        Http::get('https://example.invalid/solicitud-bloqueada');
    }
}
