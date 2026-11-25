<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_brigadistas', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::table('avisos_incendio', function (Blueprint $table): void {
            $table->foreignId('portal_brigadista_id')->nullable()->constrained('portal_brigadistas');
        });

        // Las asignaciones del login anterior vuelven a la cola si seguían abiertas.
        DB::table('avisos_incendio')->whereNotNull('brigadista_id')->whereNull('resuelto_en')
            ->update(['brigadista_id' => null, 'tomado_en' => null]);
    }

    public function down(): void
    {
        Schema::table('avisos_incendio', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('portal_brigadista_id');
        });

        Schema::dropIfExists('portal_brigadistas');
    }
};
