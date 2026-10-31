<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suscripciones_canal_avisos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $t->string('canal', 20);
            $t->boolean('activo')->default(false);
            $t->string('huella', 64);
            $t->timestamp('consentido_el')->nullable();
            $t->timestamps();
            $t->unique(['usuario_id', 'canal']);
        });
        Schema::create('envios_avisos', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('aviso_id')->constrained('avisos_ambientales')->cascadeOnDelete();
            $t->string('canal', 20);
            $t->string('huella', 64);
            $t->string('estado', 25)->default('pendiente');
            $t->unsignedSmallInteger('intentos')->default(0);
            $t->timestamp('disponible_el');
            $t->timestamp('iniciado_el')->nullable();
            $t->timestamp('terminado_el')->nullable();
            $t->string('error_codigo', 40)->nullable();
            $t->timestamps();
            $t->unique(['aviso_id', 'canal']);
            $t->index(['estado', 'disponible_el']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('envios_avisos');
        Schema::dropIfExists('suscripciones_canal_avisos');
    }
};
