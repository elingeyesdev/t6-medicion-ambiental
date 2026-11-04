<?php

namespace App\Services\Datos;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

final class AutorizarIngestion
{
    public function ejecutar(?int $actor): User
    {
        // También en consola: nunca usar una cuenta implícita ni conservar permisos de un daemon.
        $usuario = $actor ? User::find($actor) : null;
        if (! $usuario || ! $usuario->activo || Gate::forUser($usuario)->denies('datos.gestionar')) {
            throw new AuthorizationException('Se necesita una cuenta activa con permiso para cargar datos.');
        }

        return $usuario;
    }
}
