<?php

namespace App\Services\Datos;

use App\Services\Api\ErrorConsulta;

final class ProcesarBandejaDatos
{
    public function __construct(private ImportarHistorial $importar, private AutorizarIngestion $autorizar) {}

    public function ejecutar(string $directorio, int $actor): array
    {
        $this->autorizar->ejecutar($actor);
        if (str_contains($directorio, '://') || is_link($directorio) || ! is_dir($directorio) || ! is_writable($directorio)) {
            throw new ErrorConsulta('BANDEJA_INVALIDA', 'Elige una carpeta local de cargas con permiso de escritura.');
        }
        $cerrojo = @fopen($directorio.'/.geosur-worker.lock', 'c');
        if (! $cerrojo) {
            throw new ErrorConsulta('BANDEJA_INVALIDA', 'No se pudo reservar la carpeta de cargas.');
        }
        try {
            if (! flock($cerrojo, LOCK_EX | LOCK_NB)) {
                throw new ErrorConsulta('BANDEJA_OCUPADA', 'Otra tarea está revisando esa carpeta.', 409);
            }
            $archivos = glob($directorio.'/*.json');
            if ($archivos === false || count($archivos) > 100) {
                throw new ErrorConsulta('BANDEJA_INVALIDA', 'Se admiten hasta 100 archivos por carpeta.');
            }
            sort($archivos, SORT_STRING);
            $total = ['archivos' => 0, 'nuevas' => 0, 'repetidas' => 0];
            foreach ($archivos as $archivo) {
                // Cada archivo es un lote atómico. No borrar ni mover archivos del operador.
                $resultado = $this->importar->ejecutar($archivo, $actor);
                $total['archivos']++;
                $total['nuevas'] += $resultado['nuevas'];
                $total['repetidas'] += $resultado['repetidas'];
            }

            return $total;
        } finally {
            flock($cerrojo, LOCK_UN);
            fclose($cerrojo);
        }
    }
}
