<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Sequias\LectorJsonLocal;
use App\Services\Sequias\RepositorioSeriesPrecipitacion;
use App\Services\Sequias\SeriePrecipitacionInvalida;
use Illuminate\Console\Command;

final class PrepararSeriePrecipitacion extends Command
{
    protected $signature = 'sequias:preparar-serie {archivo : Captura JSON local} {procedencia : Declaración JSON local}';

    protected $description = 'Guardar una versión verificable de lluvia y su procedencia, sin red ni cálculo de sequía';

    public function handle(RepositorioSeriesPrecipitacion $repositorio, LectorJsonLocal $lector): int
    {
        try {
            $declaracion = $lector->leer((string) $this->argument('procedencia'), 64 * 1024)['datos'];
            $version = $repositorio->preparar((string) $this->argument('archivo'), $declaracion);
            unset($version['informe']['meses']);
            $this->line(json_encode($version, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));

            return $version['informe']['aptoParaAgregacionMensual'] ? self::SUCCESS : self::FAILURE;
        } catch (SeriePrecipitacionInvalida $e) {
            $this->line(json_encode(['error' => ['codigo' => $e->codigo, 'mensaje' => $e->getMessage()]], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            return self::INVALID;
        }
    }
}
