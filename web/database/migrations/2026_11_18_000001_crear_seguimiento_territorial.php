<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zonas_seguimiento', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('nombre', 100);
            $table->decimal('latitud', 8, 5);
            $table->decimal('longitud', 8, 5);
            $table->unsignedSmallInteger('radio_km');
            $table->json('evaluacion')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'id']);
        });

        Schema::create('tareas_seguimiento', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('zona_id')->constrained('zonas_seguimiento')->cascadeOnDelete();
            $table->string('titulo', 140);
            $table->json('evaluacion');
            $table->string('resultado_tipo', 24)->nullable();
            $table->text('resultado_nota')->nullable();
            $table->timestamp('completada_en')->nullable();
            $table->timestamps();
            $table->index(['zona_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tareas_seguimiento');
        Schema::dropIfExists('zonas_seguimiento');
    }
};
