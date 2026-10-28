<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LecturaAmbiental extends Model
{
    protected $table = 'lecturas_ambientales';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['valor' => 'float', 'latitud' => 'float', 'longitud' => 'float', 'instante' => 'immutable_datetime', 'hora_utc' => 'immutable_datetime'];
    }
}
