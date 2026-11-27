<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $cuentas = DB::table('users')->where('rol', 'analista')->select('id');
            DB::table('personal_access_tokens')
                ->where('tokenable_type', (new User)->getMorphClass())
                ->whereIn('tokenable_id', $cuentas)
                ->delete();

            DB::table('users')->where('rol', 'analista')->update([
                'rol' => 'visualizador',
                'sesion_version' => DB::raw('sesion_version + 1'),
                'remember_token' => null,
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        // Los roles que hoy son válidos también lo eran antes de la migración.
    }
};
