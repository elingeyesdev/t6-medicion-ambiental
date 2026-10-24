<?php

declare(strict_types=1);

namespace App\Services\Sequias\Experimental;

use App\Services\Sequias\ProcedenciaSeriePrecipitacion;
use App\Services\Sequias\SeriePrecipitacionInvalida;
use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;
use JsonException;

/** Resultados candidatos de versiones fijas; no consulta ni renueva el proveedor. */
final class CalculoSpi3ConCache
{
    public const VIGENCIA_SEGUNDOS = 86400;

    public const VIGENCIA_NULL_SEGUNDOS = 900;

    public const VERSION_MOTOR = 'spi3-php-experimental-1.0';

    public function __construct(
        private readonly EnsayoSpi3 $ensayo,
        private readonly ProcedenciaSeriePrecipitacion $procedencias,
        private readonly Spi3Propuesto $spi,
    ) {}

    public function ejecutar(string $base, string $objetivo, string $mes, bool $recalcular = false): array
    {
        $ahora = DateTimeImmutable::createFromInterface(now()->utc());
        $preparacion = $this->ensayo->preparar($base, $objetivo, $mes, $ahora);
        $motor = $this->motor();
        $huella = hash('sha256', $this->json([$motor, $preparacion->informe]));
        $clave = 'sequias:resultado:v1:'.$huella;
        $bloqueo = Cache::lock($clave.':bloqueo', 120);
        if (! $bloqueo->get()) {
            throw new SeriePrecipitacionInvalida('CALCULO_OCUPADO', 'Otra ejecución está preparando este resultado; vuelve a intentarlo.');
        }
        try {
            if (! $recalcular && ($guardado = Cache::get($clave)) !== null) {
                $validado = $this->validar($guardado, $huella, $motor, $preparacion, $ahora);
                if ($validado !== null) {
                    return $this->salida($validado, true, $ahora);
                }
            }
            $informe = $this->ensayo->calcularPreparado($preparacion);
            $vigencia = $informe['resultado']['valor'] === null ? self::VIGENCIA_NULL_SEGUNDOS : self::VIGENCIA_SEGUNDOS;
            $registro = ['versionCache' => 1, 'huella' => $huella, 'motor' => $motor,
                'calculadoEn' => $ahora->format('Y-m-d\TH:i:s.u\Z'),
                'expiraEn' => $ahora->modify('+'.$vigencia.' seconds')->format('Y-m-d\TH:i:s.u\Z'), 'informe' => $informe];
            $registro['checksum'] = hash('sha256', $this->json($registro));
            if (! Cache::put($clave, $registro, $vigencia)) {
                throw new SeriePrecipitacionInvalida('CACHE_CALCULO_NO_DISPONIBLE', 'Se calculó el resultado, pero no se pudo guardar para reutilizarlo. Las series originales se conservan.');
            }

            return $this->salida($registro, false, $ahora);
        } finally {
            $bloqueo->release();
        }
    }

    private function motor(): array
    {
        $archivos = [];
        foreach (['EnsayoSpi3.php', 'Spi3Propuesto.php', 'NumericaGammaNormal.php'] as $nombre) {
            $texto = @file_get_contents(__DIR__.'/'.$nombre);
            if ($texto === false) {
                throw new SeriePrecipitacionInvalida('MOTOR_NO_DISPONIBLE', 'No se pudo identificar la implementación de cálculo.');
            }
            // Mismo código LF/CRLF identifica el mismo motor en Windows y Linux.
            $archivos[$nombre] = hash('sha256', str_replace("\r\n", "\n", $texto));
        }

        return ['version' => self::VERSION_MOTOR, 'metodo' => Spi3Propuesto::VERSION, 'archivosSha256LF' => $archivos];
    }

