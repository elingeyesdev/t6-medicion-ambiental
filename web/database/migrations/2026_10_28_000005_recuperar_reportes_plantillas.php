<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plantillas_reportes', function (Blueprint $t): void {
            $t->ulid('id')->primary();
            $t->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $t->string('nombre', 100);
            $t->json('configuracion');
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('reportes_ambientales', function (Blueprint $t): void {
            $t->ulid('id')->primary();
            $t->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $t->string('titulo', 100);
            $t->json('parametros');
            $t->json('contenido');
            $t->timestamps();
            $t->index(['usuario_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reportes_ambientales');
        Schema::dropIfExists('plantillas_reportes');
    }
};
