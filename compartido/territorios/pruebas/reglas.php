<?php

declare(strict_types=1);

use App\Services\Territorios\CatalogoTerritorial;
use App\Services\Territorios\FiltroTerritorialInvalido;

return function (CatalogoTerritorial $catalogo): int {
    $total = 0;
    $exigir = function (bool $condicion, string $mensaje) use (&$total): void {
        if (! $condicion) {
            throw new RuntimeException($mensaje);
        }
        $total++;
    };
    $rechazar = function (callable $accion, string $codigo) use ($exigir): void {
        try {
            $accion();
        } catch (FiltroTerritorialInvalido $error) {
            $exigir($error->codigo === $codigo, 'Código de rechazo incorrecto.');

            return;
        }
        throw new RuntimeException('Se aceptó un filtro territorial inválido.');
    };

    $exigir(count($catalogo->listar()) === 72, 'Deben existir 72 registros.');
    $exigir(count($catalogo->listar('departamento')) === 1, 'Departamento incompleto.');
    $exigir(count($catalogo->listar('provincia', 'BO07')) === 15, 'Provincias incompletas.');
    $exigir(count($catalogo->listar('municipio')) === 56, 'Nivel municipal incompleto.');
    $exigir($catalogo->resolverFiltros()['territorioEfectivo']['identificador'] === 'BO07', 'Sin filtros debe seleccionarse el departamento.');
    $exigir($catalogo->resolverFiltros('BO0701')['territorioEfectivo']['identificador'] === 'BO0701', 'La provincia debe conservarse.');
    $exigir(count($catalogo->listar('municipio', 'BO0701')) === 5, 'Andrés Ibáñez debe tener cinco unidades municipales.');

    foreach ($catalogo->listar('municipio') as $municipio) {
        $seleccion = $catalogo->resolverFiltros(null, $municipio['identificador']);
        $exigir($seleccion['provincia']['identificador'] === $municipio['padre'], 'La provincia no coincide con el padre publicado.');
        $exigir($seleccion['territorioEfectivo']['identificador'] === $municipio['identificador'], 'El municipio efectivo cambió.');
        $correcta = $catalogo->resolverFiltros($municipio['padre'], $municipio['identificador']);
        $exigir($correcta['municipio']['identificador'] === $municipio['identificador'], 'Se rechazó una relación válida.');
        $otraProvincia = $municipio['padre'] === 'BO0701' ? 'BO0702' : 'BO0701';
        $rechazar(fn () => $catalogo->resolverFiltros($otraProvincia, $municipio['identificador']), 'FILTROS_INCOMPATIBLES');
    }

    $exigir(str_contains($catalogo->buscar('BO070702')['nombre'], 'Charagua Iyambae'), 'Se perdió la denominación INE de Charagua.');
    $exigir(str_contains($catalogo->buscar('BO070705')['nombre'], 'Kereimba Iyaambae'), 'Se perdió la denominación INE de Gutiérrez.');
    $exigir($catalogo->buscar('BO070702')['denominacionAutonomiaINE'] === true, 'Falta la indicación de denominación de autonomía.');
    $rechazar(fn () => $catalogo->resolverFiltros('BO0701', 'BO070201'), 'FILTROS_INCOMPATIBLES');
    $rechazar(fn () => $catalogo->resolverFiltros('BO07'), 'TIPO_TERRITORIAL_INVALIDO');
    $rechazar(fn () => $catalogo->resolverFiltros(null, 'BO0701'), 'TIPO_TERRITORIAL_INVALIDO');
    $rechazar(fn () => $catalogo->resolverFiltros(null, 'BO08'), 'TERRITORIO_NO_DISPONIBLE');
    $rechazar(fn () => $catalogo->resolverFiltros('', null), 'TERRITORIO_NO_DISPONIBLE');
    $rechazar(fn () => $catalogo->listar('canton'), 'TIPO_TERRITORIAL_INVALIDO');
    $rechazar(fn () => $catalogo->listar('municipio', '../../archivo'), 'TERRITORIO_NO_DISPONIBLE');
    $rechazar(fn () => $catalogo->limite('../../archivo'), 'TERRITORIO_NO_DISPONIBLE');
    $limite = $catalogo->limite('BO070101');
    $exigir($limite['id'] === 'BO070101' && in_array($limite['geometry']['type'], ['Polygon', 'MultiPolygon'], true), 'La geometría no corresponde al municipio.');
    $exigir($catalogo->metadatos()['estado'] === 'propuesta_pendiente_revision', 'No se debe inventar aceptación del equipo.');

    return $total;
};
