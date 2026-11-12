<?php

namespace App\Console\Commands;

use App\Services\Api\ErrorConsulta;
use App\Services\Inundaciones\PublicadorProductosAgua;
use Illuminate\Console\Command;

final class RegistrarProductoAgua extends Command
{
    protected $signature = 'agua:registrar {archivo : Producto JSON local ya revisado} {--reemplazar : Sustituir el producto vigente para el mismo lugar y fechas}';

    protected $description = 'Registra un producto de agua revisado en el almacén privado local';

    public function handle(PublicadorProductosAgua $publicador): int
    {
        try {
            $sha = $publicador->archivo($this->argument('archivo'), (bool) $this->option('reemplazar'));
            $this->info('Producto registrado: '.$sha);

            return self::SUCCESS;
        } catch (ErrorConsulta $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }
    }
}
