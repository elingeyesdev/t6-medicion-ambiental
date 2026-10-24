<?php

declare(strict_types=1);

namespace App\Services\Sequias\Experimental;

use App\Services\Sequias\LectorJsonLocal;
use App\Services\Sequias\SeriePrecipitacionInvalida;
use App\Services\Territorios\CatalogoTerritorial;
use App\Services\Territorios\FiltroTerritorialInvalido;
use App\Services\Territorios\GeometriaTerritorial;

/** Registro candidato de puntos de solicitud; no contiene lluvia, celdas ERA5 ni SPI. */
final class CatalogoMuestrasSequias
{
    public const VERSION = 'scz-muestras-sequias-0.1-propuesta';

    private ?array $documento = null;

    private array $porId = [];

    public function __construct(
        private readonly string $archivo,
        private readonly string $directorioTerritorial,
        private readonly LectorJsonLocal $lector,
        private readonly CatalogoTerritorial $territorios,
        private readonly GeometriaTerritorial $geografia,
    ) {}

    public function consultar(?string $provincia = null, ?string $municipio = null, ?string $muestra = null): array
    {
        try {
            $seleccion = $this->territorios->resolverFiltros($provincia, $municipio);
        } catch (FiltroTerritorialInvalido $e) {
            throw new SeriePrecipitacionInvalida($e->codigo, $e->getMessage());
        }
        $this->cargar();
        $p = $seleccion['provincia']['identificador'] ?? null;
        $m = $seleccion['municipio']['identificador'] ?? null;
        $enTerritorio = array_values(array_filter($this->porId, fn (array $s) => ($p === null || $s['provincia'] === $p) && ($m === null || $s['municipio'] === $m)));
        $datos = $enTerritorio;
        if ($muestra !== null) {
            if (! isset($this->porId[$muestra])) {
                throw new SeriePrecipitacionInvalida('MUESTRA_NO_DISPONIBLE', 'La muestra no pertenece al catálogo candidato.');
            }
            $datos = array_values(array_filter($enTerritorio, fn (array $s) => $s['identificador'] === $muestra));
            if ($datos === []) {
                throw new SeriePrecipitacionInvalida('FILTROS_INCOMPATIBLES', 'La muestra no pertenece al territorio indicado.');
            }
        }
        $municipios = $m === null ? $this->territorios->listar('municipio', $p) : [$seleccion['municipio']];
        foreach ($datos as &$s) {
            $s['nombreMunicipio'] = $this->territorios->buscar($s['municipio'])['nombre'];
            $s['nombreProvincia'] = $this->territorios->buscar($s['provincia'])['nombre'];
            $s['estadoClimatico'] = 'sin_consulta_en_esta_operacion';
            $s['coordenadaCelda'] = null;
            $s['spi'] = null;
        }
        unset($s);

        return ['estado' => 'catalogo_candidato_sin_consulta_climatica',
            'catalogo' => array_diff_key($this->documento, ['muestras' => true]),
            'filtrosSolicitados' => compact('provincia', 'municipio', 'muestra'),
            'filtrosEfectivos' => ['provincia' => $p, 'municipio' => $m, 'muestra' => $muestra],
            'resumen' => ['municipiosEnTerritorio' => count($municipios), 'muestrasEnTerritorio' => count($enTerritorio),
                'muestrasSeleccionadas' => count($datos), 'consultasClimaticasEnEstaOperacion' => 0, 'spiCalculadosEnEstaOperacion' => 0],
            'datos' => $datos, 'representacionMapa' => ['tipo' => 'Point', 'rellenarPoligonos' => false],
            'advertencias' => ['Puntos candidatos de solicitud; no son estaciones, medias municipales ni cobertura climática.',
                'La celda y el índice permanecen null porque esta operación no consulta lluvia ni calcula sequía.']];
    }

    public function geojson(array $consulta): array
    {
        return ['type' => 'FeatureCollection', 'versionCatalogoMuestras' => $consulta['catalogo']['version'],
            'estado' => $consulta['estado'], 'resumen' => $consulta['resumen'], 'advertencias' => $consulta['advertencias'],
            'features' => array_map(fn (array $s) => ['type' => 'Feature', 'id' => $s['identificador'],
                'geometry' => ['type' => 'Point', 'coordinates' => $s['coordenadaSolicitada']],
                'properties' => array_diff_key($s, ['coordenadaSolicitada' => true])], $consulta['datos'])];
    }

