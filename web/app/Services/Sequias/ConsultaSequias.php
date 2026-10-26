<?php

declare(strict_types=1);

namespace App\Services\Sequias;

use App\Services\Api\ErrorConsulta;
use App\Services\Sequias\Experimental\CalculoSpi3ConCache;
use App\Services\Sequias\Experimental\CatalogoMuestrasSequias;
use App\Services\Sequias\Experimental\EnsayoSpi3;
use App\Services\Territorios\GeometriaTerritorial;
use DateTimeImmutable;

/** Consulta de resultados preparados: sin red, descargas ni ajustes estadísticos en HTTP. */
final class ConsultaSequias
{
    public const CONTRATO = 'sequias-1.0-m3-candidata';

    public function __construct(
        private readonly CatalogoMuestrasSequias $catalogo,
        private readonly AlmacenPublicacionesSequias $almacen,
        private readonly CalculoSpi3ConCache $calculos,
        private readonly EnsayoSpi3 $ensayo,
        private readonly GeometriaTerritorial $geografia,
        private readonly ProcedenciaSeriePrecipitacion $procedencias,
    ) {}

    public function ejecutar(array $p): array
    {
        try {
            return $this->consultar($p);
        } catch (SeriePrecipitacionInvalida $e) {
            $codigo = match ($e->codigo) {
                'MES_NO_ADMITIDO', 'MUESTRA_INVALIDA', 'TIPO_TERRITORIAL_INVALIDO' => 'FILTROS_INVALIDOS',
                'CATALOGO_MUESTRAS_INVALIDO', 'SERIE_CORRUPTA', 'PUBLICACION_INVALIDA', 'SERIES_INCOMPATIBLES' => 'DATOS_INVALIDOS',
                default => $e->codigo,
            };
            $estado = match ($codigo) {
                'FILTROS_INVALIDOS', 'FILTROS_INCOMPATIBLES' => 422,
                'TERRITORIO_NO_DISPONIBLE', 'MUESTRA_NO_DISPONIBLE' => 404,
                default => 503,
            };
            throw new ErrorConsulta($codigo, $e->getMessage(), $estado);
        }
    }

    private function consultar(array $p): array
    {
        foreach (['provincia' => '/^BO07[0-9]{2}$/D', 'municipio' => '/^BO07[0-9]{4}$/D', 'muestra' => '/^[a-zA-Z0-9][a-zA-Z0-9_-]{0,79}$/D'] as $campo => $patron) {
            if (isset($p[$campo]) && ! preg_match($patron, $p[$campo])) {
                throw new ErrorConsulta('FILTROS_INVALIDOS', 'Formato inválido de '.$campo.'.');
            }
        }
        $periodo = PreparadorConsultaSequias::periodo($p['mesFinal'] ?? '');
        $seleccion = $this->catalogo->consultar($p['provincia'] ?? null, $p['municipio'] ?? null, $p['muestra'] ?? null);
        $datos = $capturas = $faltantes = $fechasObjetivo = $caducidades = $preparados = $celdas = $municipiosIndice = [];
        $motor = $this->calculos->motor();
        foreach ($seleccion['datos'] as $s) {
            $publicado = $this->almacen->leer('consultas', PreparadorConsultaSequias::clave($s['identificador'], $periodo['mesFinal']));
            if ($publicado === null) {
                $faltantes[] = $s['identificador'];

                continue;
            }
            $r = $this->verificar($publicado, $s, $seleccion['catalogo'], $motor, $periodo);
            [$lon, $lat] = $r['coordenadaCelda'];
            $centro = $this->geografia->asignar($lon, $lat)['municipio'];
            $datos[] = ['idMuestra' => $s['identificador'], 'territorioContexto' => $s['municipio'], 'municipioDelCentroCelda' => $centro,
                'coordenadaSolicitada' => $s['coordenadaSolicitada'], 'coordenadaCelda' => $r['coordenadaCelda'],
                'naturaleza' => 'derivado_de_reanalisis', 'fuentesRef' => ['open_meteo_era5'], 'metodo' => $r['metodo'],
                'precipitacionAcumuladaMm' => $r['precipitacionAcumuladaMm'], 'spi' => $r['resultado']];
            foreach ($r['versiones'] as $v) {
                $origen = $v['procedencia'];
                $capturas[$v['identificador']] = ['identificador' => $v['identificador'], 'url' => $origen['urlOrigen'], 'sha256' => $origen['sha256'],
                    'obtenidoEn' => $origen['obtenidoEn'], 'periodo' => $origen['periodo'], 'muestraOrigen' => $origen['muestra']];
            }
            $fechasObjetivo[] = $r['versiones'][1]['procedencia']['obtenidoEn'];
            $caducidades[] = $publicado['vigenteHasta'];
            $preparados[] = $publicado['preparadoEn'];
            $celdas[json_encode($r['coordenadaCelda'], JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR)] = true;
            if ($r['resultado']['valor'] !== null) {
                $municipiosIndice[$s['municipio']] = true;
            }
        }
        if ($datos === []) {
            throw new ErrorConsulta('MUESTRAS_NO_DISPONIBLES', 'No hay resultados preparados para este filtro y mes. Ejecuta sequias:preparar-consulta fuera de la petición web.', 503);
        }
        usort($datos, fn ($a, $b) => strcmp($a['idMuestra'], $b['idMuestra']));
        ksort($capturas);
        $indices = array_values(array_filter(array_column(array_column($datos, 'spi'), 'valor'), fn ($v) => $v !== null));
        $advertencias = ['Índice meteorológico propio de GeoCruz, derivado de ERA5; no es el SPI oficial de Copernicus ni una estación local.',
            'Resultados de muestras puntuales: no representan promedio, superficie ni daño de todo el municipio.',
            'La captura y su cálculo se prepararon fuera de esta petición; reutilizar no actualiza los datos históricos.'];
        if ($faltantes !== []) {
            $advertencias[] = 'Cobertura parcial: '.count($faltantes).' muestras seleccionadas no tienen datos preparados para este mes.';
        }
        if (count($indices) !== count($datos)) {
            $advertencias[] = 'Hay muestras sin índice; consultar su motivo. Un null no significa normal ni ausencia de sequía.';
        }
        if (count($celdas) < count($datos)) {
            $advertencias[] = 'Algunas muestras comparten celda ERA5; no son observaciones independientes.';
        }
        foreach ($datos as $d) {
            if ($d['municipioDelCentroCelda'] !== $d['territorioContexto']) {
                $advertencias[] = 'El centro de alguna celda pertenece a otro municipio o es ambiguo; se conserva el municipio de la muestra solicitada.';
                break;
            }
        }

        return ['versionContrato' => self::CONTRATO, 'datos' => $datos,
            'consulta' => ['estado' => 'metodo_acordado', ...$periodo, ...$seleccion['filtrosEfectivos'],
                'referencia' => ['desde' => '1991-01-01', 'hastaIncluido' => '2020-12-31', 'ventanaMeses' => 3],
                'obtencion' => ['tipo' => 'cache', 'obtenidoEn' => min($fechasObjetivo), 'esRespuestaHttp' => true,
                    'preparadoEn' => min($preparados), 'vigenteHasta' => min($caducidades)]],
            'fuentes' => [['id' => 'open_meteo_era5', 'proveedor' => 'Open-Meteo', 'producto' => 'ERA5', 'naturaleza' => 'reanalisis',
                'variable' => 'precipitation_sum', 'unidad' => 'mm', 'resolucionGrados' => 0.25,
                'licencia' => 'https://creativecommons.org/licenses/by/4.0/', 'atribucion' => 'Open-Meteo.com; ECMWF / Copernicus Climate Change Service, ERA5.',
                'fechaActualizacion' => null, 'capturas' => array_values($capturas)]],
            'resumen' => ['totalMuestras' => count($datos), 'muestrasConIndice' => count($indices), 'muestrasSinIndice' => count($datos) - count($indices),
                'muestrasConDeficit' => count(array_filter($indices, fn ($v) => $v <= -1)), 'spiMinimo' => $indices === [] ? null : min($indices), 'unidadConteo' => 'muestras_no_superficie'],
            'cobertura' => ['estadoEspacial' => 'muestras_puntuales', 'municipiosEnFiltro' => $seleccion['resumen']['municipiosEnTerritorio'],
                'municipiosConMuestra' => count(array_unique(array_column($seleccion['datos'], 'municipio'))),
                'municipiosConDatos' => count(array_unique(array_column($datos, 'territorioContexto'))), 'municipiosConIndice' => count($municipiosIndice),
                'celdasUnicas' => count($celdas), 'superficieEvaluadaKm2' => null, 'muestrasSeleccionadas' => count($seleccion['datos']),
                'muestrasSinPreparar' => $faltantes, 'estadoDatos' => $faltantes === [] ? 'completo_para_muestras_seleccionadas' : 'parcial'],
            'advertencias' => $advertencias, 'paginacion' => ['aplica' => false],
            'representacionMapa' => ['tipo' => 'Point', 'alcance' => 'conjunto', 'rellenarPoligonos' => false]];
    }

