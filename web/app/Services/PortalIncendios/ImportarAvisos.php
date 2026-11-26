<?php

namespace App\Services\PortalIncendios;

use App\Models\AvisoIncendio;
use App\Services\FocosCalor\ConsultaFocos;
use App\Services\Territorios\CatalogoTerritorial;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class ImportarAvisos
{
    public function __construct(private ConsultaFocos $focos, private CatalogoTerritorial $territorios) {}

    public function ejecutar(): int
    {
        return Cache::lock('portal-incendios:importar', 240)->get(function (): int {
            $hasta = now()->utc();
            $filtros = [
                'desde' => $hasta->copy()->subHours(24)->toIso8601ZuluString(),
                'hasta' => $hasta->toIso8601ZuluString(),
                'confianza' => 'alta',
                'tamanioPagina' => '500',
            ];
            $datos = [];
            do {
                $respuesta = $this->focos->ejecutar($filtros);
                foreach ($respuesta['datos'] as $foco) {
                    $datos[] = $foco;
                }
                $filtros['pagina'] = $respuesta['paginacion']['siguiente'];
                $filtros['versionConjunto'] = $respuesta['paginacion']['versionConjunto'];
            } while ($filtros['pagina'] !== null);

            $productos = array_column($respuesta['fuentes'], 'producto', 'identificador');
            usort($datos, static fn (array $a, array $b) => strcmp($a['fechas']['observadoDesde'], $b['fechas']['observadoDesde']));

            return DB::transaction(function () use ($datos, $productos): int {
                $publicados = 0;
                foreach ($datos as $foco) {
                    if ($this->guardar($foco, $productos)) {
                        $publicados++;
                    }
                }

                return $publicados;
            });
        }) ?: 0;
    }

    private function guardar(array $foco, array $productos): bool
    {
        $producto = $productos[$foco['fuentesRef'][0] ?? ''] ?? null;
        if ($producto === null || DB::table('observaciones_aviso_incendio')->where('observacion_id', $foco['identificador'])->exists()) {
            return false;
        }

        [$lon, $lat] = $foco['geometria']['coordinates'];
        $fecha = Carbon::parse($foco['fechas']['observadoDesde'])->utc();
        $codigo = $foco['territorio']['municipio'] ?? null;
        $municipio = $codigo ? $this->territorios->buscar($codigo)['nombre'] : 'Santa Cruz · municipio sin asignar';
        // Tres kilómetros y seis horas desde la detección que abrió el aviso.
        $aviso = AvisoIncendio::query()
            ->where('municipio', $municipio)
            ->whereNull('resuelto_en')
            ->where(fn ($q) => $q->whereNotNull('tomado_en')->orWhere('vence_en', '>', now()))
            ->whereBetween('observado_en', [$fecha->copy()->subHours(6), $fecha->copy()->addHours(6)])
            ->whereBetween('latitud', [$lat - 0.029, $lat + 0.029])
            ->whereBetween('longitud', [$lon - 0.034, $lon + 0.034])
            ->orderBy('observado_en')->orderBy('id')
            ->get()->first(fn (AvisoIncendio $candidato) => $this->distancia($lat, $lon, $candidato) <= 3);
        $nuevo = $aviso === null;
        $aviso ??= AvisoIncendio::create([
            'municipio' => $municipio, 'latitud' => $lat, 'longitud' => $lon,
            'observado_en' => $fecha, 'ultima_observacion_en' => $fecha,
            'publicado_en' => now(), 'vence_en' => now()->addHours(72), 'producto' => $producto,
        ]);
        DB::table('observaciones_aviso_incendio')->insert(['observacion_id' => $foco['identificador'], 'aviso_id' => $aviso->id]);
        $aviso->increment('detecciones', 1, ['ultima_observacion_en' => max($fecha, $aviso->ultima_observacion_en)]);

        return $nuevo;
    }

    private function distancia(float $lat, float $lon, AvisoIncendio $aviso): float
    {
        $dLat = deg2rad($lat - (float) $aviso->latitud);
        $dLon = deg2rad($lon - (float) $aviso->longitud);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat)) * cos(deg2rad((float) $aviso->latitud)) * sin($dLon / 2) ** 2;

        return 6371 * 2 * asin(min(1, sqrt($a)));
    }
}
