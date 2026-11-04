<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnvioAviso extends Model
{
    use HasUlids;

    protected $table = 'envios_avisos';

    protected $guarded = [];

    protected $hidden = ['huella'];

    protected function casts(): array
    {
        return ['intentos' => 'integer', 'disponible_el' => 'immutable_datetime',
            'iniciado_el' => 'immutable_datetime', 'terminado_el' => 'immutable_datetime'];
    }

    public function aviso(): BelongsTo
    {
        return $this->belongsTo(AvisoAmbiental::class, 'aviso_id');
    }

    public function etiqueta(): string
    {
        return match ($this->estado) {
            'aceptado' => 'Aceptado por el proveedor',
            'registrado_local' => 'Registrado en correo local',
            'fallido' => 'No se pudo enviar',
            'cancelado' => 'Cancelado',
            'incierto' => 'Sin confirmación',
            default => 'Pendiente de envío',
        };
    }
}
