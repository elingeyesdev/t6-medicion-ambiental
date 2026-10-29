<header class="portada-cabecera">
    <a href="{{ route('inicio') }}" aria-label="GeoSur, inicio"><x-panel.marca /></a>
    <nav aria-label="Navegación de inicio">
        <a href="{{ request()->routeIs('inicio') ? '#explorar' : route('inicio').'#explorar' }}">Qué puedes consultar</a>
        @auth
            <a class="btn btn-primary" href="{{ route('mapa') }}">Ir al mapa</a>
        @elseif(request()->routeIs('login'))
            <a class="btn btn-primary" href="{{ route('inicio') }}">Inicio</a>
        @else
            <a class="btn btn-primary" href="{{ route('login') }}">Ingresar</a>
        @endauth
    </nav>
</header>
