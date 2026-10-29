<?php

namespace App\Services\Datos;

use App\Services\Api\ErrorConsulta;

final class ImportarCsv
{
    public function __construct(private RegistrarLecturas $registrar) {}

    public function ejecutar(string $archivo, ?int $actor = null): array
    {
        if (! is_file($archivo) || ! is_readable($archivo) || filesize($archivo) > 2 * 1024 * 1024) {
            throw new ErrorConsulta('ARCHIVO_INVALIDO', 'El archivo debe ser un CSV de hasta 2 MB.');
        }
        $recurso = fopen($archivo, 'rb');
        if ($recurso === false) {
            throw new ErrorConsulta('ARCHIVO_INVALIDO', 'No se pudo abrir el archivo.');
        }
        try {
            $cabecera = fgetcsv($recurso, 0, ',', '"', '');
            if (is_array($cabecera)) {
                $cabecera[0] = preg_replace('/^\xEF\xBB\xBF/', '', $cabecera[0]);
            }
            if ($cabecera !== ['punto', 'metrica', 'valor', 'instante', 'origen', 'fuente']) {
                throw new ErrorConsulta('ARCHIVO_INVALIDO', 'Revisa las columnas del archivo de lecturas.');
            }
            $filas = [];
            while (($fila = fgetcsv($recurso, 0, ',', '"', '')) !== false) {
                if ($fila === [null]) {
                    continue;
                }
                if (count($fila) !== count($cabecera) || count($filas) >= 1000) {
                    throw new ErrorConsulta('ARCHIVO_INVALIDO', 'Revisa las filas del archivo. El máximo es 1000 lecturas.');
                }
                $filas[] = array_combine($cabecera, $fila);
            }

            return $this->registrar->ejecutar($filas, $actor);
        } finally {
            fclose($recurso);
        }
    }
}
