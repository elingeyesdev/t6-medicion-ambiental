<?php

declare(strict_types=1);

namespace App\Services\Sequias;

use App\Services\Sequias\Experimental\CalculoSpi3ConCache;
use App\Services\Sequias\Experimental\CapturaMuestraCatalogada;
use App\Services\Sequias\Experimental\CatalogoMuestrasSequias;
use App\Services\Sequias\Experimental\EnsayoSpi3;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\Cache;

/** Preparación explícita por consola; nunca se inyecta un cliente HTTP en la consulta pública. */
final class PreparadorConsultaSequias
{
    public function __construct(
        private readonly CatalogoMuestrasSequias $catalogo,
        private readonly RepositorioSeriesPrecipitacion $series,
        private readonly EnsayoSpi3 $ensayo,
        private readonly CalculoSpi3ConCache $calculos,
        private readonly AlmacenPublicacionesSequias $almacen,
        private readonly CapturaMuestraCatalogada $captura,
        private readonly ProcedenciaSeriePrecipitacion $procedencias,
    ) {}

    public static function periodo(string $mes): array
    {
        if (! preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])$/D', $mes) || $mes < '2021-01' || $mes >= now()->utc()->format('Y-m')) {
            throw new SeriePrecipitacionInvalida('MES_NO_ADMITIDO', 'Indica mesFinal AAAA-MM desde 2021-01 y anterior al mes UTC actual.');
        }
        $fecha = new DateTimeImmutable($mes.'-01', new DateTimeZone('UTC'));

        return ['mesFinal' => $mes, 'desde' => $fecha->modify('-2 months')->format('Y-m-d'), 'hastaIncluido' => $fecha->modify('last day of this month')->format('Y-m-d'), 'zona' => 'UTC'];
    }

    public static function clave(string $muestra, string $mes): string
    {
        return hash('sha256', $muestra.'|'.$mes);
    }

    private function celda(array $p): string
    {
        return hash('sha256', json_encode(['Open-Meteo', 'ERA5', $p['coordenadaCelda']], JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));
    }

    public function ejecutar(string $muestra, string $mes, ?string $base, ?string $objetivo, bool $consultar = false, bool $capturarBase = false, bool $renovar = false): array
    {
        $lock = $consultar || $capturarBase ? Cache::lock('sequias:carga:proveedor', 120) : null;
        if ($lock !== null && ! $lock->get()) {
            throw new SeriePrecipitacionInvalida('CARGA_OCUPADA', 'Hay otra carga de lluvia en curso. Se preparan en serie para limitar consultas al proveedor.');
        }
        try {
            return $this->preparar($muestra, $mes, $base, $objetivo, $consultar, $capturarBase, $renovar);
        } finally {
            $lock?->release();
        }
    }

    private function preparar(string $muestra, string $mes, ?string $base, ?string $objetivo, bool $consultar, bool $capturarBase, bool $renovar): array
    {
        $periodo = self::periodo($mes);
        $seleccion = $this->catalogo->consultar(muestra: $muestra);
        $s = $seleccion['datos'][0];
        if ($consultar) {
            if ($objetivo !== null) {
                throw new SeriePrecipitacionInvalida('OPCIONES_INCOMPATIBLES', 'Usa --objetivo o --consultar, no ambos.');
            }
            $objetivo = $this->captura->ejecutar($muestra, $periodo['desde'], $periodo['hastaIncluido'], $renovar)['version']['identificador'];
        }
        if ($objetivo === null) {
            throw new SeriePrecipitacionInvalida('PREPARACION_REQUERIDA', 'Indica --objetivo con ID preparado, o --consultar para obtener lluvia fuera de la petición web.');
        }
        $o = $this->series->leer($objetivo)['procedencia'];
        if ($o['muestra'] !== $muestra || $o['coordenadaSolicitada'] !== $s['coordenadaSolicitada']) {
            throw new SeriePrecipitacionInvalida('SERIES_INCOMPATIBLES', 'La captura objetivo debe corresponder al punto registrado.');
        }
        if ($base === null) {
            $registrada = $this->almacen->leer('bases', $this->celda($o));
            $base = $registrada['base'] ?? null;
            if ($base !== null && (! is_string($base) || ($registrada['celda'] ?? null) !== $o['coordenadaCelda'])) {
                throw new SeriePrecipitacionInvalida('PUBLICACION_INVALIDA', 'El registro de base no corresponde a esta celda.');
            }
        }
        if ($base === null && $capturarBase) {
            $base = $this->captura->ejecutar($muestra, '1990-11-01', '2020-12-31', $renovar)['version']['identificador'];
        }
        if ($base === null) {
            throw new SeriePrecipitacionInvalida('BASE_NO_PREPARADA', 'Prepara la base fuera de HTTP: indica --base o autoriza la descarga histórica con --capturar-base.');
        }
        $preparado = $this->ensayo->preparar($base, $objetivo, $mes, DateTimeImmutable::createFromInterface(now()), true);
        $r = $preparado->informe;
        if ($r['tipoEvidencia'] !== 'captura_externa' || $r['versiones'][0]['procedencia']['periodo'] !== ['desde' => '1990-11-01', 'hastaIncluido' => '2020-12-31', 'zona' => 'UTC']
            || $o['periodo'] !== array_diff_key($periodo, ['mesFinal' => true])) {
            throw new SeriePrecipitacionInvalida('PRODUCTO_NO_PROPUESTO', 'La API solo publica capturas externas ERA5, base noviembre 1990–diciembre 2020 y ventana objetivo exacta.');
        }
        $motor = $this->calculos->motor();
        $pBase = $r['versiones'][0]['procedencia'];
        $claveAjuste = hash('sha256', json_encode([$motor, $pBase['sha256'], $pBase['coordenadaCelda']], JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));
        $ajuste = $this->almacen->leer('ajustes', $claveAjuste);
        if ($ajuste === null) {
            $ajuste = ['motor' => $motor, 'baseSha256' => $pBase['sha256'], 'meses' => $this->ensayo->referencia($base)];
            $this->almacen->guardar('ajustes', $claveAjuste, $ajuste);
        }
        if (($ajuste['motor'] ?? null) !== $motor || ($ajuste['baseSha256'] ?? null) !== $pBase['sha256'] || count($ajuste['meses'] ?? []) !== 12) {
            throw new SeriePrecipitacionInvalida('PUBLICACION_INVALIDA', 'Los ajustes preparados no corresponden a la base/motor actuales.');
        }
        $delMes = $ajuste['meses'][(int) substr($mes, 5)] ?? null;
        if (! is_array($delMes) || ($delMes['acumuladosMm'] ?? null) !== array_column($r['base']['ventanas'], 'acumuladoMm')
            || ! array_key_exists('parametrosAjuste', $delMes)) {
            throw new SeriePrecipitacionInvalida('PUBLICACION_INVALIDA', 'El ajuste mensual no corresponde a las ventanas de la base verificada.');
        }
        $r = $this->ensayo->calcularPreparado($preparado, $delMes['parametrosAjuste']);
        $instante = new DateTimeImmutable($this->procedencias->instante($o['obtenidoEn']));
        $ttl = $r['resultado']['valor'] === null ? 900 : 86400;
        $expira = $instante->modify('+'.$ttl.' seconds');
        if ($instante > now() || $expira <= now()) {
            throw new SeriePrecipitacionInvalida('PERIODO_NO_DISPONIBLE', 'La captura objetivo está vencida o tiene fecha futura. Renueva la lluvia; republicar no renueva su antigüedad.');
        }
        $publicacion = ['version' => 1, 'mesFinal' => $mes, 'muestra' => $s['identificador'], 'catalogo' => $seleccion['catalogo'], 'motor' => $motor,
            'base' => $base, 'objetivo' => $objetivo, 'ajustes' => $claveAjuste, 'informe' => $r,
            'preparadoEn' => now()->utc()->format('Y-m-d\TH:i:s.u\Z'), 'vigenteHasta' => $expira->format('Y-m-d\TH:i:s.u\Z')];
        $id = $this->almacen->guardar('consultas', self::clave($muestra, $mes), $publicacion);
        $this->almacen->guardar('bases', $this->celda($pBase), ['base' => $base, 'celda' => $pBase['coordenadaCelda']]);

        return ['publicacion' => $id, 'datos' => $publicacion, 'nota' => 'Preparación terminada. La API reutiliza esta versión hasta su vencimiento; no descarga ni ajusta Gamma durante HTTP.'];
    }
}
