<?php

namespace App\Services\Territorios;

use App\Services\Api\ErrorConsulta;

final class RegionesEnvirosense
{
    private ?array $indice = null;

    public function listado(): array
    {
        return $this->indice ??= json_decode(file_get_contents(base_path('../compartido/geografia/regiones/indice.json')), true, 512, JSON_THROW_ON_ERROR)['datos'];
    }

    public function limite(string $codigo): array
    {
        $region = collect($this->listado())->firstWhere('codigo', $codigo);
        if (! $region) {
            throw new ErrorConsulta('FILTROS_INVALIDOS', 'Elige una región del país seleccionado.');
        }
        $contenido = file_get_contents(base_path('../compartido/geografia/regiones/'.$region['codigo'].'.geojson'));
        if (! hash_equals($region['sha256'], hash('sha256', $contenido))) {
            throw new \RuntimeException('La geometría no coincide con el catálogo recuperado.');
        }

        return json_decode($contenido, true, 512, JSON_THROW_ON_ERROR);
    }
}
