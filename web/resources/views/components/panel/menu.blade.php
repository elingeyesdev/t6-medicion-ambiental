@props(['modulos'])
<aside class="enviro-lateral" id="menu-panel" aria-label="Panel de EnviroSense">
    <a class="enviro-marca" href="{{ route('inicio') }}" aria-label="EnviroSense, inicio">
        <x-panel.marca />
    </a>
    <button class="enviro-contraer" id="contraer-menu" type="button" aria-controls="menu-panel" aria-expanded="true" aria-label="Contraer menú"><span aria-hidden="true">‹</span></button>
    <nav class="enviro-navegacion" aria-label="Navegación principal">
        <div class="enviro-seccion">Navegación</div>
        <a class="enviro-enlace {{ request()->routeIs('mapa') ? 'activo' : '' }}" href="{{ route('mapa') }}" title="Mapa de Monitoreo" @if(request()->routeIs('mapa')) aria-current="page" @endif>
            <x-panel.icono nombre="world" /><span>Mapa de Monitoreo</span>
        </a>
        <div class="enviro-seccion enviro-seccion-modulos">Módulos ambientales</div>
        @foreach($modulos as $id => $item)
            <a class="enviro-enlace {{ request()->route('modulo') === $id ? 'activo' : '' }}" href="{{ route('modulos.mostrar', $id) }}" title="{{ $item['nombre'] }}" aria-label="Abrir {{ $item['nombre'] }}" @if(request()->route('modulo') === $id) aria-current="page" @endif>
                <x-panel.icono :nombre="$item['icono']" /><span>{{ $item['nombre'] }}</span>
            </a>
        @endforeach
    </nav>
    <div class="enviro-territorio"><x-panel.icono nombre="map-pin" /><span>Santa Cruz<small>Bolivia</small></span></div>
</aside>
