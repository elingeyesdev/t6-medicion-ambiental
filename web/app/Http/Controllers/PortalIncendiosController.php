<?php

namespace App\Http\Controllers;

use App\Models\AvisoIncendio;
use App\Models\BrigadistaIncendios;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class PortalIncendiosController extends Controller
{
    public function index(Request $request, ?AvisoIncendio $aviso = null): Response
    {
        $historial = $request->query('vista') === 'historial';
        $cuentaBrigada = $this->cuentaBrigada();
        $brigadista = $cuentaBrigada !== null;
        $consulta = AvisoIncendio::query();
        if ($historial) {
            $consulta->where(fn ($q) => $q->whereNotNull('resuelto_en')
                ->orWhere(fn ($q) => $q->whereNull('tomado_en')->where('vence_en', '<=', now())));
        } else {
            $consulta->whereNull('resuelto_en')
                ->where(fn ($q) => $q->whereNotNull('tomado_en')->orWhere('vence_en', '>', now()));
        }
        $resumen = $historial ? [] : [
            'pendientes' => (clone $consulta)->whereNull('tomado_en')->count(),
            'en_verificacion' => (clone $consulta)->whereNotNull('tomado_en')->count(),
        ];
        $avisos = $consulta->orderByDesc('ultima_observacion_en')->orderBy('id')->paginate(30)->withQueryString();

        $mapaAvisos = ($aviso ? collect([$aviso]) : $avisos->getCollection())->map(fn (AvisoIncendio $item) => [
            'lat' => (float) $item->latitud,
            'lon' => (float) $item->longitud,
            'nombre' => $item->municipio,
            'estado' => $item->estado(),
            'url' => route('portal-incendios.ficha', ['aviso' => $item] + ($historial ? ['vista' => 'historial'] : [])),
        ])->values();

        return response()->view('portal-incendios.index', [
            'avisos' => $avisos, 'aviso' => $aviso, 'historial' => $historial, 'mapaAvisos' => $mapaAvisos,
            'brigadista' => $brigadista, 'cuentaBrigada' => $cuentaBrigada, 'resumen' => $resumen,
        ])->header('Cache-Control', 'no-store');
    }

    public function buscar(string $observacion): RedirectResponse
    {
        $id = DB::table('observaciones_aviso_incendio')->where('observacion_id', $observacion)->value('aviso_id');
        if ($id !== null) {
            return redirect()->route('portal-incendios.ficha', ['aviso' => $id]);
        }

        return redirect()->route('portal-incendios.index')->with('estado', 'Todavía no hay un aviso publicado para esta detección.');
    }

    public function tomar(AvisoIncendio $aviso): RedirectResponse
    {
        $cuenta = $this->cuentaBrigada();
        abort_if($cuenta === null, 403);
        $actualizados = AvisoIncendio::query()->whereKey($aviso->id)
            ->whereNull('tomado_en')->whereNull('resuelto_en')->where('vence_en', '>', now())
            ->update(['portal_brigadista_id' => $cuenta->id, 'tomado_en' => now()]);

        return redirect()->route('portal-incendios.ficha', $aviso)
            ->with('estado', $actualizados ? 'Aviso tomado para verificación.' : 'El aviso ya fue tomado o venció.');
    }

    public function resolver(Request $request, AvisoIncendio $aviso): RedirectResponse
    {
        $cuenta = $this->cuentaBrigada();
        abort_if($cuenta === null, 403);
        $datos = $request->validate(['resultado' => ['required', Rule::in(array_keys(AvisoIncendio::RESULTADOS))]]);
        $actualizados = AvisoIncendio::query()->whereKey($aviso->id)
            ->where('portal_brigadista_id', $cuenta->id)->whereNotNull('tomado_en')->whereNull('resuelto_en')
            ->update(['resultado' => $datos['resultado'], 'resuelto_en' => now()]);

        return redirect()->route('portal-incendios.ficha', ['aviso' => $aviso, 'vista' => 'historial'])
            ->with('estado', $actualizados ? 'Resultado de la verificación registrado.' : 'Esta cuenta no tiene un aviso pendiente asignado.');
    }

    private function cuentaBrigada(): ?BrigadistaIncendios
    {
        $cuenta = Auth::guard('brigada')->user();

        return $cuenta?->activo ? $cuenta : null;
    }
}
