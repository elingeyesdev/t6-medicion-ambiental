<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Sequias\SeriePrecipitacionInvalida;
use App\Services\Sequias\ValidadorSeriePrecipitacion;
use Illuminate\Console\Command;
use JsonException;

final class ValidarSeriePrecipitacion extends Command
{
    protected $signature = 'sequias:validar-serie {archivo : JSON diario local, sin URL} {--desde= : Primer día del primer mes} {--hasta= : Último día del último mes}';

    protected $description = 'Revisar completitud mensual de precipitación mm/UTC sin red, índices ni cambios en la API';

    private const MAX_BYTES = 8 * 1024 * 1024;

    public function handle(ValidadorSeriePrecipitacion $validador): int
    {
        try {
            $desde = $this->option('desde');
            $hasta = $this->option('hasta');
            if (! is_string($desde) || ! is_string($hasta)) {
                throw new SeriePrecipitacionInvalida('PERIODO_REQUERIDO', 'Indica --desde y --hasta; no se infiere cobertura desde los registros recibidos.');
            }
            $archivo = (string) $this->argument('archivo');
            $ruta = realpath($archivo);
            if ($ruta === false || ! is_file($ruta) || ! is_readable($ruta)) {
                throw new SeriePrecipitacionInvalida('ARCHIVO_NO_DISPONIBLE', 'No se puede leer el archivo local indicado.');
            }
            $recurso = @fopen($ruta, 'rb');
            if ($recurso === false) {
                throw new SeriePrecipitacionInvalida('ARCHIVO_NO_DISPONIBLE', 'No se puede abrir el archivo local indicado.');
            }
            try {
                $crudo = stream_get_contents($recurso, self::MAX_BYTES + 1);
            } finally {
                fclose($recurso);
            }
            if ($crudo === false) {
                throw new SeriePrecipitacionInvalida('ARCHIVO_NO_DISPONIBLE', 'No se puede leer el contenido del archivo.');
            }
            if (strlen($crudo) > self::MAX_BYTES) {
                throw new SeriePrecipitacionInvalida('ARCHIVO_DEMASIADO_GRANDE', 'El archivo supera el límite de 8 MiB.');
            }
            try {
                $documento = json_decode($crudo, true, 64, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw new SeriePrecipitacionInvalida('JSON_INVALIDO', 'El archivo no contiene JSON válido o excede la profundidad permitida.');
            }
            if (! is_array($documento)) {
                throw new SeriePrecipitacionInvalida('ESTRUCTURA_INVALIDA', 'El archivo debe contener el objeto de una serie diaria.');
            }
            $informe = $validador->validar($documento, $desde, $hasta);
            $informe['origen'] = ['tipo' => 'archivo_local', 'sha256' => hash('sha256', $crudo), 'bytes' => strlen($crudo)];
            $this->line(json_encode($informe, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));

            return $informe['aptoParaAgregacionMensual'] ? self::SUCCESS : self::FAILURE;
        } catch (SeriePrecipitacionInvalida $e) {
            $this->line(json_encode(['error' => ['codigo' => $e->codigo, 'mensaje' => $e->getMessage()]], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            return self::INVALID;
        }
    }
}
