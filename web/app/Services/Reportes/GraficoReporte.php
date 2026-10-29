<?php

namespace App\Services\Reportes;

/** SVG local con puntos reales del snapshot. Los huecos conservados interrumpen la línea. */
final class GraficoReporte
{
    public function series(array $contenido): array
    {
        return collect([...$contenido['filas'], ...($contenido['faltantes'] ?? [])])
            ->groupBy(fn ($f) => json_encode([$f['puntoCodigo'], $f['metrica'], $f['origen'], $f['fuente']]))
            ->map(fn ($g) => $g->sortBy('instante')->values()->all())->values()->all();
    }

    public function svg(array $filas): string
    {
        $validos = array_filter(array_column($filas, 'valor'), fn ($v) => $v !== null);
        if ($validos === []) {
            return '';
        }
        $min = min($validos);
        $max = max($validos);
        $desde = strtotime($filas[0]['instante']);
        $hasta = strtotime($filas[array_key_last($filas)]['instante']);
        $trazos = '';
        $camino = '';
        foreach ($filas as $f) {
            if ($f['valor'] === null) {
                $camino .= ' ';

                continue;
            }
            $x = round(35 + (strtotime($f['instante']) - $desde) / max(1, $hasta - $desde) * 690, 2);
            $y = round(175 - ($f['valor'] - $min) / max(1, $max - $min) * 145, 2);
            $comando = $camino === '' || str_ends_with($camino, ' ') ? 'M' : 'L';
            $camino .= $comando.$x.','.$y;
            $trazos .= '<circle cx="'.$x.'" cy="'.$y.'" r="3" fill="#168042"/>';
        }
        $escala = htmlspecialchars($min.' — '.$max.' '.$filas[0]['unidad'], ENT_QUOTES | ENT_XML1, 'UTF-8');

        return '<svg xmlns="http://www.w3.org/2000/svg" width="760" height="220" viewBox="0 0 760 220"><rect width="760" height="220" fill="white"/><path d="M35,20V185H735" fill="none" stroke="#667766"/><path d="'.trim($camino).'" fill="none" stroke="#168042" stroke-width="2"/>'.$trazos.'<text x="35" y="210" font-size="12">'.$escala.'</text></svg>';
    }
}
