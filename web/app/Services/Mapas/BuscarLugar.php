<?php

namespace App\Services\Mapas;

use App\Services\Api\ErrorConsulta;
use Illuminate\Support\Facades\Http;

final class BuscarLugar
{
    public function ejecutar(string $texto): array
    {
        $texto = trim($texto);
        if (mb_strlen($texto) < 2 || mb_strlen($texto) > 120 || preg_match('/[;\x00-\x1F]|%3b/iu', $texto) || count(preg_split('/[\s\p{P}]+/u', $texto, -1, PREG_SPLIT_NO_EMPTY)) > 20) {
            throw new ErrorConsulta('BUSQUEDA_INVALIDA', 'Escribe el nombre de un país, ciudad o lugar.');
        }
        $token = config('mapas.token_publico');
        if (! is_string($token) || ! preg_match('/^pk\.[A-Za-z0-9._-]+$/D', $token)) {
            throw new ErrorConsulta('BUSCADOR_NO_DISPONIBLE', 'El buscador no está disponible. Puedes elegir el lugar en el mapa.', 503);
        }
        try {
            // Resultados temporales: no se guardan ni se comparten en caché.
            $r = Http::acceptJson()->connectTimeout(3)->timeout(10)->withOptions(['allow_redirects' => false])
                ->get('https://api.mapbox.com/search/geocode/v6/forward', [
                    'q' => $texto, 'access_token' => $token, 'language' => 'es', 'limit' => 6, 'format' => 'v5', 'autocomplete' => 'false',
                ]);
            if (! $r->successful() || strlen($r->body()) > 500000) {
                throw new \UnexpectedValueException;
            }
            $respuesta = $r->json();
            $lugares = $respuesta['features'] ?? null;
            if (($respuesta['type'] ?? null) !== 'FeatureCollection' || ! is_array($lugares) || ! array_is_list($lugares) || count($lugares) > 6) {
                throw new \UnexpectedValueException;
            }
            $datos = [];
            foreach ($lugares as $lugar) {
                $centro = $lugar['center'] ?? null;
                if (! is_array($centro) || ! array_is_list($centro) || count($centro) !== 2 || ! $this->coordenada($centro[0], 180) || ! $this->coordenada($centro[1], 90)) {
                    throw new \UnexpectedValueException;
                }
                $nombre = $lugar['text'] ?? null;
                $descripcion = $lugar['place_name'] ?? $nombre;
                if (! is_string($nombre) || ! trim($nombre) || mb_strlen($nombre) > 250 || ! is_string($descripcion) || mb_strlen($descripcion) > 1000) {
                    throw new \UnexpectedValueException;
                }
                $limites = $lugar['bbox'] ?? null;
                if (! is_array($limites) || ! array_is_list($limites) || count($limites) !== 4 ||
                    ! $this->coordenada($limites[0], 180) || ! $this->coordenada($limites[1], 90) ||
                    ! $this->coordenada($limites[2], 180) || ! $this->coordenada($limites[3], 90) || $limites[0] >= $limites[2] || $limites[1] >= $limites[3]) {
                    $limites = null;
                }
                $datos[] = ['nombre' => $nombre, 'descripcion' => $descripcion, 'longitud' => $centro[0], 'latitud' => $centro[1], 'limites' => $limites];
            }

            return ['fuente' => 'Mapbox', 'datos' => $datos];
        } catch (\Throwable $e) {
            throw new ErrorConsulta('BUSCADOR_NO_DISPONIBLE', 'No se pudo buscar. Inténtalo de nuevo.', 503);
        }
    }

    private function coordenada(mixed $valor, float $maximo): bool
    {
        return (is_int($valor) || is_float($valor)) && is_finite((float) $valor) && abs($valor) <= $maximo;
    }
}
