<?php

namespace App\Http\Controllers;

use App\Services\Sequias\Experimental\CatalogoMuestrasSequias;
use App\Services\Territorios\CatalogoTerritorial;
use Illuminate\Contracts\View\View;

class InicioController extends Controller
{
    public function modulo(string $modulo, CatalogoTerritorial $territorios, CatalogoMuestrasSequias $muestras): View
    {
        $modulos = $this->modulosDisponibles();
        abort_unless(array_key_exists($modulo, $modulos), 404);

        $datos = [
            'modulos' => $modulos,
            'identificador' => $modulo,
            'modulo' => $modulos[$modulo],
        ];
        if ($modulo === 'sequias') {
            $datos['catalogos'] = [
                'territorios' => $territorios->listar(),
                'versionTerritorial' => $territorios->metadatos()['version'],
                'muestras' => $muestras->consultar()['datos'],
                'mesMaximo' => now('UTC')->startOfMonth()->subMonth()->format('Y-m'),
            ];
        }

        return view(match ($modulo) {
            'focos-calor' => 'modulos.focos-calor.index',
            'sequias' => 'modulos.sequias.index',
        }, $datos);
    }

    private function modulosDisponibles(): array
    {
        return array_filter(config('geocruz.modulos'), fn (array $modulo): bool => $modulo['habilitado']);
    }
}
