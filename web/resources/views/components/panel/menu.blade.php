@props(['modulos'])
<aside class="navbar navbar-vertical navbar-expand-lg" data-bs-theme="dark" aria-label="Panel de EnviroSense">
    <div class="container-fluid">
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu-panel" aria-controls="menu-panel" aria-expanded="false" aria-label="Abrir o cerrar menú">
            <span class="navbar-toggler-icon"></span>
        </button>
        <a class="navbar-brand marca-panel" href="{{ route('inicio') }}" aria-label="EnviroSense GeoCruz, inicio">
            <x-panel.icono nombre="world" class="marca-icono" />
            <span>EnviroSense<small>GeoCruz</small></span>
        </a>
        <div class="collapse navbar-collapse" id="menu-panel">
            <nav class="w-100" aria-label="Navegación principal">
                <div class="nav-seccion">Monitoreo ambiental</div>
                <ul class="navbar-nav pt-lg-2">
                    <li class="nav-item {{ request()->routeIs('inicio') ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('inicio') }}" @if(request()->routeIs('inicio')) aria-current="page" @endif>
                            <span class="nav-link-icon"><x-panel.icono nombre="layout-dashboard" /></span><span class="nav-link-title">Panel</span>
                        </a>
                    </li>
                    @foreach($modulos as $id => $item)
                        <li class="nav-item {{ request()->route('modulo') === $id ? 'active' : '' }}">
                            <a class="nav-link" href="{{ route('modulos.mostrar', $id) }}" @if(request()->route('modulo') === $id) aria-current="page" @endif>
                                <span class="nav-link-icon"><x-panel.icono :nombre="$item['icono']" /></span><span class="nav-link-title">{{ $item['nombre'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
                <div class="nav-territorio"><x-panel.icono nombre="map-pin" /><span>Santa Cruz<small>Bolivia</small></span></div>
            </nav>
        </div>
    </div>
</aside>
