@props(['modulos'])
<aside class="enviro-lateral" id="menu-panel" aria-label="Panel de GeoSur">
    <a class="enviro-marca" href="{{ route('inicio') }}" aria-label="GeoSur, inicio">
        <x-panel.marca />
    </a>
    <button class="enviro-contraer" id="contraer-menu" type="button" aria-controls="menu-panel" aria-expanded="true" aria-label="Contraer menú"><span aria-hidden="true">‹</span></button>
    <nav class="enviro-navegacion" aria-label="Navegación principal">
        <div class="enviro-seccion">Monitoreo</div>
        <a class="enviro-enlace {{ request()->routeIs('mapa') ? 'activo' : '' }}" href="{{ route('mapa') }}" title="Mapa de Monitoreo" @if(request()->routeIs('mapa')) aria-current="page" @endif>
            <x-panel.icono nombre="world" /><span>Mapa de Monitoreo</span>
        </a>
        @can('datos.consultar')
            <a class="enviro-enlace {{ request()->routeIs('sensores*') ? 'activo' : '' }}" href="{{ route('sensores') }}" title="Mediciones" @if(request()->routeIs('sensores*')) aria-current="page" @endif><x-panel.icono nombre="layout-dashboard" /><span>Mediciones</span></a>
        @endcan
        @can('simulaciones.gestionar')
            <a class="enviro-enlace {{ request()->routeIs('simulaciones*') ? 'activo' : '' }}" href="{{ route('simulaciones') }}" title="Simulaciones"><x-panel.icono nombre="world" /><span>Simulaciones</span></a>
        @endcan
        @can('reportes.ver')
            <a class="enviro-enlace {{ request()->routeIs('reportes*') ? 'activo' : '' }}" href="{{ route('reportes') }}" title="Reportes"><x-panel.icono nombre="layout-dashboard" /><span>Reportes</span></a>
            <a class="enviro-enlace {{ request()->routeIs('plantillas*') ? 'activo' : '' }}" href="{{ route('plantillas') }}" title="Plantillas"><x-panel.icono nombre="layout-dashboard" /><span>Plantillas</span></a>
        @endcan
        <div class="enviro-seccion enviro-seccion-modulos">Análisis del territorio</div>
        @foreach($modulos as $id => $item)
            <a class="enviro-enlace {{ request()->route('modulo') === $id ? 'activo' : '' }}" href="{{ route('modulos.mostrar', $id) }}" title="{{ $item['nombre'] }}" aria-label="Abrir {{ $item['nombre'] }}" @if(request()->route('modulo') === $id) aria-current="page" @endif>
                <x-panel.icono :nombre="$item['icono']" /><span>{{ $item['nombre'] }}</span>
            </a>
        @endforeach
        <div class="enviro-seccion">Cuenta</div>
        <a class="enviro-enlace {{ request()->routeIs('cuenta*') ? 'activo' : '' }}" href="{{ route('cuenta') }}" title="Mi cuenta" @if(request()->routeIs('cuenta*')) aria-current="page" @endif><x-panel.icono nombre="map-pin" /><span>Mi cuenta</span></a>
        @can('usuarios.administrar')
            <a class="enviro-enlace {{ request()->routeIs('usuarios*') ? 'activo' : '' }}" href="{{ route('usuarios') }}" title="Usuarios" @if(request()->routeIs('usuarios*')) aria-current="page" @endif><x-panel.icono nombre="layout-dashboard" /><span>Usuarios</span></a>
        @endcan
    </nav>
    <div class="enviro-territorio"><x-panel.icono nombre="map-pin" /><span>@if(request()->routeIs('mapa', 'sensores*', 'simulaciones*', 'reportes*', 'plantillas*'))Sudamérica<small>Monitoreo ambiental</small>@else Santa Cruz<small>Bolivia</small>@endif</span></div>
</aside>
