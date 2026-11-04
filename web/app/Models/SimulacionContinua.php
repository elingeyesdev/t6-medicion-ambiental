<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SimulacionContinua extends Model
{
    protected $table = 'simulacion_continua';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['contenido' => 'array', 'revision' => 'integer', 'usuario_id' => 'integer'];
    }

    public function paraCliente(int $usuario): array
    {
        return ['estado' => $this->estado, 'revision' => $this->revision, 'propia' => $this->usuario_id === $usuario,
            'origen' => 'simulado'] + array_diff_key($this->contenido, array_flip(['reloj_ms']));
    }
}
