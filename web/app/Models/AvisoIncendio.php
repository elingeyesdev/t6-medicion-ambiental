<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class AvisoIncendio extends Model
{
    use HasUuids;

    public const RESULTADOS = [
        'incendio' => 'Incendio confirmado',
        'sin_incendio' => 'Sin incendio',
        'sin_verificacion' => 'No se pudo verificar',
    ];

    protected $table = 'avisos_incendio';

    protected $guarded = ['id'];

    protected $hidden = ['brigadista_id', 'portal_brigadista_id'];

    public $timestamps = false;

    protected $casts = [
        'observado_en' => 'immutable_datetime',
        'ultima_observacion_en' => 'immutable_datetime',
        'publicado_en' => 'immutable_datetime',
        'vence_en' => 'immutable_datetime',
        'tomado_en' => 'immutable_datetime',
        'resuelto_en' => 'immutable_datetime',
    ];

    public function estado(): string
    {
        return match (true) {
            $this->resuelto_en !== null => 'Resuelto',
            $this->tomado_en !== null => 'En verificación',
            $this->vence_en->lte(now()) => 'Vencido sin atención',
            default => 'Por verificar',
        };
    }
}
