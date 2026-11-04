<?php

namespace App\Services\Mapas\Capas;

use App\Services\Api\ErrorConsulta;
use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

final class BuscarPoi
{
    public function ejecutar(string $texto, string $pais): array
    {
        $token = config('capas.poi_token');
        if (! config('capas.poi_habilitado') || ! is_string($token) || ! preg_match('/\Apk\.[A-Za-z0-9._-]+\z/D', $token)) {
            throw new ErrorConsulta('POI_NO_DISPONIBLE', 'La búsqueda de establecimientos no está disponible.', 503);
        }
        if (! array_key_exists($pais, config('capas.poi_paises')) || mb_strlen(trim($texto)) < 2 || mb_strlen($texto) > 120 || preg_match('/[;\x00-\x1f]|%3b/iu', $texto)) {
            throw new ErrorConsulta('POI_BUSQUEDA_INVALIDA', 'Revisa el nombre y el país de la búsqueda.', 422);
        }
        try {
            // Endpoint fijo, sin redirecciones ni resultados persistidos. No se usa Geocoding v6 para POI.
            $r = Http::acceptJson()->connectTimeout(3)->timeout(10)->withOptions(['allow_redirects' => false])
                ->get('https://api.mapbox.com/search/searchbox/v1/forward', [
                    'q' => trim($texto), 'access_token' => $token, 'language' => 'es', 'limit' => 6,
                    'types' => 'poi', 'country' => $pais,
                ]);
            if (! $r->successful() || strlen($r->body()) > 500000) {
                throw new UnexpectedValueException;
            }
            $json = $r->json();
            $features = $json['features'] ?? null;
            if (($json['type'] ?? null) !== 'FeatureCollection' || ! is_array($features) || ! array_is_list($features) || count($features) > 6) {
                throw new UnexpectedValueException;
            }
            $datos = [];
            foreach ($features as $f) {
                $p = $f['properties'] ?? [];
                $coordenadas = $f['geometry']['coordinates'] ?? null;
                $nombre = $p['name'] ?? null;
                $descripcion = $p['full_address'] ?? $p['place_formatted'] ?? $nombre;
                if (($f['type'] ?? null) !== 'Feature' || ($f['geometry']['type'] ?? null) !== 'Point' ||
                    ($p['feature_type'] ?? null) !== 'poi' || ! is_array($coordenadas) || ! array_is_list($coordenadas) || count($coordenadas) !== 2 ||
                    ! $this->coordenada($coordenadas[0], 180) || ! $this->coordenada($coordenadas[1], 90) ||
                    ! is_string($nombre) || ! trim($nombre) || mb_strlen($nombre) > 250 ||
                    ! is_string($descripcion) || mb_strlen($descripcion) > 1000 ||
                    strtolower($p['context']['country']['country_code'] ?? '') !== $pais) {
                    throw new UnexpectedValueException;
                }
                $datos[] = ['nombre' => $nombre, 'descripcion' => $descripcion, 'longitud' => $coordenadas[0], 'latitud' => $coordenadas[1]];
            }

            return ['fuente' => 'Mapbox Search Box', 'pais' => $pais, 'datos' => $datos];
        } catch (\Throwable) {
            throw new ErrorConsulta('POI_NO_DISPONIBLE', 'No se pudo buscar. Inténtalo de nuevo.', 503);
        }
    }

    private function coordenada(mixed $valor, float $maximo): bool
    {
        return (is_int($valor) || is_float($valor)) && is_finite((float) $valor) && abs($valor) <= $maximo;
    }
}
