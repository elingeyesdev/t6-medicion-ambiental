<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PuntoAmbiental extends Model
{
    protected $attributes = ['activo' => true, 'tipo' => 'manual'];

    protected $table = 'puntos_ambientales';

    protected $fillable = ['codigo', 'nombre', 'pais', 'latitud', 'longitud', 'tipo'];

    protected function casts(): array
    {
        return ['latitud' => 'float', 'longitud' => 'float', 'activo' => 'boolean'];
    }

    public function lecturas(): HasMany
    {
        return $this->hasMany(LecturaAmbiental::class, 'punto_id');
    }
}