    private function cargar(): void
    {
        if ($this->documento !== null) {
            return;
        }
        $d = $this->lector->leer($this->archivo, 256 * 1024)['datos'];
        $campos = ['version', 'estado', 'versionCatalogoTerritorial', 'crs', 'ordenCoordenadas', 'fuentesTerritorialesSha256', 'generacion', 'alcance', 'muestras'];
        if (array_diff(array_keys($d), $campos) || array_diff($campos, array_keys($d))
            || ($d['version'] ?? null) !== self::VERSION || ($d['estado'] ?? null) !== 'propuesta_pendiente_revision'
            || ($d['versionCatalogoTerritorial'] ?? null) !== $this->territorios->metadatos()['version']
            || ($d['crs'] ?? null) !== 'OGC:CRS84' || ($d['ordenCoordenadas'] ?? null) !== ['longitud', 'latitud']
            || ! is_string($d['alcance'] ?? null) || trim($d['alcance']) === '' || strlen($d['alcance']) > 500
            || ! is_array($d['muestras'] ?? null) || ! array_is_list($d['muestras'])) {
            $this->invalido();
        }
        $generacion = $d['generacion'];
        if (! is_array($generacion) || count($generacion) !== 4 || ($generacion['algoritmo'] ?? null) !== 'punto_interior_y_piloto_conservado_v1') {
            $this->invalido();
        }
        foreach (['shapely', 'geos', 'numpy'] as $biblioteca) {
            if (! is_string($generacion[$biblioteca] ?? null) || ! preg_match('/^[0-9]+\.[0-9]+\.[0-9]+$/D', $generacion[$biblioteca])) {
                $this->invalido();
            }
        }
        $fuentes = $d['fuentesTerritorialesSha256'] ?? [];
        $rutas = ['datos/catalogo.json', 'datos/limites/municipios.geojson'];
        if (! is_array($fuentes) || count($fuentes) !== 2) {
            $this->invalido();
        }
        $capas = [];
        foreach ($rutas as $ruta) {
            $fuente = $this->lector->leer($this->directorioTerritorial.'/'.$ruta);
            if (($fuentes[$ruta] ?? null) !== $fuente['sha256']) {
                $this->invalido();
            }
            $capas[$ruta] = $fuente['datos'];
        }
        $capa = $capas['datos/limites/municipios.geojson'];
        if (($capa['versionCatalogo'] ?? null) !== $d['versionCatalogoTerritorial']) {
            $this->invalido();
        }
        $geometrias = array_column($capa['features'], 'geometry', 'id');
        $municipios = $this->territorios->listar('municipio');
        if (count($d['muestras']) !== count($municipios) || count($geometrias) !== count($municipios)) {
            $this->invalido();
        }
        $porId = [];
        $porMunicipio = [];
        foreach ($d['muestras'] as $s) {
            if (! is_array($s) || count($s) !== 5 || ! is_string($s['identificador'] ?? null)
                || ! is_string($s['municipio'] ?? null) || ! is_string($s['provincia'] ?? null)
                || ! is_array($s['coordenadaSolicitada'] ?? null) || ! array_is_list($s['coordenadaSolicitada']) || count($s['coordenadaSolicitada']) !== 2
                || isset($porId[$s['identificador']]) || isset($porMunicipio[$s['municipio']])) {
                $this->invalido();
            }
            try {
                $t = $this->territorios->buscar($s['municipio']);
            } catch (FiltroTerritorialInvalido) {
                $this->invalido();
            }
            $piloto = $s['municipio'] === 'BO070101';
            $idEsperado = $piloto ? 'scz-piloto-01' : 'scz-'.strtolower($s['municipio']).'-01';
            if ($t['tipo'] !== 'municipio' || $t['padre'] !== $s['provincia'] || ! isset($geometrias[$s['municipio']])
                || $s['identificador'] !== $idEsperado
                || ($s['seleccion'] ?? null) !== ($piloto ? 'piloto_met35_conservado' : 'point_on_surface_redondeado_6_decimales')) {
                $this->invalido();
            }
            foreach ([180, 90] as $i => $limite) {
                $v = $s['coordenadaSolicitada'][$i];
                if ((! is_int($v) && ! is_float($v)) || ! is_finite((float) $v) || abs($v) > $limite || abs($v - round($v, 6)) > 5e-13) {
                    $this->invalido();
                }
            }
            [$lon, $lat] = array_map(floatval(...), $s['coordenadaSolicitada']);
            if (($piloto && [$lon, $lat] !== [-63.1821, -17.7833])
                || ! $this->geografia->interiorEstricto($geometrias[$s['municipio']], $lon, $lat)
                || $this->geografia->asignar($lon, $lat)['municipio'] !== $s['municipio']) {
                $this->invalido();
            }
            $s['coordenadaSolicitada'] = [$lon, $lat];
            $porId[$s['identificador']] = $s;
            $porMunicipio[$s['municipio']] = true;
        }
        $this->porId = $porId;
        $this->documento = $d;
    }

    private function invalido(): never
    {
        throw new SeriePrecipitacionInvalida('CATALOGO_MUESTRAS_INVALIDO', 'No se pudo verificar versión, procedencia, jerarquía o pertenencia exclusiva de las muestras candidatas.');
    }
}
