<header class="landing-header" data-landing-header>
    <div class="enviro-container landing-header-inner">
        <a class="landing-brand" href="{{ route('inicio') }}" aria-label="GeoSur, inicio"><img src="{{ asset('img/marca/geosur-simbolo.svg') }}" alt="" width="50" height="40"><span>Geo<span>Sur</span></span></a>
        <nav class="landing-desktop-nav" aria-label="Navegación principal">
            <a href="#inicio">Inicio</a><a href="#plataforma">Plataforma</a><a href="#metricas">Métricas</a><a href="#contacto">Contacto</a>
        </nav>
        <a class="enviro-button enviro-button-small" href="{{ auth()->check() ? route('mapa') : route('login') }}">{{ auth()->check() ? 'Ir al Mapa' : 'Iniciar Sesión' }}</a>
    </div>
</header>
