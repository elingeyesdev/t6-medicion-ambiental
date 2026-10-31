<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('destinos_push_avisos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('usuario_id')->unique()->constrained('users')->cascadeOnDelete();
            $t->text('token');
            $t->string('huella', 64)->unique();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('destinos_push_avisos');
    }
};
