<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('rol', 25)->default('visualizador')->index();
            $table->boolean('activo')->default(true);
            $table->unsignedInteger('sesion_version')->default(1);
        });
        Schema::create('preferencias_cuenta', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('latitud', 9, 6)->nullable();
            $table->decimal('longitud', 9, 6)->nullable();
            $table->boolean('notif_email')->default(false);
            $table->boolean('notif_whatsapp')->default(false);
            $table->string('whatsapp_destino', 50)->nullable();
            $table->boolean('notif_telegram')->default(false);
            $table->string('telegram_destino', 50)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preferencias_cuenta');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['rol', 'activo', 'sesion_version']));
    }
};
