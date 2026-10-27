@props(['modulos'])
<aside class="enviro-lateral" id="menu-panel" aria-label="Panel de EnviroSense">
    <a class="enviro-marca" href="{{ route('inicio') }}" aria-label="EnviroSense, mapa de monitoreo">
        <span class="enviro-simbolo" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3C7 3 4 7 4 12c0 5 4 9 8 9s8-4 8-9"/><path d="M12 3c3 2 4 6 4 9s-1 7-4 9M4 12h16"/></svg></span>
        <span class="enviro-marca-texto"><strong>EnviroSense</strong><small>OBS · GeoCruz</small></span>
    </a>
    <button class="enviro-contraer" id="contraer-menu" type="button" aria-controls="menu-panel" aria-expanded="true" aria-label="Contraer menú"><span aria-hidden="true">‹</span></button>
    <nav class="enviro-navegacion" aria-label="Navegación principal">
        <div class="enviro-seccion">Navegación</div>
        <a class="enviro-enlace {{ request()->routeIs('inicio') ? 'activo' : '' }}" href="{{ route('inicio') }}" title="Mapa de Monitoreo" @if(request()->routeIs('inicio')) aria-current="page" @endif>
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
