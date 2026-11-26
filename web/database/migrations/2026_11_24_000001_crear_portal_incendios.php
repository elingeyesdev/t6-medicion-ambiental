<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avisos_incendio', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('municipio');
            $table->decimal('latitud', 9, 5);
            $table->decimal('longitud', 9, 5);
            $table->dateTime('observado_en');
            $table->dateTime('ultima_observacion_en');
            $table->dateTime('publicado_en');
            $table->dateTime('vence_en')->index();
            $table->string('producto');
            $table->unsignedInteger('detecciones')->default(0);
            $table->foreignId('brigadista_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('tomado_en')->nullable();
            $table->dateTime('resuelto_en')->nullable();
            $table->string('resultado')->nullable();
            $table->index(['municipio', 'ultima_observacion_en']);
        });

        Schema::create('observaciones_aviso_incendio', function (Blueprint $table): void {
            $table->string('observacion_id', 96)->primary();
            $table->foreignUuid('aviso_id')->constrained('avisos_incendio')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('observaciones_aviso_incendio');
        Schema::dropIfExists('avisos_incendio');
    }
};
