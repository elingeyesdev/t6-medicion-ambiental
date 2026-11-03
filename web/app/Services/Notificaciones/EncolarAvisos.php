<?php

namespace App\Services\Notificaciones;

use App\Models\AvisoAmbiental;
use App\Models\EnvioAviso;
use App\Models\User;

final class EncolarAvisos
{
    public function __construct(private CanalesAvisos $canales) {}

    public function ejecutar(AvisoAmbiental $aviso, User $usuario): void
    {
        foreach (array_keys(CanalesAvisos::NOMBRES) as $canal) {
            if ($destino = $this->canales->autorizado($usuario, $canal)) {
                EnvioAviso::firstOrCreate(['aviso_id' => $aviso->id, 'canal' => $canal],
                    ['huella' => $this->canales->huella($destino), 'disponible_el' => now()]);
            }
        }
    }
}
