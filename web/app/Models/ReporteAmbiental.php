<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class ReporteAmbiental extends Model
{
    use HasUlids;

    protected $table = 'reportes_ambientales';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['usuario_id' => 'integer', 'parametros' => 'array', 'contenido' => 'array'];
    }

    public function paraCliente(): array
    {
        return $this->only(['id', 'titulo', 'parametros', 'contenido', 'created_at']);
    }
}
