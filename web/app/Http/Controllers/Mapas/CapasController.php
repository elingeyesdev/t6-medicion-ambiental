<?php

namespace App\Http\Controllers\Mapas;

use App\Services\Api\ErrorConsulta;
use App\Services\Mapas\Capas\VariablesRaster;
use App\Services\Mapas\Radar\CatalogoRaster;

final class CapasController
{
    public function index(CatalogoRaster $catalogo)
    {
        try {
            $publicacion = $catalogo->cargar();
            $capas = [];
            foreach (VariablesRaster::todas() as $id => $variable) {
                $cuadros = [];
                foreach ($publicacion['cuadros'] as $c) {
                    if ($c['capa'] !== $id) {
                        continue;
                    }
                    $cuadros[] = array_intersect_key($c, array_flip([
                        'id', 'instante', 'ancho', 'alto', 'codificacion', 'fuente', 'producto', 'rejilla',
                    ])) + ['url' => route('mapa.capas.imagen', ['cuadro' => $c['id']], absolute: false), 'origen' => 'estimado'];
                }
                $capas[] = ['id' => $id, 'nombre' => $variable['nombre'], 'unidad' => $variable['unidad'], 'cuadros' => $cuadros];
            }

            return response()->json([
                'version' => 1, 'estado' => $publicacion['cuadros'] ? 'disponible' : 'sin_insumos',
                'revision' => hash('sha256', json_encode([$capas, $publicacion['faltantes']], JSON_THROW_ON_ERROR)),
                'capas' => $capas, 'faltantes' => $publicacion['faltantes'],
                'poi' => ['habilitado' => (bool) config('capas.poi_habilitado'), 'paises' => config('capas.poi_paises')],
            ])->header('Cache-Control', 'no-store, private');
        } catch (ErrorConsulta $e) {
            return $this->error($e);
        }
    }

    public function imagen(string $cuadro, CatalogoRaster $catalogo)
    {
        try {
            return response($catalogo->imagen($cuadro), 200, [
                'Content-Type' => 'image/png', 'Cache-Control' => 'no-store, private',
                'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'",
            ]);
        } catch (ErrorConsulta $e) {
            return $this->error($e);
        }
    }

    private function error(ErrorConsulta $e)
    {
        return response()->json(['error' => ['codigo' => $e->codigo, 'mensaje' => $e->getMessage()]], $e->estado)
            ->header('Cache-Control', 'no-store, private');
    }
}
