<?php

namespace App\Services\Alertas;

final class UmbralesAmbientales
{
    private ?array $datos = null;

    public function catalogo(): array
    {
        return $this->datos ??= json_decode(file_get_contents(base_path('../compartido/alertas/umbrales-envirosense.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    public function clasificar(string $metrica, float $valor): ?array
    {
        $niveles = $this->catalogo()['metricas'][$metrica] ?? [];
        foreach ($niveles as $nivel) {
            if ($valor >= $nivel['minimo'] && $valor <= $nivel['maximo']) {
                return $nivel;
            }
        }
        // Los huecos decimales del seed pasan al intervalo superior, nunca directamente al último nivel.
        foreach ($niveles as $nivel) {
            if ($valor < $nivel['minimo']) {
                return $nivel;
            }
        }

        return $niveles ? $niveles[array_key_last($niveles)] : null;
    }
}
