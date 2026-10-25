<?php

namespace App\Console\Commands;

use App\Services\Sequias\PreparadorConsultaSequias;
use App\Services\Sequias\SeriePrecipitacionInvalida;
use Illuminate\Console\Command;

final class PrepararConsultaSequias extends Command
{
    protected $signature = 'sequias:preparar-consulta {muestra} {mes : Mes final AAAA-MM} {--base= : ID de base local, reutilizable por celda} {--objetivo= : ID de captura objetivo local} {--consultar : Obtener objetivo del proveedor fuera de HTTP} {--capturar-base : Autorizar descarga histórica si no hay base de la celda} {--renovar : Renovar capturas en lugar de reutilizar su índice}';

    protected $description = 'Preparar y publicar una muestra para la API de sequías, con método #35 acordado';

    public function handle(PreparadorConsultaSequias $preparador): int
    {
        try {
            $r = $preparador->ejecutar((string) $this->argument('muestra'), (string) $this->argument('mes'), $this->option('base'), $this->option('objetivo'),
                (bool) $this->option('consultar'), (bool) $this->option('capturar-base'), (bool) $this->option('renovar'));
            $this->line(json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));

            return $r['datos']['informe']['resultado']['valor'] === null ? self::FAILURE : self::SUCCESS;
        } catch (SeriePrecipitacionInvalida $e) {
            $this->line(json_encode(['error' => ['codigo' => $e->codigo, 'mensaje' => $e->getMessage()]], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            return self::INVALID;
        }
    }
}
