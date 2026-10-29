<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlantillaReporte extends Model
{
    use HasUlids,SoftDeletes;

    protected $table = 'plantillas_reportes';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['usuario_id' => 'integer', 'configuracion' => 'array'];
    }
}
