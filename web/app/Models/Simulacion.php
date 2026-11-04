<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Simulacion extends Model
{
    use HasUlids;

    protected $table = 'simulaciones';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['configuracion' => 'array', 'resultado' => 'array', 'posicion' => 'integer', 'revision' => 'integer', 'reloj_ms' => 'integer', 'posicion_base' => 'integer'];
    }

    public function paraCliente(): array
    {
        return $this->only(['id', 'nombre', 'estado', 'posicion', 'revision', 'configuracion', 'resultado']);
    }
}
