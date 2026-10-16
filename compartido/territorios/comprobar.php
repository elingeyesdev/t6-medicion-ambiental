<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}

try {
    $catalogo = require __DIR__.'/herramientas/cargar_catalogo.php';
    $manifiesto = json_decode(file_get_contents(__DIR__.'/manifiesto.json'), true, 512, JSON_THROW_ON_ERROR);
    foreach ($manifiesto['archivos'] as $ruta => $huella) {
        if (hash_file('sha256', __DIR__.'/'.$ruta) !== $huella) {
            throw new RuntimeException('El recurso cambió respecto del manifiesto: '.$ruta);
        }
    }

    $identificadores = [];
    $posiciones = 0;
    $comprobarCoordenadas = function (array $coordenadas) use (&$comprobarCoordenadas, &$posiciones): void {
        if (isset($coordenadas[0]) && is_numeric($coordenadas[0])) {
            if (count($coordenadas) !== 2 || ! is_numeric($coordenadas[1])
                || ! is_finite((float) $coordenadas[0]) || ! is_finite((float) $coordenadas[1])
                || $coordenadas[0] < -180 || $coordenadas[0] > 180 || $coordenadas[1] < -90 || $coordenadas[1] > 90) {
                throw new RuntimeException('Posición geográfica inválida.');
            }
            $posiciones++;

            return;
        }
        foreach ($coordenadas as $hijo) {
            if (! is_array($hijo)) {
                throw new RuntimeException('Estructura de coordenadas inválida.');
            }
            $comprobarCoordenadas($hijo);
        }
    };

    foreach (['departamentos', 'provincias', 'municipios'] as $nombre) {
        $capa = json_decode(file_get_contents(__DIR__.'/datos/limites/'.$nombre.'.geojson'), true, 512, JSON_THROW_ON_ERROR);
        if ($capa['type'] !== 'FeatureCollection' || $capa['versionCatalogo'] !== $catalogo->metadatos()['version']) {
            throw new RuntimeException('Versión o estructura GeoJSON inconsistente.');
        }
        foreach ($capa['features'] as $feature) {
            $id = $feature['id'];
            $territorio = $catalogo->buscar($id);
            if (isset($identificadores[$id]) || $feature['properties']['padre'] !== $territorio['padre']
                || $feature['properties']['tipo'] !== $territorio['tipo']
                || ! in_array($feature['geometry']['type'], ['Polygon', 'MultiPolygon'], true)) {
                throw new RuntimeException('Geometría sin correspondencia unívoca con su registro.');
            }
            $poligonos = $feature['geometry']['type'] === 'Polygon' ? [$feature['geometry']['coordinates']] : $feature['geometry']['coordinates'];
            foreach ($poligonos as $poligono) {
                foreach ($poligono as $anillo) {
                    if (count($anillo) < 4 || $anillo[0] !== $anillo[count($anillo) - 1]) {
                        throw new RuntimeException('Anillo sin cierre o insuficiente.');
                    }
                }
            }
            $comprobarCoordenadas($feature['geometry']['coordinates']);
            $identificadores[$id] = true;
        }
        unset($capa);
    }
    if (count($identificadores) !== 72) {
        throw new RuntimeException('No están representadas las 72 geometrías.');
    }

    $probar = require __DIR__.'/pruebas/reglas.php';
    $total = $probar($catalogo);
    echo "CORRECTO: 1 departamento, 15 provincias y 56 unidades de nivel municipal.\n";
    echo "CORRECTO: 72 geometrías, $posiciones posiciones longitud/latitud y huellas SHA256 verificadas.\n";
    echo "CORRECTO: $total comprobaciones de catálogo y filtros, incluidas las 56 relaciones municipales.\n";
    echo "Ejemplo válido: BO0701 + BO070101 = Santa Cruz de la Sierra.\n";
    echo "Ejemplo incompatible: BO0701 + BO070201 = FILTROS_INCOMPATIBLES.\n";
    echo "Estado: propuesta pendiente de revisión de Jaziel.\n";
    echo "La validez geométrica y cobertura completa se recalculan con herramientas/validar_geometrias.py.\n";
} catch (Throwable $error) {
    if (PHP_SAPI !== 'cli') {
        http_response_code(500);
    }
    echo 'ERROR: '.$error->getMessage()."\n";
    if (PHP_SAPI === 'cli') {
        exit(1);
    }
}
