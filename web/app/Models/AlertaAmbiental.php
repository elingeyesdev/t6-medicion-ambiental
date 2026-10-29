<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class AlertaAmbiental extends Model
{
    use HasUlids;

    protected $table = 'alertas_ambientales';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['contenido' => 'array', 'instante' => 'immutable_datetime', 'reconocida_el' => 'immutable_datetime', 'reconocida_por' => 'integer'];
    }

    public function paraCliente(): array
    {
        return $this->only(['id', 'contenido', 'metrica', 'origen', 'severidad', 'instante', 'reconocida_el']) + ['reconocida' => $this->reconocida_el !== null];
    }
}
