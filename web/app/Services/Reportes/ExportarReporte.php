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
        $pdf->loadHtml(view('reportes.pdf', ['reporte' => $reporte, 'columnas' => PrepararReporte::COLUMNAS])->render(), 'UTF-8');
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

        return (string) SimpleXLSXGen::fromArray($filas, 'Lecturas')->addSheet($resumen, 'Resumen')->setDefaultFont('Arial')->setDefaultFontSize(12);
    }
}
