<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Sequias\LectorJsonLocal;
use App\Services\Sequias\SeriePrecipitacionInvalida;
use App\Services\Sequias\ValidadorSeriePrecipitacion;
use Illuminate\Console\Command;

final class ValidarSeriePrecipitacion extends Command
{
    protected $signature = 'sequias:validar-serie {archivo : JSON diario local, sin URL} {--desde= : Primer día del primer mes} {--hasta= : Último día del último mes}';

    protected $description = 'Revisar completitud mensual de precipitación mm/UTC sin red, índices ni cambios en la API';

    public function handle(ValidadorSeriePrecipitacion $validador, LectorJsonLocal $lector): int
    {
        try {
            $desde = $this->option('desde');
            $hasta = $this->option('hasta');
            if (! is_string($desde) || ! is_string($hasta)) {
                throw new SeriePrecipitacionInvalida('PERIODO_REQUERIDO', 'Indica --desde y --hasta; no se infiere cobertura desde los registros recibidos.');
            }
            $archivo = $lector->leer((string) $this->argument('archivo'));
            $informe = $validador->validar($archivo['datos'], $desde, $hasta);
            $informe['origen'] = ['tipo' => 'archivo_local', 'sha256' => $archivo['sha256'], 'bytes' => $archivo['bytes']];
            $this->line(json_encode($informe, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));

            return $informe['aptoParaAgregacionMensual'] ? self::SUCCESS : self::FAILURE;
        } catch (SeriePrecipitacionInvalida $e) {
            $this->line(json_encode(['error' => ['codigo' => $e->codigo, 'mensaje' => $e->getMessage()]], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            return self::INVALID;
        }
    }
}
