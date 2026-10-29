<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvisoAmbiental extends Model
{
    use HasUlids;

    protected $table = 'avisos_ambientales';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['leido_el' => 'immutable_datetime', 'usuario_id' => 'integer'];
    }

    public function alerta(): BelongsTo
    {
        return $this->belongsTo(AlertaAmbiental::class);
    }
}
