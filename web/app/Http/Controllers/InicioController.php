<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class InicioController extends Controller
{
    public function index(): View
    {
        return view('inicio', ['modulos' => config('geocruz.modulos')]);
    }

    public function modulo(string $modulo): View
    {
        $modulos = config('geocruz.modulos');
        abort_unless(array_key_exists($modulo, $modulos), 404);

        return view('modulo', [
            'modulos' => $modulos,
            'identificador' => $modulo,
            'modulo' => $modulos[$modulo],
        ]);
    }
}
