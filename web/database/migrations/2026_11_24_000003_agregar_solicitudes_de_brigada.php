<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_brigadistas', function (Blueprint $table): void {
            $table->string('organizacion')->nullable();
            $table->text('telefono')->nullable();
            $table->text('carnet')->nullable();
            $table->string('huella_carnet', 64)->nullable()->unique();
            $table->timestamp('solicitado_en')->nullable();
            $table->timestamp('aprobado_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('portal_brigadistas', function (Blueprint $table): void {
            $table->dropUnique(['huella_carnet']);
            $table->dropColumn(['organizacion', 'telefono', 'carnet', 'huella_carnet', 'solicitado_en', 'aprobado_en']);
        });
    }
};
