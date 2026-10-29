<?php

namespace App\Services\Datos;

final class CatalogoAmbiental
{
    private ?array $catalogo = null;

    public const ORIGENES = ['observado' => 'Registrado', 'estimado' => 'Estimado', 'simulado' => 'Simulado'];

    public function leer(): array
    {
        return $this->catalogo ??= json_decode(file_get_contents(base_path('../compartido/sensores/catalogo-envirosense.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    public function metricas(): array
    {
        return $this->leer()['metricas'];
    }

    public function paises(): array
    {
        return json_decode(file_get_contents(base_path('../compartido/geografia/paises-envirosense.json')), true, 512, JSON_THROW_ON_ERROR)['datos'];
    }
}
