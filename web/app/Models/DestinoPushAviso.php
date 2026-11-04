<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DestinoPushAviso extends Model
{
    protected $table = 'destinos_push_avisos';

    protected $guarded = [];

    protected $hidden = ['token', 'huella'];

    protected function casts(): array
    {
        return ['token' => 'encrypted'];
    }
}
