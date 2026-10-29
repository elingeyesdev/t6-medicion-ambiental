<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simulaciones', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->string('nombre', 100);
            $table->json('configuracion');
            $table->json('resultado');
            $table->string('estado', 20)->default('lista');
            $table->unsignedInteger('posicion')->default(0);
            $table->unsignedInteger('revision')->default(0);
            $table->timestamps();
            $table->index(['usuario_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simulaciones');
    }
};
