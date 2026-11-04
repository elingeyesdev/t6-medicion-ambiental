<?php

namespace App\Services\Cuentas;

use App\Models\EnvioAviso;
use App\Models\SuscripcionCanalAviso;
use App\Models\User;
use App\Services\Notificaciones\CanalesAvisos;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class GuardarCanalesAvisos
{
    public function __construct(private CanalesAvisos $canales, private GuardarPreferencias $preferencias) {}

    public function ejecutar(User $usuario, array $datos): void
    {
        DB::transaction(function () use ($usuario, $datos): void {
            $cuenta = User::whereKey($usuario->id)->lockForUpdate()->firstOrFail();
            abort_unless($cuenta->activo, 403);
            $habilita = collect(CanalesAvisos::CUENTA)->contains(fn ($c) => (bool) $datos['notif_'.$c]);
            if ($habilita && ! in_array($datos['consentimiento'] ?? null, [true, 1, '1', 'yes', 'on', 'true'], true)) {
                throw ValidationException::withMessages(['consentimiento' => 'Confirma que deseas recibir los avisos.']);
            }
            if ($habilita && ($cuenta->preferencias?->latitud === null || $cuenta->preferencias?->longitud === null)) {
                throw ValidationException::withMessages(['canales' => 'Guarda tu ubicación antes de activar estos avisos.']);
            }
            foreach (CanalesAvisos::CUENTA as $canal) {
                if ($datos['notif_'.$canal] && ! $this->canales->disponible($canal)) {
                    throw ValidationException::withMessages(['canales' => 'Ese canal no está disponible. Elige otro.']);
                }
            }
            $this->preferencias->ejecutar($cuenta, array_intersect_key($datos, array_flip([
                'notif_email', 'notif_telegram', 'notif_whatsapp', 'telegram_destino', 'whatsapp_destino',
            ])));
            foreach (CanalesAvisos::CUENTA as $canal) {
                $activo = (bool) $datos['notif_'.$canal];
                $destino = $this->canales->destino($cuenta, $canal);
                if ($activo && ! $destino) {
                    throw ValidationException::withMessages([$canal.'_destino' => 'Revisa el destino de tus avisos.']);
                }
                $huella = $this->canales->huella($destino ?? '');
                SuscripcionCanalAviso::updateOrCreate(['usuario_id' => $cuenta->id, 'canal' => $canal],
                    ['activo' => $activo, 'huella' => $huella, 'consentido_el' => $activo ? now() : null]);
                EnvioAviso::whereHas('aviso', fn ($q) => $q->where('usuario_id', $cuenta->id))->where('canal', $canal)
                    ->whereIn('estado', ['pendiente', 'reintento'])
                    ->when($activo, fn ($q) => $q->where('huella', '!=', $huella))
                    ->update(['estado' => 'cancelado', 'error_codigo' => 'CONSENTIMIENTO_CAMBIADO', 'terminado_el' => now()]);
            }
        }, 3);
        $usuario->unsetRelation('preferencias');
    }
}
