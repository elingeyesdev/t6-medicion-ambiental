<?php

namespace App\Services\Datos;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class ImportarHistorial
{
    public function __construct(private RegistrarLecturas $registrar, private CatalogoAmbiental $catalogo, private AutorizarIngestion $autorizar) {}

    public function ejecutar(string $archivo, int $actor, bool $comprobar = false): array
    {
        $this->autorizar->ejecutar($actor);

        return $this->contenido(ArchivoDatos::leer($archivo), $actor, $comprobar);
    }

    public function contenido(string $contenido, int $actor, bool $comprobar = false): array
    {
        $this->autorizar->ejecutar($actor);
        $datos = ArchivoDatos::json($contenido);
        Validator::make(['documento' => $datos], [
            'documento' => ['required', 'array:formato,lecturas'],
            'documento.formato' => ['required', Rule::in(['geosur.historial.v1'])],
            'documento.lecturas' => ['required', 'array', 'list', 'min:1', 'max:1000'],
            'documento.lecturas.*' => ['required', 'array:tiempo,localidad,metrica,valor,unidad,origen,fuente'],
            'documento.lecturas.*.tiempo' => ['required', 'string'],
            'documento.lecturas.*.localidad' => ['required', 'string', 'max:60'],
            'documento.lecturas.*.metrica' => ['required', Rule::in(array_keys($this->catalogo->metricas()))],
            'documento.lecturas.*.valor' => ['required', 'numeric'],
            'documento.lecturas.*.unidad' => ['required', 'string'],
            'documento.lecturas.*.origen' => ['required', Rule::in(array_keys(CatalogoAmbiental::ORIGENES))],
            'documento.lecturas.*.fuente' => ['required', 'string'],
        ])->validate();
        $filas = [];
        foreach ($datos['lecturas'] as $i => $fila) {
            Validator::make($fila, ['unidad' => [Rule::in([$this->catalogo->metricas()[$fila['metrica']]['unidad']])]],
                ['unidad.in' => 'La unidad de la lectura '.($i + 1).' no corresponde a la variable.'])->validate();
            $filas[] = ['punto' => $fila['localidad'], 'metrica' => $fila['metrica'], 'valor' => $fila['valor'],
                'instante' => $fila['tiempo'], 'origen' => $fila['origen'], 'fuente' => $fila['fuente']];
        }

        return $this->registrar->ejecutar($filas, $actor, $comprobar);
    }
}
