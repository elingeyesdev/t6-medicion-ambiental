<?php

declare(strict_types=1);

namespace App\Services\Sequias\Experimental;

use App\Services\Territorios\GeometriaTerritorial;

final class CapturaMuestraCatalogada
{
    public function __construct(private readonly CatalogoMuestrasSequias $catalogo, private readonly CapturadorPrecipitacion $capturador,
        private readonly GeometriaTerritorial $geografia) {}

    public function ejecutar(string $muestra, string $desde, string $hasta, bool $renovar = false, ?string $provincia = null, ?string $municipio = null): array
    {
        $consulta = $this->catalogo->consultar($provincia, $municipio, $muestra);
        $s = $consulta['datos'][0];
        [$lon, $lat] = $s['coordenadaSolicitada'];
        $r = $this->capturador->ejecutar($s['identificador'], $lon, $lat, $desde, $hasta, $renovar);
        [$x, $y] = $r['version']['procedencia']['coordenadaCelda'];
        $centro = $this->geografia->asignar($x, $y);
        $r['muestraCatalogada'] = ['identificador' => $s['identificador'], 'municipio' => $s['municipio'], 'provincia' => $s['provincia'],
            'nombreMunicipio' => $s['nombreMunicipio'], 'nombreProvincia' => $s['nombreProvincia'],
            'coordenadaSolicitada' => $s['coordenadaSolicitada'], 'versionCatalogoMuestras' => $consulta['catalogo']['version'],
            'versionCatalogoTerritorial' => $consulta['catalogo']['versionCatalogoTerritorial'],
            'fuentesTerritorialesSha256' => $consulta['catalogo']['fuentesTerritorialesSha256']];
        $r['municipioDelCentroCelda'] = $centro['municipio'];
        $r['motivoAsignacionCentro'] = $centro['motivoAsignacion'];
        if ($centro['municipio'] !== $s['municipio']) {
            $r['advertencias'][] = 'El centro de celda no coincide exclusivamente con el municipio solicitado; se conserva el contexto de la muestra, sin reasignarlo al centro.';
        }

        return $r;
    }
}