    private function verificar(array $p, array $s, array $catalogo, array $motor, array $periodo): array
    {
        if (($p['version'] ?? null) !== 1 || ($p['muestra'] ?? null) !== $s['identificador'] || ($p['mesFinal'] ?? null) !== $periodo['mesFinal']
            || ($p['catalogo'] ?? null) !== $catalogo || ($p['motor'] ?? null) !== $motor
            || ! is_string($p['base'] ?? null) || ! is_string($p['objetivo'] ?? null) || ! is_array($p['informe'] ?? null)) {
            throw new SeriePrecipitacionInvalida('PUBLICACION_INVALIDA', 'El resultado no corresponde al método, motor, catálogo o ventana actuales. Prepara otra versión.');
        }
        $actual = $this->ensayo->preparar($p['base'], $p['objetivo'], $periodo['mesFinal'], DateTimeImmutable::createFromInterface(now()), true);
        $informe = $p['informe'];
        $resultado = $informe['resultado'] ?? null;
        unset($informe['resultado']);
        if ($informe !== $actual->informe || $informe['tipoEvidencia'] !== 'captura_externa' || $informe['muestra'] !== $s['identificador']
            || $informe['coordenadaSolicitada'] !== $s['coordenadaSolicitada'] || ! is_array($resultado)) {
            throw new SeriePrecipitacionInvalida('PUBLICACION_INVALIDA', 'No coincide la publicación con las series originales verificadas.');
        }
        $recuperado = new DateTimeImmutable($this->procedencias->instante($informe['versiones'][1]['procedencia']['obtenidoEn']));
        $preparado = new DateTimeImmutable($this->procedencias->instante($p['preparadoEn'] ?? null));
        $vence = new DateTimeImmutable($this->procedencias->instante($p['vigenteHasta'] ?? null));
        if ($preparado > now() || $recuperado > $preparado || $vence != $recuperado->modify('+'.($resultado['valor'] === null ? 900 : 86400).' seconds')) {
            throw new SeriePrecipitacionInvalida('PUBLICACION_INVALIDA', 'Fechas de publicación incoherentes.');
        }
        if ($vence <= now()) {
            throw new ErrorConsulta('PERIODO_NO_DISPONIBLE', 'Los datos preparados vencieron. Renueva la captura por consola; no se entregó el resultado caducado.', 503);
        }

        return $p['informe'];
    }
}
