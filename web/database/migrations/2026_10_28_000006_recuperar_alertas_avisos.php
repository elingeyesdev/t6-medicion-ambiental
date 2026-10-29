<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estados_alertas', function (Blueprint $t) {
            $t->string('clave', 64)->primary();
            $t->foreignId('lectura_id')->constrained('lecturas_ambientales')->restrictOnDelete();
            $t->timestamp('instante');
            $t->unsignedSmallInteger('nivel');
            $t->timestamps();
        });
        Schema::create('alertas_ambientales', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignId('lectura_id')->unique()->constrained('lecturas_ambientales')->restrictOnDelete();
            $t->json('contenido');
            $t->string('metrica', 30)->index();
            $t->string('origen', 15)->index();
            $t->string('severidad', 20)->index();
            $t->timestamp('instante')->index();
            $t->foreignId('reconocida_por')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('reconocida_el')->nullable();
            $t->timestamps();
        });
        Schema::create('preferencias_avisos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('usuario_id')->unique()->constrained('users')->cascadeOnDelete();
            $t->boolean('activos')->default(false);
            $t->boolean('simulados')->default(false);
            $t->json('metricas');
            $t->timestamps();
        });
        Schema::create('avisos_ambientales', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $t->foreignUlid('alerta_id')->constrained('alertas_ambientales')->restrictOnDelete();
            $t->timestamp('leido_el')->nullable();
            $t->timestamps();
            $t->unique(['usuario_id', 'alerta_id']);
            $t->index(['usuario_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['avisos_ambientales', 'preferencias_avisos', 'alertas_ambientales', 'estados_alertas'] as $tabla) {
            Schema::dropIfExists($tabla);
        }
    }
};
