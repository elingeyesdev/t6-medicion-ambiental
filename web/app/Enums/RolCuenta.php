<?php

namespace App\Enums;

enum RolCuenta: string
{
    case Administrador = 'admin';
    case Visualizador = 'visualizador';

    public function nombre(): string
    {
        return match ($this) {
            self::Administrador => 'Administrador',
            self::Visualizador => 'Visualizador',
        };
    }

    public function permisos(): array
    {
        return match ($this) {
            self::Administrador => ['mapa.ver', 'reportes.ver', 'reportes.crear', 'simulaciones.gestionar', 'alertas.gestionar', 'usuarios.administrar', 'datos.consultar', 'datos.gestionar'],
            self::Visualizador => ['mapa.ver', 'reportes.ver', 'datos.consultar'],
        };
    }
}
