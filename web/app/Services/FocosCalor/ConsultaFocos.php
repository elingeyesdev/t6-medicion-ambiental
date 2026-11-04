<?php

namespace App\Services\FocosCalor;

use App\Services\Api\ErrorConsulta;
use App\Services\Api\ParametrosConsulta as Parametros;
use App\Services\Territorios\CatalogoTerritorial;
use App\Services\Territorios\GeometriaTerritorial;
use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class ConsultaFocos
{
    /** Países ISO-3 de Sudamérica para la API country de FIRMS (evita bbox continental inestable). */
    private const PAISES_SUDAMERICA = ['ARG', 'BOL', 'BRA', 'CHL', 'COL', 'ECU', 'GUY', 'PER', 'PRY', 'SUR', 'URY', 'VEN'];

    public function __construct(private readonly CatalogoTerritorial $catalogo, private readonly GeometriaTerritorial $geografia, private readonly ClienteFirms $nasa, private readonly NormalizadorFirms $normalizador) {}

    public function ejecutar(array $p): array
    {
        $desde = Parametros::instante($p['desde'] ?? null);
        $hasta = Parametros::instante($p['hasta'] ?? null);
        if ($desde >= $hasta) {
            throw new ErrorConsulta('FILTROS_INVALIDOS', 'El período debe ser positivo.');
        }
        if ($hasta->getTimestamp() > now()->timestamp) {
            throw new ErrorConsulta('FILTROS_INVALIDOS', 'Elige fechas hasta el momento actual. No se pueden consultar días futuros.');
        }
        if (isset($p['confianza']) && ! in_array($p['confianza'], ['alta', 'nominal', 'baja', 'desconocida'], true)) {
            throw new ErrorConsulta('FILTROS_INVALIDOS', 'Confianza VIIRS no admitida.');
        }
        $modo = $p['modo'] ?? 'externo';
        if (! in_array($modo, ['externo', 'demostracion'], true)) {
            throw new ErrorConsulta('FILTROS_INVALIDOS', 'Modo desconocido.');
        }
        $alcance = $p['alcance'] ?? 'sudamerica';
        if (! in_array($alcance, ['sudamerica', 'santacruz'], true)) {
            throw new ErrorConsulta('FILTROS_INVALIDOS', 'Alcance admitido: sudamerica o santacruz.');
        }
        $seleccion = null;
        if ($alcance === 'santacruz' || isset($p['provincia']) || isset($p['municipio'])) {
            $seleccion = $this->catalogo->resolverFiltros($p['provincia'] ?? null, $p['municipio'] ?? null);
            $alcance = 'santacruz';
        }
        // Sudamérica: máximo 72 h para no saturar FIRMS; Santa Cruz conserva 168 h.
        $maxHoras = $alcance === 'sudamerica' ? 72 : 168;
        if ($hasta->getTimestamp() - $desde->getTimestamp() > $maxHoras * 3600) {
            throw new ErrorConsulta(
                'FILTROS_INVALIDOS',
                $alcance === 'sudamerica'
                    ? 'Para Sudamérica el período máximo es de 72 horas; reduce el rango o filtra por territorio de Santa Cruz.'
                    : 'El período debe ser positivo y de un máximo de 168 horas.'
            );
        }
        $pagina = Parametros::entero($p, 'pagina', 1, 1000000);
        $tamanio = Parametros::entero($p, 'tamanioPagina', 100, 500);
        if ($modo === 'demostracion') {
            throw new ErrorConsulta('MODULO_NO_DISPONIBLE', 'Esta implementación ofrece consulta externa; no genera demostraciones.', 503);
        }
        $efectivos = [
            'alcance' => $alcance,
            'provincia' => $seleccion['provincia']['identificador'] ?? null,
            'municipio' => $seleccion['municipio']['identificador'] ?? null,
            'desde' => Parametros::utc($desde), 'hasta' => Parametros::utc($hasta),
            'modo' => $modo, 'confianza' => $p['confianza'] ?? null,
        ];
        $huella = hash('sha256', json_encode([$efectivos, $this->catalogo->metadatos()['version'], config('focos.producto')]));
        $token = $p['versionConjunto'] ?? null;
        $esCache = false;
        if ($token !== null) {
            if (! Str::isUuid($token)) {
                throw new ErrorConsulta('FILTROS_INVALIDOS', 'versionConjunto debe ser el identificador recibido en la primera página.');
            }
            $conjunto = Cache::get('focos:conjunto:'.$token);
            if (! is_array($conjunto) || $conjunto['huella'] !== $huella) {
                throw new ErrorConsulta('CONJUNTO_CAMBIADO', 'El conjunto expiró o los filtros cambiaron. Reinicia desde la primera página.', 409);
            }
            $esCache = true;
        } else {
            if ($pagina !== 1) {
                throw new ErrorConsulta('CONJUNTO_CAMBIADO', 'Solicita la primera página y conserva versionConjunto para continuar.', 409);
            }
            $token = Cache::get('focos:reciente:'.$huella);
            $conjunto = $token ? Cache::get('focos:conjunto:'.$token) : null;
            if (is_array($conjunto)) {
                $esCache = true;
            } else {
                $conjunto = $this->preparar($desde, $hasta, $seleccion, $p['confianza'] ?? null, $alcance);
                $conjunto['huella'] = $huella;
                $conjunto['versionConjunto'] = (string) Str::uuid();
                $conjunto['expiraEn'] = now()->addSeconds(config('focos.conjunto_segundos'))->toIso8601ZuluString();
                Cache::put('focos:conjunto:'.$conjunto['versionConjunto'], $conjunto, config('focos.conjunto_segundos'));
                Cache::put('focos:reciente:'.$huella, $conjunto['versionConjunto'], config('focos.cache_segundos'));
            }
        }
        $todos = $conjunto['datos'];
        $total = count($todos);
        $datos = array_slice($todos, ($pagina - 1) * $tamanio, $tamanio);
        $potencias = array_values(array_filter(array_column(array_column($todos, 'atributos'), 'potenciaRadiativa'), fn ($v) => $v !== null));
        $altos = count(array_filter($todos, fn ($f) => $f['atributos']['confianza'] === 'alta'));
        $indicador = fn ($codigo, $valor, $unidad, $validos, $metodo) => compact('codigo', 'valor', 'unidad') + ['registrosValidos' => $validos, 'metodo' => $metodo, 'naturaleza' => 'observacion'];

        return [
            'versionContrato' => config('focos.version_contrato'),
            'datos' => $datos,
            'consulta' => [
                'identificador' => (string) Str::uuid(), 'modulo' => 'focos-calor', 'modo' => 'externo',
                'filtrosSolicitados' => (object) $p, 'filtrosEfectivos' => $efectivos,
                'territorio' => $alcance === 'sudamerica'
                    ? ['identificador' => 'SA', 'tipo' => 'region', 'nombre' => 'Sudamérica', 'departamento' => null, 'provincia' => null, 'municipio' => null, 'fuenteCatalogo' => 'firms-country-sa', 'versionCatalogo' => null, 'motivoAsignacion' => null]
                    : $this->territorio($seleccion['territorioEfectivo']),
                'periodo' => ['desde' => Parametros::utc($desde), 'hasta' => Parametros::utc($hasta), 'resolucion' => 'instante de adquisición UTC', 'regla' => 'final_excluido'],
                'fechaConsulta' => now()->toIso8601ZuluString(),
                'obtencion' => ['tipo' => $esCache ? 'cache' : 'consulta_proveedor', 'fechaRecuperacion' => $conjunto['fechaRecuperacion'], 'fechaActualizacion' => null, 'antiguedadSegundos' => max(0, now()->timestamp - (new DateTimeImmutable($conjunto['fechaRecuperacion']))->getTimestamp()), 'expiraEn' => $conjunto['expiraEn']],
            ],
            'fuentes' => [[
                'identificador' => 'nasa-firms-snpp-nrt', 'proveedor' => 'NASA FIRMS', 'producto' => config('focos.producto'),
                'version' => count($conjunto['versiones']) === 1 ? $conjunto['versiones'][0] : null,
                'referencia' => 'https://firms.modaps.eosdis.nasa.gov/api/area/', 'naturaleza' => 'observacion',
                'resolucion' => ['valor' => 375, 'unidad' => 'm', 'descripcion' => 'Resolución nominal VIIRS; no precisión del punto ni superficie quemada.'],
            ]],
            'resumen' => ['totalRegistros' => $total, 'registrosValidos' => $total, 'registrosConFaltantes' => count(array_filter($todos, fn ($f) => $f['faltantes'] !== [])),
                'indicadores' => [$indicador('totalFocos', $total, 'registros', $total, 'conteo'), $indicador('focosConfianzaAlta', $altos, 'registros', $total, 'conteo_confianza_alta'), $indicador('potenciaMaxima', $potencias === [] ? null : max($potencias), 'MW', count($potencias), 'maximo')]],
            'cobertura' => $conjunto['cobertura'], 'advertencias' => $conjunto['advertencias'],
            'paginacion' => ['pagina' => $pagina, 'tamanioPagina' => $tamanio, 'total' => $total, 'siguiente' => $pagina * $tamanio < $total ? $pagina + 1 : null, 'versionConjunto' => $conjunto['versionConjunto']],
            'representacionMapa' => ['alcance' => 'pagina', 'descripcion' => 'Solo los registros de esta página. Recuperar las siguientes con los mismos filtros y versionConjunto; no representar esta página como el total.', 'referenciaCapa' => null],
        ];
    }

    private function preparar(DateTimeImmutable $desde, DateTimeImmutable $hasta, ?array $seleccion, ?string $confianza, string $alcance): array
    {
        $disponible = $this->nasa->disponibilidad();
        $inicioProducto = new DateTimeImmutable($disponible['desde']);
        $finProducto = new DateTimeImmutable($disponible['hasta']);
        $sinCobertura = $hasta <= $inicioProducto || $desde >= $finProducto;
        $parcial = $desde < $inicioProducto || $hasta > $finProducto;
        $geometria = null;
        $caja = null;
        if ($alcance !== 'sudamerica') {
            $geometria = $this->catalogo->limite($seleccion['territorioEfectivo']['identificador'])['geometry'];
            $caja = $this->geografia->caja($geometria);
        }
        $datos = [];
        $invalidos = $duplicados = $conflictos = $fuera = 0;
        $versiones = [];
        if (! $sinCobertura) {
            $inicio = max($desde, $inicioProducto)->setTime(0, 0);
            $fin = min($hasta, $finProducto);
            $filas = [];
            while ($inicio < $fin) {
                $dias = min(5, (int) ceil(($fin->getTimestamp() - $inicio->getTimestamp()) / 86400));
                if ($alcance === 'sudamerica') {
                    foreach (self::PAISES_SUDAMERICA as $pais) {
                        array_push($filas, ...$this->nasa->pais($pais, $inicio, $dias));
                        if (count($filas) > config('focos.maximo_filas')) {
                            throw new ErrorConsulta('PROVEEDOR_NO_DISPONIBLE', 'La consulta excede el volumen admitido. Reduce el período.', 503);
                        }
                    }
                } else {
                    array_push($filas, ...$this->nasa->area($caja, $inicio, $dias));
                    if (count($filas) > config('focos.maximo_filas')) {
                        throw new ErrorConsulta('PROVEEDOR_NO_DISPONIBLE', 'La consulta excede el volumen admitido. Reduce el período o territorio.', 503);
                    }
                }
                $inicio = $inicio->modify('+'.$dias.' days');
            }
            foreach ($filas as $fila) {
                $foco = $this->normalizador->convertir($fila);
                if ($foco === null) {
                    $invalidos++;

                    continue;
                }
                $fecha = $foco['fechas']['observadoDesde'];
                [$x, $y] = $foco['geometria']['coordinates'];
                $fueraTerritorio = $geometria !== null && ! $this->geografia->contiene($geometria, $x, $y);
                if ($fecha < Parametros::utc($desde) || $fecha >= Parametros::utc($hasta) || $fueraTerritorio) {
                    $fuera++;

                    continue;
                }
                $id = $foco['identificador'];
                if (isset($datos[$id])) {
                    $duplicados++;
                    if ($datos[$id]['atributos'] !== $foco['atributos']) {
                        $conflictos++;
                        // Elección reproducible entre variantes; se informa el conflicto.
                        if (strcmp(json_encode($datos[$id]['atributos']), json_encode($foco['atributos'])) <= 0) {
                            continue;
                        }
                    } else {
                        continue;
                    }
                }
                $datos[$id] = $foco;
            }
            foreach ($datos as $id => &$foco) {
                if ($confianza !== null && $foco['atributos']['confianza'] !== $confianza) {
                    unset($datos[$id]);

                    continue;
                }
                if ($alcance === 'sudamerica') {
                    $foco['territorio'] = [
                        'identificador' => 'SA', 'tipo' => 'region', 'departamento' => null,
                        'provincia' => null, 'municipio' => null,
                        'fuenteCatalogo' => 'firms-country-sa', 'versionCatalogo' => null, 'motivoAsignacion' => null,
                    ];
                } else {
                    $asignacion = $this->geografia->asignar(...$foco['geometria']['coordinates']);
                    $foco['territorio'] = $this->territorio($this->catalogo->buscar('BO07'), $asignacion);
                    if ($asignacion['motivoAsignacion'] !== null) {
                        $foco['faltantes'][] = ['campo' => 'territorio.municipio', 'motivo' => $asignacion['motivoAsignacion']];
                    }
                }
                $versiones[] = $foco['atributos']['versionOriginal'];
            }
            unset($foco);
        }
        $datos = array_values($datos);
        usort($datos, fn ($a, $b) => strcmp($b['fechas']['observadoDesde'], $a['fechas']['observadoDesde']) ?: strcmp($a['identificador'], $b['identificador']));
        $avisos = [
            ['codigo' => 'INTERPRETACION', 'mensaje' => 'Detecciones térmicas, no incendios confirmados. Confianza no es gravedad; FRP en MW no es superficie quemada.'],
            ['codigo' => 'ACTUALIZACION_DESCONOCIDA', 'mensaje' => 'fechaActualizacion no informada; recuperación no equivale a adquisición ni a actualización del producto.'],
        ];
        if ($alcance === 'santacruz') {
            array_unshift($avisos, ['codigo' => 'CATALOGO_PENDIENTE_REVISION', 'mensaje' => 'Catálogo territorial en revisión: versión candidata, no adoptada por el equipo.']);
            $avisos[] = ['codigo' => 'FRONTERAS', 'mensaje' => 'Se incluyen bordes del área consultada una sola vez por detección; pertenencia municipal ambigua queda nula. Consultas independientes de áreas vecinas pueden compartir puntos de borde.'];
        } else {
            $avisos[] = ['codigo' => 'ALCANCE_SUDAMERICA', 'mensaje' => 'Consulta FIRMS por países de Sudamérica; no aplica catálogo municipal de Santa Cruz.'];
        }
        foreach (['FILAS_INVALIDAS' => $invalidos, 'DUPLICADOS' => $duplicados, 'DUPLICADOS_CONFLICTIVOS' => $conflictos, 'FUERA_DE_FILTROS' => $fuera] as $codigo => $cantidad) {
            if ($cantidad > 0) {
                $avisos[] = ['codigo' => $codigo, 'mensaje' => 'Registros afectados: '.$cantidad, 'cantidad' => $cantidad];
            }
        }
        if ($versiones === [] || in_array(null, $versiones, true) || count(array_unique($versiones)) > 1) {
            $avisos[] = ['codigo' => 'VERSION_PRODUCTO_NO_UNICA', 'mensaje' => 'Versión de producto no informada o múltiple; se conserva la versión original por registro.'];
        }

        return [
            'datos' => $datos, 'versiones' => array_values(array_unique($versiones)), 'advertencias' => $avisos,
            'fechaRecuperacion' => now()->toIso8601ZuluString(),
            'cobertura' => [
                'estado' => $sinCobertura ? 'sin_cobertura' : ($parcial || $invalidos > 0 ? 'parcial' : 'desconocida'),
                'fundamento' => $sinCobertura ? 'Período fuera de la disponibilidad publicada del producto.' : 'Disponibilidad temporal publicada; las detecciones no acreditan observación completa del territorio. Filas inválidas y períodos no disponibles se advierten.',
                'referenciaEvidencia' => 'https://firms.modaps.eosdis.nasa.gov/api/data_availability/',
                'periodoDisponible' => $disponible,
                'ambitoDisponible' => $alcance === 'sudamerica'
                    ? 'Producto global consultado por países de Sudamérica (FIRMS country API).'
                    : 'Producto global consultado mediante caja y filtrado al polígono territorial de referencia.',
            ],
        ];
    }

    public function territorio(array $t, ?array $asignacion = null): array
    {
        if ($asignacion !== null) {
            $t = $this->catalogo->buscar($asignacion['municipio'] ?? $asignacion['provincia'] ?? 'BO07');
        }
        $filtros = $this->catalogo->resolverFiltros($t['tipo'] === 'provincia' ? $t['identificador'] : null, $t['tipo'] === 'municipio' ? $t['identificador'] : null);

        return ['identificador' => $t['identificador'], 'tipo' => $t['tipo'], 'departamento' => 'BO07',
            'provincia' => $asignacion['provincia'] ?? $filtros['provincia']['identificador'] ?? null,
            'municipio' => $asignacion['municipio'] ?? $filtros['municipio']['identificador'] ?? null,
            'fuenteCatalogo' => 'hdx_codab_bol_v02', 'versionCatalogo' => $this->catalogo->metadatos()['version'],
            'motivoAsignacion' => $asignacion['motivoAsignacion'] ?? null];
    }
}
