<?php

namespace App\Enums;

enum RolCuenta: string
{
    case Administrador = 'admin';
    case Analista = 'analista';
    case Visualizador = 'visualizador';

    public function nombre(): string
    {
        return match ($this) {
            self::Administrador => 'Administrador', self::Analista => 'Analista', self::Visualizador => 'Visualizador',
        };
    }

    public function permisos(): array
    {
        return match ($this) {
            self::Administrador => ['mapa.ver', 'reportes.ver', 'reportes.crear', 'simulaciones.gestionar', 'alertas.gestionar', 'usuarios.administrar'],
            self::Analista => ['mapa.ver', 'reportes.ver', 'reportes.crear', 'simulaciones.gestionar', 'alertas.gestionar'],
            self::Visualizador => ['mapa.ver', 'reportes.ver'],
        };
    }
}
