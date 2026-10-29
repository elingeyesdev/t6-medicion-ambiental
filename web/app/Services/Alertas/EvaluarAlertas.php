<?php

namespace App\Services\Alertas;

use App\Models\AlertaAmbiental;
use App\Models\LecturaAmbiental;
use Illuminate\Support\Facades\DB;

final class EvaluarAlertas
{
    public function __construct(private UmbralesAmbientales $umbrales, private PrepararAvisos $avisos) {}

    public function ejecutar(array $lecturas): int
    {
        usort($lecturas, fn ($a, $b) => $a->instante <=> $b->instante ?: $a->id <=> $b->id);
        $nuevas = 0;
        foreach ($lecturas as $lectura) {
            $nuevas += DB::transaction(fn () => $this->evaluar($lectura), 3);
        }

        return $nuevas;
    }

    private function evaluar(LecturaAmbiental $l): int
    {
        $nivel = $this->umbrales->clasificar($l->metrica, $l->valor);
        if (! $nivel) {
            return 0;
        }
        $clave = hash('sha256', json_encode([$l->punto_id, $l->metrica, $l->origen, $l->fuente]));
        $tabla = DB::table('estados_alertas');
        $estado = $tabla->where('clave', $clave)->lockForUpdate()->first();
        $instante = $l->instante->format('Y-m-d H:i:s');
        if ($estado && $estado->instante >= $instante) {
            return 0;
        }
        $tabla->updateOrInsert(['clave' => $clave], ['lectura_id' => $l->id, 'instante' => $instante, 'nivel' => $nivel['nivel'], 'updated_at' => now(), 'created_at' => $estado?->created_at ?? now()]);
        if (($estado && (int) $estado->nivel === $nivel['nivel']) || ! in_array($nivel['severidad'], ['critica', 'emergencia'], true)) {
            return 0;
        }
        $punto = $l->punto;
        $alerta = AlertaAmbiental::firstOrCreate(['lectura_id' => $l->id], ['metrica' => $l->metrica, 'origen' => $l->origen, 'severidad' => $nivel['severidad'], 'instante' => $instante,
            'contenido' => ['punto' => $punto->codigo, 'nombre' => $punto->nombre, 'pais' => $punto->pais, 'latitud' => $l->latitud, 'longitud' => $l->longitud,
                'valor' => $l->valor, 'fuente' => $l->fuente, 'umbral' => $nivel]]);
        if ($alerta->wasRecentlyCreated) {
            $this->avisos->ejecutar($alerta);

            return 1;
        }

        return 0;
    }
}
