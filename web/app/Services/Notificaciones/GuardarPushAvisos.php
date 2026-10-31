<?php

namespace App\Services\Notificaciones;

use App\Models\DestinoPushAviso;
use App\Models\EnvioAviso;
use App\Models\SuscripcionCanalAviso;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class GuardarPushAvisos
{
    public function __construct(private CanalesAvisos $canales) {}

    public function ejecutar(User $usuario, bool $activo, ?string $token = null): void
    {
        DB::transaction(function () use ($usuario, $activo, $token): void {
            $cuenta = User::whereKey($usuario->id)->lockForUpdate()->firstOrFail();
            abort_unless($cuenta->activo, 403);
            if ($activo && (! $this->canales->disponible('push') || ! $token)) {
                throw ValidationException::withMessages(['push' => 'Los avisos del navegador no están disponibles.']);
            }
            if ($activo) {
                $huella = $this->canales->huella($token);
                // Un navegador compartido deja de recibir los avisos de la cuenta anterior.
                $anterior = DestinoPushAviso::where('huella', $huella)->where('usuario_id', '!=', $cuenta->id)->first();
                if ($anterior) {
                    $this->desactivar($anterior->usuario_id);
                }
                $this->desactivar($cuenta->id);
                DestinoPushAviso::create(['usuario_id' => $cuenta->id, 'token' => $token, 'huella' => $huella]);
                SuscripcionCanalAviso::updateOrCreate(['usuario_id' => $cuenta->id, 'canal' => 'push'],
                    ['activo' => true, 'huella' => $huella, 'consentido_el' => now()]);
            } else {
                $this->desactivar($cuenta->id);
            }
        }, 3);
    }

    private function desactivar(int $usuarioId): void
    {
        DestinoPushAviso::where('usuario_id', $usuarioId)->delete();
        SuscripcionCanalAviso::where('usuario_id', $usuarioId)->where('canal', 'push')->update(['activo' => false, 'consentido_el' => null]);
        EnvioAviso::whereHas('aviso', fn ($q) => $q->where('usuario_id', $usuarioId))->where('canal', 'push')
            ->whereIn('estado', ['pendiente', 'reintento'])->update(['estado' => 'cancelado', 'terminado_el' => now(), 'error_codigo' => 'CONSENTIMIENTO_CAMBIADO']);
    }
}
