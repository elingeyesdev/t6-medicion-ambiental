<?php

declare(strict_types=1);

namespace App\Services\Territorios;

use RuntimeException;
use UnexpectedValueException;

/** Catálogo de consulta común: sin Laravel, red, claves o base de datos. */
final class CatalogoTerritorial
{
    private array $documento;

    private array $porIdentificador = [];

    public function __construct(private readonly string $directorioDatos)
    {
        $this->documento = $this->leerJson($directorioDatos.'/catalogo.json');
        if (($this->documento['espacioIdentificadores'] ?? null) !== 'PCODE_OCHA_CODAB_BOL_v02'
            || ! is_string($this->documento['version'] ?? null)
            || ! is_array($this->documento['territorios'] ?? null)) {
            throw new UnexpectedValueException('Formato o espacio de identificadores del catálogo no admitido.');
        }

        foreach ($this->documento['territorios'] as $territorio) {
            $id = $territorio['identificador'] ?? null;
            $tipo = $territorio['tipo'] ?? null;
            if (! is_string($id) || ! is_string($tipo)
                || ! in_array($tipo, ['departamento', 'provincia', 'municipio'], true)
                || isset($this->porIdentificador[$id]) || ! is_string($territorio['nombre'] ?? null)) {
                throw new UnexpectedValueException('Registro territorial incompleto, duplicado o con tipo desconocido.');
            }
            $this->porIdentificador[$id] = $territorio;
        }

        $tiposPadre = ['provincia' => 'departamento', 'municipio' => 'provincia'];
        foreach ($this->porIdentificador as $id => $territorio) {
            $padre = $territorio['padre'] ?? null;
            if ($territorio['tipo'] === 'departamento') {
                if ($id !== 'BO07' || $padre !== null) {
                    throw new UnexpectedValueException('El único departamento admitido es Santa Cruz BO07.');
                }
            } elseif (! is_string($padre)
                || ($this->porIdentificador[$padre]['tipo'] ?? null) !== $tiposPadre[$territorio['tipo']]) {
                throw new UnexpectedValueException('La jerarquía territorial está incompleta o es incompatible.');
            }
        }

        if (! isset($this->porIdentificador['BO07'])) {
            throw new UnexpectedValueException('Falta el departamento de Santa Cruz.');
        }
    }

    public function metadatos(): array
    {
        return array_diff_key($this->documento, ['territorios' => true]);
    }

    public function buscar(string $identificador): array
    {
        if (! isset($this->porIdentificador[$identificador])) {
            throw new FiltroTerritorialInvalido('TERRITORIO_NO_DISPONIBLE', 'El identificador no pertenece al catálogo de Santa Cruz.');
        }

        return $this->porIdentificador[$identificador];
    }

    public function listar(?string $tipo = null, ?string $padre = null): array
    {
        if ($tipo !== null && ! in_array($tipo, ['departamento', 'provincia', 'municipio'], true)) {
            throw new FiltroTerritorialInvalido('TIPO_TERRITORIAL_INVALIDO', 'Tipo admitido: departamento, provincia o municipio.');
        }
        if ($padre !== null) {
            $this->buscar($padre);
        }

        return array_values(array_filter($this->porIdentificador, fn (array $t): bool => ($tipo === null || $t['tipo'] === $tipo) && ($padre === null || $t['padre'] === $padre)
        ));
    }

    public function resolverFiltros(?string $provincia = null, ?string $municipio = null): array
    {
        $provinciaElegida = $provincia === null ? null : $this->exigirTipo($provincia, 'provincia');
        $municipioElegido = $municipio === null ? null : $this->exigirTipo($municipio, 'municipio');

        if ($municipioElegido !== null) {
            if ($provinciaElegida !== null && $municipioElegido['padre'] !== $provinciaElegida['identificador']) {
                throw new FiltroTerritorialInvalido('FILTROS_INCOMPATIBLES', 'El municipio no pertenece a la provincia indicada.');
            }
            $provinciaElegida = $this->exigirTipo($municipioElegido['padre'], 'provincia');
        }

        return [
            'departamento' => $this->buscar('BO07'),
            'provincia' => $provinciaElegida,
            'municipio' => $municipioElegido,
            'territorioEfectivo' => $municipioElegido ?? $provinciaElegida ?? $this->buscar('BO07'),
        ];
    }

    public function limite(string $identificador): array
    {
        $territorio = $this->buscar($identificador);
        // Los nombres de archivo son constantes: ningún parámetro se utiliza como ruta.
        $archivos = ['departamento' => 'departamentos.geojson', 'provincia' => 'provincias.geojson', 'municipio' => 'municipios.geojson'];
        $capa = $this->leerJson($this->directorioDatos.'/limites/'.$archivos[$territorio['tipo']]);
        if (($capa['versionCatalogo'] ?? null) !== $this->documento['version']) {
            throw new UnexpectedValueException('La geometría y el catálogo tienen versiones distintas.');
        }
        foreach ($capa['features'] as $feature) {
            if (($feature['id'] ?? null) === $identificador) {
                return $feature;
            }
        }

        throw new RuntimeException('No está disponible la geometría del territorio solicitado.');
    }

    private function exigirTipo(string $identificador, string $tipo): array
    {
        $territorio = $this->buscar($identificador);
        if ($territorio['tipo'] !== $tipo) {
            throw new FiltroTerritorialInvalido('TIPO_TERRITORIAL_INVALIDO', 'El identificador no corresponde al nivel territorial solicitado.');
        }

        return $territorio;
    }

    private function leerJson(string $ruta): array
    {
        if (! is_file($ruta) || ! is_readable($ruta)) {
            throw new RuntimeException('No se puede leer el recurso territorial requerido.');
        }

        $documento = json_decode(file_get_contents($ruta), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($documento)) {
            throw new UnexpectedValueException('El recurso territorial no contiene un objeto JSON válido.');
        }

        return $documento;
    }
}
