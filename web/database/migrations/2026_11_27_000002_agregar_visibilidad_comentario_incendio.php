<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('avisos_incendio', function (Blueprint $table): void {
            $table->boolean('comentario_publico')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('avisos_incendio', function (Blueprint $table): void {
            $table->dropColumn('comentario_publico');
        });
    }
};
