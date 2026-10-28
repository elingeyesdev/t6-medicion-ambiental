<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('puntos_ambientales', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 60)->unique();
            $table->string('nombre', 100);
            $table->string('pais', 2)->index();
            $table->decimal('latitud', 9, 6);
            $table->decimal('longitud', 9, 6);
            $table->string('tipo', 20)->default('manual');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
        Schema::create('lecturas_ambientales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('punto_id')->constrained('puntos_ambientales')->restrictOnDelete();
            $table->string('metrica', 25);
            $table->decimal('valor', 14, 4);
            $table->dateTime('instante');
            $table->dateTime('hora_utc');
            $table->string('origen', 15);
            $table->string('fuente', 80);
            $table->decimal('latitud', 9, 6);
            $table->decimal('longitud', 9, 6);
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['punto_id', 'metrica', 'instante', 'origen', 'fuente'], 'lectura_identidad');
            $table->index(['punto_id', 'instante'], 'lectura_periodo');
            $table->index(['punto_id', 'hora_utc', 'metrica'], 'lectura_horas');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lecturas_ambientales');
        Schema::dropIfExists('puntos_ambientales');
    }
};
