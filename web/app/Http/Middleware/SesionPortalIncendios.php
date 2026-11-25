<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SesionPortalIncendios
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('portal-incendios', 'portal-incendios/*')) {
            return $next($request);
        }

        // Este middleware se ejecuta antes de StartSession y de la protección CSRF.
        $original = ['session.cookie' => config('session.cookie'), 'session.path' => config('session.path')];
        config(['session.cookie' => 'geosur_portal_incendios', 'session.path' => '/portal-incendios']);

        try {
            return $next($request);
        } finally {
            config($original);
        }
    }
}