    private function validar(mixed $r, string $huella, array $motor, PreparacionSpi3 $preparacion, DateTimeImmutable $ahora): ?array
    {
        if (! is_array($r) || count($r) !== 7 || ($r['versionCache'] ?? null) !== 1 || ($r['huella'] ?? null) !== $huella
            || ($r['motor'] ?? null) !== $motor || ! is_array($r['informe'] ?? null) || ! is_string($r['checksum'] ?? null)) {
            $this->invalida();
        }
        $sinChecksum = $r;
        unset($sinChecksum['checksum']);
        if (! hash_equals($r['checksum'], hash('sha256', $this->json($sinChecksum)))) {
            $this->invalida();
        }
        $informeBase = $r['informe'];
        unset($informeBase['resultado']);
        if ($informeBase !== $preparacion->informe || ! is_array($resultado = $r['informe']['resultado'] ?? null)
            || count($resultado) !== 6 || array_diff(['valor', 'categoria', 'motivo', 'aniosValidos', 'parametrosAjuste', 'probabilidadAcumulada'], array_keys($resultado)) !== []
            || $resultado['aniosValidos'] !== count(array_filter(array_column($preparacion->informe['base']['ventanas'], 'acumuladoMm'), fn ($v) => $v !== null))) {
            $this->invalida();
        }
        if ($resultado['valor'] !== null && ((! is_float($resultado['valor']) && ! is_int($resultado['valor']))
            || ! is_finite((float) $resultado['valor']) || $resultado['motivo'] !== null
            || $resultado['categoria'] !== $this->spi->clasificar((float) $resultado['valor'])
            || $resultado['aniosValidos'] !== 30 || ! is_array($resultado['parametrosAjuste'])
            || ! $this->numero($resultado['probabilidadAcumulada']) || $resultado['probabilidadAcumulada'] <= 0 || $resultado['probabilidadAcumulada'] >= 1)) {
            $this->invalida();
        }
        if ($resultado['valor'] === null && ($resultado['categoria'] !== null
            || ! in_array($resultado['motivo'], ['BASE_INCOMPLETA', 'PERIODO_INCOMPLETO', 'BASE_NO_AJUSTABLE', 'AJUSTE_FALLIDO', 'PROBABILIDAD_EXTREMA'], true))) {
            $this->invalida();
        }
        if ($resultado['probabilidadAcumulada'] !== null && (! $this->numero($resultado['probabilidadAcumulada'])
            || $resultado['probabilidadAcumulada'] < 0 || $resultado['probabilidadAcumulada'] > 1)) {
            $this->invalida();
        }
        if (($p = $resultado['parametrosAjuste']) !== null && (! is_array($p) || count($p) !== 4
            || array_diff(['forma', 'escalaMm', 'probabilidadCero', 'positivos'], array_keys($p)) !== []
            || ! $this->numero($p['forma']) || $p['forma'] <= 0 || ! $this->numero($p['escalaMm']) || $p['escalaMm'] <= 0
            || ! is_int($p['positivos']) || $p['positivos'] < 10 || $p['positivos'] > 30
            || ! $this->numero($p['probabilidadCero']) || $p['probabilidadCero'] !== (30 - $p['positivos']) / 30)) {
            $this->invalida();
        }
        try {
            $calculado = new DateTimeImmutable($this->procedencias->instante($r['calculadoEn'] ?? null));
            $expira = new DateTimeImmutable($this->procedencias->instante($r['expiraEn'] ?? null));
        } catch (SeriePrecipitacionInvalida) {
            $this->invalida();
        }
        $vigencia = $resultado['valor'] === null ? self::VIGENCIA_NULL_SEGUNDOS : self::VIGENCIA_SEGUNDOS;
        if ($calculado > $ahora || $expira != $calculado->modify('+'.$vigencia.' seconds')) {
            $this->invalida();
        }

        return $expira <= $ahora ? null : $r;
    }

    private function salida(array $r, bool $cache, DateTimeImmutable $ahora): array
    {
        return [...$r['informe'], 'cacheCalculo' => ['tipo' => $cache ? 'cache_calculo' : 'calculo_php', 'esCalculoNuevo' => ! $cache,
            'huella' => $r['huella'], 'motor' => $r['motor'], 'calculadoEn' => $r['calculadoEn'], 'expiraEn' => $r['expiraEn'],
            'antiguedadCalculoSegundos' => $ahora->getTimestamp() - (new DateTimeImmutable($r['calculadoEn']))->getTimestamp(),
            'integridad' => 'verificada_con_entradas_originales']];
    }

    private function json(array $r): string
    {
        try {
            return json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->invalida();
        }
    }

    private function numero(mixed $v): bool
    {
        return (is_int($v) || is_float($v)) && is_finite((float) $v);
    }

    private function invalida(): never
    {
        throw new SeriePrecipitacionInvalida('CACHE_CALCULO_INVALIDA', 'El resultado guardado no corresponde a las entradas/motor o está alterado. Revisa la causa; --recalcular rehace solo el cálculo desde series verificadas.');
    }
}
