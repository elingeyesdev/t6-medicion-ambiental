<?php

namespace App\Services\Seguimiento;

use App\Services\FocosCalor\ConsultaFocos;
use Carbon\CarbonImmutable;

final class EvaluarZona
{
    public function __construct(private readonly ConsultaFocos $focos) {}

    public function consultar(object $zona, ?string $fecha): array
    {
        $ahora = CarbonImmutable::now('UTC')->startOfMinute();
        $hasta = $fecha === null ? $ahora : CarbonImmutable::parse($fecha, 'America/La_Paz')->addDay()->utc()->min($ahora);
        $desde = $hasta->subDays(7);
        $parametros = [
            'desde' => $desde->toIso8601ZuluString(),
            'hasta' => $hasta->toIso8601ZuluString(),
            'tamanioPagina' => 500,
        ];
        $total = 0;
        $altas = 0;
        $distanciaMinima = null;
        $consulta = $this->focos->ejecutar($parametros);
        $pagina = $consulta;
        do {
            foreach ($pagina['datos'] as $foco) {
                [$longitud, $latitud] = $foco['geometria']['coordinates'];
                $distancia = $this->distancia($zona, $latitud, $longitud);
                if ($distancia <= $zona->radio_km) {
                    $total++;
                    $altas += (int) ($foco['atributos']['confianza'] === 'alta');
                    $distanciaMinima = min($distanciaMinima ?? $distancia, $distancia);
                }
            }
            $siguiente = $pagina['paginacion']['siguiente'];
            if ($siguiente !== null) {
                $pagina = $this->focos->ejecutar($parametros + [
                    'pagina' => $siguiente,
                    'versionConjunto' => $consulta['paginacion']['versionConjunto'],
                ]);
            }
        } while ($siguiente !== null);

        $cobertura = $consulta['cobertura']['estado'];
        $prioridad = match (true) {
            $altas > 0 => 'prioritaria',
            $total > 0 => 'seguimiento',
            $cobertura === 'sin_cobertura' => 'sin_cobertura',
            default => 'sin_senal',
        };

        return [
            'prioridad' => $prioridad,
            'detecciones' => $cobertura === 'sin_cobertura' ? null : $total,
            'confianzaAlta' => $cobertura === 'sin_cobertura' ? null : $altas,
            'distanciaKm' => $distanciaMinima === null ? null : round($distanciaMinima, 2),
            'cobertura' => $cobertura,
            'desde' => $parametros['desde'],
            'hasta' => $parametros['hasta'],
            'fuente' => $consulta['fuentes'][0]['proveedor'],
            'producto' => $consulta['fuentes'][0]['producto'],
            'fechaRecuperacion' => $consulta['consulta']['obtencion']['fechaRecuperacion'],
        ];
    }

    private function distancia(object $zona, float $latitud, float $longitud): float
    {
        $origen = deg2rad((float) $zona->latitud);
        $destino = deg2rad($latitud);
        $deltaLongitud = deg2rad($longitud - (float) $zona->longitud);
        $seno = sin(($destino - $origen) / 2) ** 2 + cos($origen) * cos($destino) * sin($deltaLongitud / 2) ** 2;

        return 6371.0088 * 2 * asin(sqrt(min(1, $seno)));
    }
}
