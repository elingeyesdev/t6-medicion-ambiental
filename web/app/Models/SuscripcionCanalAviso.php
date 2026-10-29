<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuscripcionCanalAviso extends Model
{
    protected $table = 'suscripciones_canal_avisos';

    protected $guarded = [];

    protected $hidden = ['huella'];

    protected function casts(): array
    {
        return ['activo' => 'boolean', 'consentido_el' => 'immutable_datetime'];
    }
}
