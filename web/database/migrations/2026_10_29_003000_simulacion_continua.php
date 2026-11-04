<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simulacion_continua', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('revision')->default(0);
            $table->string('estado', 20);
            $table->json('contenido');
            $table->timestamps();
        });
        Schema::table('simulaciones', function (Blueprint $table): void {
            $table->unsignedBigInteger('reloj_ms')->nullable();
            $table->unsignedInteger('posicion_base')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('simulaciones', function (Blueprint $table): void {
            $table->dropColumn(['reloj_ms', 'posicion_base']);
        });
        Schema::dropIfExists('simulacion_continua');
    }
};
