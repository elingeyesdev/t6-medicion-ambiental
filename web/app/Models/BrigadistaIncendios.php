<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

final class BrigadistaIncendios extends Authenticatable
{
    protected $table = 'portal_brigadistas';

    protected $fillable = ['nombre', 'email', 'password', 'organizacion', 'telefono', 'carnet'];

    protected $hidden = ['password', 'telefono', 'carnet', 'huella_carnet'];

    protected $casts = ['password' => 'hashed', 'activo' => 'boolean', 'telefono' => 'encrypted',
        'carnet' => 'encrypted', 'solicitado_en' => 'immutable_datetime', 'aprobado_en' => 'immutable_datetime'];
}
