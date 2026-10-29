<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LecturaAmbiental extends Model
{
    protected $table = 'lecturas_ambientales';

    protected $guarded = ['id'];

    public function punto(): BelongsTo
    {
        return $this->belongsTo(PuntoAmbiental::class);
    }

    protected function casts(): array
    {
        return ['valor' => 'float', 'latitud' => 'float', 'longitud' => 'float', 'instante' => 'immutable_datetime', 'hora_utc' => 'immutable_datetime'];
    }
}
