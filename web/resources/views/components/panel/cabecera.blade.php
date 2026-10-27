<header class="enviro-cabecera">
    <button type="button" class="enviro-boton-icono enviro-abrir" id="abrir-menu" aria-controls="menu-panel" aria-expanded="false" aria-label="Abrir menú"><svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button>
    <div class="enviro-ruta"><span>GeoCruz · Bolivia / Observación / </span><strong>@yield('titulo', 'Mapa de Monitoreo')</strong></div>
    <button type="button" class="enviro-boton-icono" id="cambiar-tema" aria-label="Cambiar tema" aria-pressed="false" title="Cambiar tema"><svg aria-hidden="true" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20.9 13a9 9 0 0 1-9.9-9.9A9 9 0 1 0 20.9 13Z"/></svg></button>
    @auth
        <span class="enviro-avatar" title="{{ auth()->user()->name }}" aria-label="Cuenta de {{ auth()->user()->name }}">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(auth()->user()->name, 0, 1)) }}</span>
        <form action="{{ route('logout') }}" method="post">@csrf<button type="submit" class="enviro-boton-icono" aria-label="Cerrar sesión" title="Cerrar sesión"><svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 4H4v16h5M10 12h11m-4-4 4 4-4 4"/></svg></button></form>
    @endauth
</header>
