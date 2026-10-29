<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('preferencias_cuenta', fn (Blueprint $tabla) => $tabla->json('unidades')->nullable());
    }

    public function down(): void
    {
        Schema::table('preferencias_cuenta', fn (Blueprint $tabla) => $tabla->dropColumn('unidades'));
    }
};
