<?php

namespace App\View\Composers;

use App\Services\Api\ErrorConsulta;
use App\Services\Inundaciones\CatalogoProductosAgua;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class MenuPanel
{
    public function __construct(private readonly CatalogoProductosAgua $productos) {}

    public function compose(View $vista): void
    {
        $disponible = false;
        if (auth()->check() && Gate::allows('mapa.ver') && Gate::allows('datos.consultar')) {
            try {
                $disponible = $this->productos->catalogo()['productos'] !== [];
            } catch (ErrorConsulta) {
                // Un catálogo inválido no crea una entrada pública pendiente.
            }
        }
        $vista->with('aguaDisponible', $disponible);
    }
}
