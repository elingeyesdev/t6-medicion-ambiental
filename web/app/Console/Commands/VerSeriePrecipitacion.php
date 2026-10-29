<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Sequias\RepositorioSeriesPrecipitacion;
use App\Services\Sequias\SeriePrecipitacionInvalida;
use Illuminate\Console\Command;

final class VerSeriePrecipitacion extends Command
{
    protected $signature = 'sequias:ver-serie {identificador : ID devuelto al preparar} {--detalle : Incluir el informe mensual completo}';

    protected $description = 'Comprobar y recuperar una versión local de lluvia sin consultar proveedores';

    public function handle(RepositorioSeriesPrecipitacion $repositorio): int
    {
        try {
            $version = $repositorio->leer((string) $this->argument('identificador'));
            if (! $this->option('detalle')) {
                unset($version['informe']['meses']);
            }
            $this->line(json_encode($version, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));

            return $version['informe']['aptoParaAgregacionMensual'] ? self::SUCCESS : self::FAILURE;
        } catch (SeriePrecipitacionInvalida $e) {
            $this->line(json_encode(['error' => ['codigo' => $e->codigo, 'mensaje' => $e->getMessage()]], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            return self::INVALID;
        }
    }
}
