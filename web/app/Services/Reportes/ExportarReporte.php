<?php

namespace App\Services\Reportes;

use App\Models\ReporteAmbiental;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\File;
use Shuchkin\SimpleXLSXGen;

final class ExportarReporte
{
    public function pdf(ReporteAmbiental $reporte): string
    {
        $opciones = new Options;
        $opciones->set('isRemoteEnabled', false);
        $opciones->set('isPhpEnabled', false);
        $opciones->set('isJavascriptEnabled', false);
        $opciones->set('defaultFont', 'DejaVu Sans');
        $opciones->set('chroot', public_path('img/marca'));
        $cache = storage_path('framework/cache/data/dompdf');
        File::ensureDirectoryExists($cache);
        $opciones->set('fontCache', $cache);
        $pdf = new Dompdf($opciones);
        $pdf->setPaper('A4', 'landscape');
        $graficos = [];
        if ($reporte->contenido['grafico']) {
            $motor = app(GraficoReporte::class);
            foreach ($motor->series($reporte->contenido) as $serie) {
                $svg = $motor->svg($serie);
                if ($svg !== '') {
                    $graficos[] = ['serie' => $serie, 'imagen' => 'data:image/svg+xml;base64,'.base64_encode($svg)];
                }
            }
        }
        $pdf->loadHtml(view('reportes.pdf', ['reporte' => $reporte, 'columnas' => PrepararReporte::COLUMNAS, 'graficos' => $graficos])->render(), 'UTF-8');
        $pdf->render();

        return $pdf->output();
    }

    public function excel(ReporteAmbiental $reporte): string
    {
        $claves = $reporte->contenido['columnas'];
        $filas = [array_map(fn ($c) => PrepararReporte::COLUMNAS[$c], $claves)];
        foreach ($reporte->contenido['filas'] as $fila) {
            $filas[] = array_map(fn ($c) => is_numeric($fila[$c]) && in_array($c, ['valor', 'latitud', 'longitud'], true) ? (float) $fila[$c] : "\0".PrepararReporte::celda($fila, $c), $claves);
        }
        $resumen = [["\0".$reporte->titulo], ["\0".'GeoSur · Reporte ambiental'], ["\0".$reporte->contenido['nota']], [],
            ['Zona', 'Variable', 'Origen', 'Fuente', 'Lecturas', 'Mínimo', 'Máximo', 'Promedio', 'Unidad']];
        foreach ($reporte->contenido['resumen'] as $r) {
            $resumen[] = ["\0".$r['punto'], "\0".$r['variable'], "\0".$r['origenNombre'], "\0".$r['fuenteNombre'], $r['cantidad'], $r['minimo'], $r['maximo'], $r['promedio'], "\0".$r['unidad']];
        }
        $resumen[] = [];
        if (isset($reporte->contenido['atribucion'])) {
            $resumen[] = ["\0".$reporte->contenido['atribucion']];
        }
        if (isset($reporte->parametros['corte'])) {
            $resumen[] = ["\0".'Corte de simulación global', "\0".json_encode($reporte->parametros['corte'], JSON_UNESCAPED_UNICODE)];
        }
        $excel = SimpleXLSXGen::fromArray($filas, 'Lecturas')->addSheet($resumen, 'Resumen')->setDefaultFont('Arial')->setDefaultFontSize(12);
        if (count($reporte->contenido['faltantes'] ?? [])) {
            $faltantes = [['Zona', 'Fecha · Bolivia', 'Variable', 'Unidad', 'Origen', 'Fuente', 'Estado']];
            foreach ($reporte->contenido['faltantes'] as $f) {
                $faltantes[] = array_map(fn ($v) => "\0".$v, [$f['punto'], $f['fecha'], $f['variable'], $f['unidad'], $f['origenNombre'], $f['fuenteNombre'], 'Sin dato']);
            }
            $excel->addSheet($faltantes, 'No disponibles');
        }

        return (string) $excel;
    }
}
