<?php

namespace App\Services\Notificaciones;

use App\Models\EnvioAviso;
use App\Models\PreferenciasAvisos;
use App\Models\User;
use App\Services\Alertas\PrepararAvisos;
use Illuminate\Support\Facades\DB;

final class ProcesarOutboxAvisos
{
    public function __construct(private CanalesAvisos $canales, private TransportarAviso $transporte) {}

    public function ejecutar(int $limite = 50): int
    {
        if (! config('avisos.procesar')) {
            return 0;
        }
        // Un proceso interrumpido no se vuelve a ejecutar: pudo haber alcanzado al proveedor.
        EnvioAviso::where('estado', 'en_proceso')->where('iniciado_el', '<', now()->subMinutes(2))
            ->update(['estado' => 'incierto', 'error_codigo' => 'PROCESO_INTERRUMPIDO', 'terminado_el' => now()]);
        $ids = EnvioAviso::whereIn('estado', ['pendiente', 'reintento'])->where('disponible_el', '<=', now())
            ->orderBy('disponible_el')->orderBy('id')->limit(max(1, min(100, $limite)))->pluck('id');
        $procesados = 0;
        foreach ($ids as $id) {
            // Compare-and-set: funciona también en SQLite y no retiene locks durante la red.
            $reclamado = EnvioAviso::whereKey($id)->whereIn('estado', ['pendiente', 'reintento'])->where('disponible_el', '<=', now())
                ->update(['estado' => 'en_proceso', 'iniciado_el' => now(), 'intentos' => DB::raw('intentos + 1')]);
            if (! $reclamado) {
                continue;
            }
            $envio = EnvioAviso::with('aviso.alerta')->findOrFail($id);
            $destino = $this->destinoVigente($envio);
            $resultado = $destino ? $this->transporte->ejecutar($envio, $destino) : ['estado' => 'cancelado', 'error_codigo' => 'PREFERENCIAS_O_VIGENCIA'];
            if ($resultado['estado'] === 'reintento' && $envio->intentos >= 3) {
                $resultado = ['estado' => 'fallido', 'error_codigo' => 'LIMITE_INTENTOS'];
            }
            $espera = max(60 * $envio->intentos, $resultado['espera'] ?? 60);
            unset($resultado['espera']);
            $envio->update($resultado + ['disponible_el' => now()->addSeconds($espera),
                'terminado_el' => $resultado['estado'] === 'reintento' ? null : now()]);
            $procesados++;
        }

        return $procesados;
    }

    private function destinoVigente(EnvioAviso $envio): ?string
    {
        $aviso = $envio->aviso;
        $a = $aviso?->alerta;
        $usuario = $aviso ? User::find($aviso->usuario_id) : null;
        $pref = $usuario ? PreferenciasAvisos::where('usuario_id', $usuario->id)->first() : null;
        if (! $a || ! $usuario || ! $pref?->activos || $a->instante->lt(now()->subMinutes(15)) ||
            ! in_array($a->metrica, $pref->metricas, true) || ($a->origen === 'simulado' && ! $pref->simulados)) {
            return null;
        }
        $ubicacion = $usuario->preferencias;
        if ($ubicacion?->latitud !== null && $ubicacion?->longitud !== null &&
            PrepararAvisos::distancia($ubicacion->latitud, $ubicacion->longitud, $a->contenido['latitud'], $a->contenido['longitud']) > 50) {
            return null;
        }
        $destino = $this->canales->autorizado($usuario, $envio->canal);

        return $destino && hash_equals($envio->huella, $this->canales->huella($destino)) ? $destino : null;
    }
}
