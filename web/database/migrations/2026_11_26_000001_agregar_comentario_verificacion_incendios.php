<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('avisos_incendio', function (Blueprint $tabla): void {
            $tabla->text('comentario_verificacion')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('avisos_incendio', function (Blueprint $tabla): void {
            $tabla->dropColumn('comentario_verificacion');
        });
    }
};
