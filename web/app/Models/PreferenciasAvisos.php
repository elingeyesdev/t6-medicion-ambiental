<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PreferenciasAvisos extends Model
{
    protected $table = 'preferencias_avisos';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['activos' => 'boolean', 'simulados' => 'boolean', 'metricas' => 'array'];
    }
}
